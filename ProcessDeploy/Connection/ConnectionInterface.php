<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ProcessDeploy\Connection;

/**
 * Description of ConnectionInterface
 *
 * @package ProcessDeploy
 * @subpackage Connection
 */
interface ConnectionInterface
{
	public function connect();
	public function disconnect();

	public function getType();
	public function getHost();

	public function getDatabaseTables();
	public function getDatabaseTableSQL($table);

	public function databaseExec($sql);
	public function databaseQuery($sql);

	public function upload($localFile, $remoteFile);
	public function download($remoteFile, $localFile = false);

	/**
	 * Calculate MD5 hash for files in the specified directory.
	 * 
	 * @param string $directory Directory path relative to PW's root
	 * @param string $exclude Array of paths to be excluded
	 * @param boolean $recursive If TRUE, the directory is scanned recursively
	 * @return array Associative array [filename] => [MD5 hash]
	 */
	public function getFilesMD5($directory, $exclude, $recursive = false);
}
