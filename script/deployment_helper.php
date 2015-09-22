<?php

use ProcessDeploy\Connection\Local;
use ProcessDeploy\Destination;

class Helper
{
	/**
	 * @param string $pwPath
	 */
	protected $pwPath;

	/**
	 * @param string $tmpDir
	 */
	protected $tmpDir;

	/**
	 * @var ProcessWire
	 */
	protected $wire;

	/**
	 * @param Destination $local
	 */
	protected $local;

	public function __construct($pwPath)
	{
		$pwPath = rtrim($pwPath, '/') . '/'; // trailing slash fix
		
		include $pwPath . 'index.php';

		$this->wire = $wire;
		$this->pwPath = $this->wire->config->paths->root;
		$this->tmpDir = $this->pwPath . 'site/assets/tmp/ProcessDeploy/';

		spl_autoload_register(function($classname) {
			$classname = ltrim($classname, '\\');
			$filename = sprintf($this->tmpDir . '%s.php', str_replace('\\', DIRECTORY_SEPARATOR, $classname));

			if (is_file($filename)) {
				require_once $filename;
			}
		});

		$localConnection = new Local($this->wire->database, $this->wire->config, array(
			'pw_path' => $this->pwPath
		));

		$this->local = new Destination(0, 'local', $localConnection);
	}

	public function run()
	{
		try {
			$params = $this->parseParams();
			$this->checkKey($params['key']);

			$method = 'get' . ucfirst($params['action']);
			if (method_exists($this, $method)) {
				call_user_func(array($this, $method), $params);
			}
		} catch (Exception $e) {
			if ($this->wire->config->cli) {
				exit(1);
			} else {
				header('HTTP/1.0 404 Not Found');
				echo "<h1>404 Not Found</h1>";
			}
		}
	}

	public function getFilesMD5()
	{
		$exclude = array(
			'site/config.php',
			'site/config-dev.php',
			'site/install',
			'site/modules',
			'site/assets/cache',
			'site/assets/logs',
			'site/assets/sessions',
			'site/assets/backups',
			'site/assets/tmp',
			'site/assets/install',
		);

		return $this->local->getFilesMD5('site', $exclude, true);
	}

	public function getDbTables($params)
	{
		$tables = json_encode($this->local->getDatabaseTables());
		file_put_contents($this->tmpDir . 'tables.json', $tables);
	}

	public function getDbTableExport($params)
	{
		if (! isset($params['table'])) {
			throw new WireException('No table specified');
		}

		$exportPath = $this->tmpDir . 'export/';
		wireMkdir($exportPath, true, '0755');

		$table = $params['table'];

		$sql = $this->local->getDatabaseTableSQL($table);
		file_put_contents($exportPath . $table . '.sql', $sql);
	}

	protected function parseParams()
	{
		$params = array(
			'key' => null,
			'action' => null
		);

		if ($this->wire->config->cli) {
			global $argc;
			global $argv;

			for ($i = 1; $i < $argc; ++$i) {
				list($param, $value) = explode('=', $argv[$i] . '=');
				$params[$param] = $value;
			}
		} else {
			$params = array_merge($params, $_GET);
		}

		return $params;
	}

	protected function checkKey($key)
	{
		return;
		$keyFile = $this->tmpDir . 'key';
		if (! is_file($keyFile) || trim(file_get_contents($keyFile)) !== $key) {
			throw new WireException("Invalid key");
		}
	}
}

$helper = new Helper(getenv('PW_PATH') ?: __DIR__);
$helper->run();