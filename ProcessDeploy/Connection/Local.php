<?php

namespace ProcessDeploy\Connection;

use Config;
use WireDatabaseBackup;
use WireDatabasePDO;
use WireException;

/**
 * Description of Local
 *
 * @package ProcessDeploy
 * @subpackage Connection
 */
class Local implements ConnectionInterface
{
	/**
	 * @var WireDatabasePDO
	 */
	protected $database;

	/**
	 * @var string
	 */
	protected $pwPath;

	/**
	 * Constructor.
	 * 
	 * @param WireDatabasePDO $localDatabase Database used in the PW's instance currently running
	 * @param Config $localConfig Config of the PW's instance currently running
	 * @param array $options
	 */
	public function __construct($localDatabase, $localConfig, $options)
	{
		$this->initPwPath($localConfig, $options);
		$this->initDatabase($localDatabase, $localConfig);
	}

	public function getType()
	{
		return 'local';
	}

	public function getOption($name)
	{
		if ($name === 'pw_path') {
			return $this->pwPath;
		}

		return null;
	}

	public function connect()
	{
	}

	public function disconnect()
	{
	}

	public function getHost()
	{
		return 'localhost';
	}

	public function getDatabaseTables()
	{
		return $this->database->getTables();
	}

	public function getDatabaseTableSQL($table)
	{
		$exportPath = wireTempDir('ProcessDeploy');

		wireMkdir($exportPath, true, '0755');

		$backup = new WireDatabaseBackup($exportPath);
		$backup->setDatabase($this->database);

		$backup->setBackupOptions(array(
			'allowDrop' => false,
		));

		$file = $backup->backup(array(
			'filename' => $table . '.sql',
			'tables' => array($table),
		));

		if (! $file) {
			return null;
		}

		$sql = file_get_contents($file);

		@unlink($file);

		return $sql;
	}

	public function databaseExec($sql)
	{
		return $this->database->exec($sql);
	}

	public function databaseQuery($sql, $params = array())
	{
		$query = $this->database->prepare($sql);
		$query->execute($params);
		return $query;
	}

	public function upload($localFile, $remoteFile)
	{
		throw new WireException('Not yet implemented.');	
	}

	public function download($remoteFile, $localFile = false)
	{
		throw new WireException('Not yet implemented.');	
	}

	/**
	 * Calculate MD5 hash for files in the specified directory.
	 * 
	 * @param string $directory Directory path relative to PW's root
	 * @param string $exclude Array of paths to be excluded
	 * @param boolean $recursive If TRUE, the directory is scanned recursively
	 * @return array Associative array [filename] => [MD5 hash]
	 */
	public function getFilesMD5($directory, $exclude, $recursive = false)
	{
		$directory = rtrim($directory, '/') . '/'; // trailing slash fix
		$absolutePath = $this->pwPath . $directory;

		$files = array();

		$directoryFiles = scandir($absolutePath);
		foreach ($directoryFiles as $file) {
			$isDot = in_array($file, array('.', '..'));
			$isExcluded = in_array($directory . $file, $exclude);

			if ($isDot || $isExcluded) {
				continue;
			}

			if (is_dir($absolutePath . $file) && $recursive) {
				$files += $this->getFilesMD5($directory . $file, $exclude, true);
			} else {
				$files[$directory . $file] = md5_file($absolutePath);
			}
		}

		return $files;
	}

	protected function initPwPath($localConfig, $options)
	{
		if (isset($options['pw_path'])) {
			$this->pwPath = rtrim($options['pw_path'], '/') . '/'; // trailing slash fix

			// check if $this->pwPath is valid
			$pwIndexFile = $this->pwPath . 'index.php';
			if (! is_file($pwIndexFile) || strpos(file_get_contents($pwIndexFile), 'PROCESSWIRE') === false) {
				throw new WireException('There is no ProcessWire site in \'pw_path\' (' . $this->pwPath . ')');	
			}
		} else {
			$this->pwPath = $localConfig->paths->root;
		}
	}

	protected function initDatabase($localDatabase, $localConfig)
	{
		if ($this->database) {
			return;
		}

		$this->database = $localDatabase;

		if ($this->pwPath) {
			$config = new Config();
			include $this->pwPath . 'site/config.php';

			if (
				$config->dbName != $localConfig->dbName
				|| $config->dbHost != $localConfig->dbHost
				|| $config->dbUser != $localConfig->dbUser
				|| $config->dbPass != $localConfig->dbPass
			) {
				$this->database = WireDatabasePDO::getInstance($config);
			}
		}
	}
}
