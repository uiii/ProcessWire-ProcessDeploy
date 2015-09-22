<?php

namespace ProcessDeploy\Connection;

use Crypt_RSA;
use Exception;
use Net_SCP;
use Net_SSH2;
use ProcessDeploy;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WireException;

require_once 'Net/SSH2.php';
require_once 'Net/SCP.php';
require_once 'Crypt/RSA.php';

class SSHExecException extends WireException
{
	protected $commandOutput;
	protected $exitCode;

	public function __construct($message, $code = 1, $previous = null)
	{
		$this->commandOutput = $message;

		parent::__construct('SSH command execution failed', $code, $previous);
	}

	public function getCommandOutput()
	{
		return $this->commandOutput;
	}
}

/**
 * Description of SSH
 *
 * @package ProcessDeploy
 * @subpackage Connection
 */
class SSH implements ConnectionInterface
{
	/**
	 * @var array
	 */
	protected $options = array(
		'host' => '',
		'port' => '22',
		'username' => '',
		'password' => '',
		'private_key_path' => '',
		'pw_path' => ''
	);

	/**
	 * @var Net_SSH2
	 */
	protected $ssh;

	/**
	 * @var Net_SCP
	 */
	protected $scp;

	/**
	 * @var string
	 */
	protected $pwPath;

	/**
	 * @var string
	 */
	protected $tmpDir;

	public function __construct($options)
	{
		foreach($options as $key => $value) {
			if (array_key_exists($key, $this->options)) {
				$this->options[$key] = $value;
			}
		}

		$this->pwPath = rtrim($this->options['pw_path'], '/') . '/';
	}

	public function __destruct()
	{
		$this->disconnect();
	}

	public function getType()
	{
		return 'ssh';
	}

	public function getOption($name)
	{
		if (array_key_exists($name, $this->options)) {
			return $this->options[$name];
		}

		return null;
	}

	/**
	 * Connect to remote host
	 * 
	 * @throws WireException
	 */
	public function connect()
	{
		if (! $this->ssh) {
			$this->ssh = new Net_SSH2($this->options['host']);
		}

		if ($this->ssh->isConnected()) {
			return;
		}

		$key = $this->options['password'];

		if ($this->options['private_key_path']) {
			if (! is_file($this->options['private_key_path'])) {
				throw new WireException('Private key file doesn\'t exists.');
			}

			$key = new Crypt_RSA();
			$key->setPassword($this->options['password']);

			if (! $key->loadKey(file_get_contents($this->options['private_key_path']))) {
				throw new WireException('Wrong password or invalid private key.');
			}
		}

		if (! $this->ssh->login($this->options['username'], $key)) {
			throw new WireException('Login failed.');
		}

		try {
			$this->checkPwPath();
			$this->tmpDir = $this->pwPath . 'site/assets/tmp/ProcessDeploy/';

			$this->uploadHelper();
		} catch (Exception $e) {
			$this->disconnect();

			throw $e;
		}
	}

	protected function uploadHelper()
	{
		try {
			$this->exec('mkdir -p ' . $this->tmpDir);
			$this->upload(ProcessDeploy::CLASS_PATH, $this->tmpDir . 'ProcessDeploy');
			$this->upload(ProcessDeploy::SCRIPT_PATH . 'deployment_helper.php', $this->tmpDir . 'deployment_helper.php');
		} catch (Exception $e) {
			throw new WireException('Cannot upload helper script.');
		}
	}

	public function disconnect()
	{
		try {
			if ($this->ssh && $this->ssh->isConnected()) {
				if ($this->tmpDir) {
					//$this->exec('rm -r ' . $this->tmpDir);
				}

				$this->ssh->disconnect();
			}

			$this->scp = null;
		} catch (Exception $e) {
			// nothing
		}
	}

	public function getHost()
	{
		return $this->getOption('host');
	}

	public function getDatabaseTables()
	{
		$this->connect();

		$this->exec('php deployment_helper.php action=dbTables', $this->tmpDir, array('PW_PATH' => $this->options['pw_path']));
		$tables = $this->download($this->tmpDir . 'tables.json');
		var_dump($tables);

		return json_decode($tables, true);
	}

	public function getDatabaseTableSQL($table)
	{
		$this->connect();

		$this->exec('php deployment_helper.php action=dbTableExport table=' . $table, $this->tmpDir, array('PW_PATH' => $this->options['pw_path']));
		$sql = $this->download($this->tmpDir . 'export/' . $table . '.sql');
		var_dump($sql);

		return $sql;
	}

	public function databaseExec($sql)
	{
		throw new WireException('Not yet implemented.');
	}

	public function databaseQuery($sql)
	{
		throw new WireException('Not yet implemented.');
	}

	/**
	 * Upload a file or directory to the remote machine
	 *
	 * @param string $localFile
	 * @param string $remoteFile
	 * @return boolean
	 */
	public function upload($localFile, $remoteFile)
	{
		$this->initScp();

		if (is_dir($localFile)) {
			$localDir = rtrim($localFile, '/') . '/';
			$remoteDir = rtrim($remoteFile, '/') . '/';

			$this->exec('mkdir -p ' . $remoteDir);

			$files = array_diff(scandir($localDir), array('.', '..'));
			foreach ($files as $file) {
				$this->upload($localDir . $file, $remoteDir . $file);
			}
		} else {
			return $this->scp->put($remoteFile, $localFile, NET_SCP_LOCAL_FILE);
		}
	}

	/**
	 * Upload a file to the remove machine
	 *
	 * @param string $remoteFile
	 * @param string $localFile If FALSE, only the file's content is downloaded
	 * @return boolean
	 */
	public function download($remoteFile, $localFile = false)
	{
		$this->initScp();

		return $this->scp->get($remoteFile, $localFile);
	}

	public function getFilesMD5($directory, $exclude, $recursive = false, $relativeTo = '')
	{
		throw new WireException('Not implemented yet.');
	}

	/**
	 * Check if $this->pwPath is valid
	 */
	protected function checkPwPath()
	{
		$canonicalPwPath = null;

		try {
			$canonicalPwPath = $this->exec('pwd', $this->pwPath);
			$this->exec('grep -q PROCESSWIRE index.php', $this->pwPath);
		} catch (SSHExecException $e) {
			throw new WireException('There is no ProcessWire site in \'pw_path\' (' . ($canonicalPwPath ?: $this->pwPath) . ')');	
		}
	}

	protected function initScp()
	{
		$this->connect();

		if (! $this->scp) {
			$this->scp = new Net_SCP($this->ssh);
		}
	}

	/**
	 * Execute command on remote machine.
	 *
	 * @param string $command
	 * @param string $currentDirectory Absolute path to the directory where to execute the command
	 * @param array $env Environment variables and its values to be passed to the command
	 * @return string Command's output
	 */
	protected function exec($command, $currentDirectory = null, $env = array())
	{
		if ($env) {
			$assignments = array();
			foreach ($env as $key => $value) {
				$assignments[] = $key . '="' . $value . '"';
			}

			$command = implode(' ', $assignments) . ' ' . $command;
		}

		if ($currentDirectory) {
			$command = 'cd ' . $currentDirectory . ' && ' . $command;
		}

		var_dump($command);

		$output = trim($this->ssh->exec($command));

		var_dump($this->ssh->getExitStatus());
		var_dump($output);

		$exitCode = $this->ssh->getExitStatus();
		if ($exitCode !== 0) {
			throw new SSHExecException($output, $exitCode);
		}

		return $output;
	}
}
