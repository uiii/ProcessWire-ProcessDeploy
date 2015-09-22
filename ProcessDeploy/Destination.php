<?php

namespace ProcessDeploy;

use ProcessDeploy\Connection\ConnectionInterface;

/**
 * Description of Destination
 *
 * @package ProcessDeploy
 */
class Destination
{
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var ConnectionInterface
	 */
	protected $connection;

	public function __construct($id, $title, ConnectionInterface $connection)
	{
		$this->id = $id;
		$this->title = $title;
		$this->connection = $connection;
	}

	public function getId()
	{
		return $this->id;
	}

	public function getTitle()
	{
		return $this->title;
	}

	public function getConnection()
	{
		return $this->connection;
	}

	public function getDatabaseTables()
	{
		return $this->connection->getDatabaseTables();
	}

	public function getDatabaseTableSQL($table)
	{
		return $this->connection->getDatabaseTableSQL($table);
	}

	public function databaseExec($sql)
	{
		return $this->connection->databaseExec($sql);
	}

	public function databaseQuery($sql)
	{
		return $this->connection->databaseQuery($sql);
	}

	public function upload($localFile, $remoteFile)
	{
		return $this->connection->upload($localFile, $remoteFile);
	}

	public function download($remoteFile, $localFile = false)
	{
		return $this->connection->download($remoteFile, $localFile);
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
		return $this->connection->getFilesMD5($directory, $exclude, $recursive);
	}
}
