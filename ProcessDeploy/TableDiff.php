<?php

namespace ProcessDeploy;

use PDO;
use ProcessDeploy;

/**
 * Description of TableDiff
 *
 * @package ProcessDeploy
 */
class TableDiff
{
	protected $local;
	protected $remote;

	protected $table;
	protected $primaryKeys;

	protected $errors;

	protected $newLocalRecords;
	protected $newRemoteRecords;
	protected $recordDiffs;

	public function __construct(Destination $localDestination, Destination $remoteDestination, $tableName)
	{
		$this->local = $localDestination;
		$this->remote = $remoteDestination;
		$this->table = $tableName;
		$this->errors = array();

		$this->performDiff();
	}

	public function getTable()
	{
		return $this->table;
	}

	public function getErrors()
	{
		return $this->errors;
	}

	public function getNewLocalRecords()
	{
		return $this->newLocalRecords;
	}

	public function getNewRemoteRecords()
	{
		return $this->newRemoteRecords;
	}

	public function getRecordDiffs()
	{
		return $this->recordDiffs;
	}

	protected function performDiff()
	{
		$sql = $this->remote->getDatabaseTableSQL($this->table);

		$sql = preg_replace('/(^|\n)--[^\n]*(\n|$)/', '', $sql); // remove comments
		$sql = preg_replace('/(CREATE TABLE) `[^`]+`/', sprintf('\1 `%s`', ProcessDeploy::REMOTE_TABLE), $sql);
		$sql = preg_replace('/(INSERT INTO) `[^`]+`/', sprintf('\1 `%s`', ProcessDeploy::REMOTE_TABLE), $sql);

		echo nl2br($sql);

		$this->local->databaseExec('DROP TABLE IF EXISTS ' . ProcessDeploy::REMOTE_TABLE);
		$this->local->databaseExec($sql);

		$localKeys = $this->_getPrimaryKey($this->table);
		$remoteKeys = $this->_getPrimaryKey(ProcessDeploy::REMOTE_TABLE);

		if ($localKeys != $remoteKeys) {
			$this->errors[] = 'Incompatible tables: different primary keys';
			return;
		}

		$this->primaryKeys = $localKeys;

		$this->newLocalRecords = $this->_getNewRecords($this->table, ProcessDeploy::REMOTE_TABLE);
		$this->newRemoteRecords = $this->_getNewRecords(ProcessDeploy::REMOTE_TABLE, $this->table);
		$this->recordDiffs = $this->_getRecordDiffs($this->table, ProcessDeploy::REMOTE_TABLE);
	}

	protected function _getNewRecords($inTable, $againstTable)
	{
		$joinOn = array();
		$where = array();
		foreach ($this->primaryKeys as $key) {
			$joinOn[] = sprintf('`%s`.`%s` = `%s`.`%s`', $inTable, $key, $againstTable, $key);
			$where[] = sprintf('`%s`.`%s` IS NULL', $againstTable, $key);
		}

		$sql = sprintf(
			'SELECT %s.* FROM %s LEFT JOIN %s ON %s WHERE %s',
			$inTable,
			$inTable,
			$againstTable, 
			implode(' AND ', $joinOn),
			implode(' AND ', $where)
		);

		return $this->local->databaseQuery($sql)->fetchAll(PDO::FETCH_NAMED);
	}

	protected function _getRecordDiffs($leftTable, $rightTable)
	{
		$leftColums = array_keys($this->_getColumns($leftTable));
		$rightColumns = array_keys($this->_getColumns($rightTable));

		/*$commonColumns = array_diff(array_intersect($leftColums, $rightColumns), $this->primaryKeys); // get common columns without primary keys

		$select = array();
		$joinOn = array();
		foreach ($this->primaryKeys as $key) {
			$select[] = sprintf('`%s`.`%s`', $leftTable, $key);
			$joinOn[] = sprintf('`%s`.`%s` = `%s`.`%s`', $leftTable, $key, $rightTable, $key);
		}

		$where = array();
		foreach($commonColumns as $column) {
			$select[] = sprintf('NULLIF(`%s`.`%s`, `%s`.`%s`) as %s', $leftTable, $column, $rightTable, $column, $column);
			$select[] = sprintf('NULLIF(`%s`.`%s`, `%s`.`%s`) as %s', $rightTable, $column, $leftTable, $column, $column);
			$where[] = sprintf('IFNULL(`%s`.`%s`, \'\') <> IFNULL(`%s`.`%s`, \'\')', $leftTable, $column, $rightTable, $column);
		}

		$sql = sprintf(
			'SELECT %s FROM %s INNER JOIN %s ON %s WHERE %s',
			implode(', ', $select),
			$leftTable,
			$rightTable,
			implode(' AND ', $joinOn),
			implode(' OR ', $where)
		);*/

		$commonColumns = array_intersect($leftColums, $rightColumns);

		$pkSelect = array();
		$joinLeftOn = array();
		$joinRightOn = array();
		foreach ($this->primaryKeys as $key) {
			$pkSelect[] = sprintf('`%s`', $key);
			$joinLeftOn[] = sprintf('`%s`.`%s` = `pk`.`%s`', $leftTable, $key, $key);
			$joinRightOn[] = sprintf('`%s`.`%s` = `pk`.`%s`', $rightTable, $key, $key);
		}

		$select = array();
		$where = array();
		foreach($commonColumns as $column) {
			$select[] = sprintf('`%s`.`%s` as %s', $leftTable, $column, $column);
			$select[] = sprintf('`%s`.`%s` as %s', $rightTable, $column, $column);
			$where[] = sprintf('IFNULL(`%s`.`%s`, \'\') <> IFNULL(`%s`.`%s`, \'\')', $leftTable, $column, $rightTable, $column);
		}

		$pkSql = sprintf(
			'SELECT %s FROM %s UNION DISTINCT SELECT %s FROM %s',
			implode(', ', $pkSelect),
			$leftTable,
			implode(', ', $pkSelect),
			$rightTable
		);

		$sql = sprintf('SELECT %s ', implode(', ', $select)) .
			sprintf('FROM (%s) as pk ', $pkSql) .
			sprintf('LEFT JOIN %s ON %s ', $leftTable, implode(' AND ', $joinLeftOn)) .
			sprintf('LEFT JOIN %s ON %s ', $rightTable, implode(' AND ', $joinRightOn)) .
			sprintf('WHERE %s ', implode(' OR ', $where)) .
			sprintf('ORDER BY %s.`name` ', $leftTable);

		echo $sql;

		return $this->local->databaseQuery($sql)->fetchAll(PDO::FETCH_NAMED);
	}

	protected function _getPrimaryKey($table)
	{
		$rows = $this->local->databaseQuery('SHOW KEYS FROM ' . $table . ' WHERE Key_name = \'PRIMARY\'')->fetchAll();
		
		$keys = array_map(function($row) {
			return $row['Column_name'];
		}, $rows);

		sort($keys, SORT_STRING);

		return $keys;
	}

	protected function _getColumns($table)
	{
		$rows = $this->local->databaseQuery('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);

		$columns = array();
		foreach ($rows as $row) {
			$columns[$row['Field']] = $row;
		}

		return $columns;
	}
}
