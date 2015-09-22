<?php

namespace ProcessDeploy;

use ProcessDeploy;

/**
 * Description of DatabaseDiff
 *
 * @package ProcessDeploy
 */
class Diff
{
	protected $local;
	protected $remote;

	/**
	 * @var array
	 */
	protected $newLocalTables;

	/**
	 * @var array
	 */
	protected $newRemoteTables;

	/**
	 * @var TableDiffs[]
	 */
	protected $tableDiffs;

	public function __construct(Destination $local, Destination $remote)
	{
		$this->local = $local;
		$this->remote = $remote;

		$this->performDiff();
	}

	public function getNewLocalTables()
	{
		return $this->newLocalTables;
	}

	public function getNewRemoteTables()
	{
		return $this->newRemoteTables;
	}

	/**
	 * @return TableDiff[]
	 */
	public function getTableDiffs()
	{
		return $this->tableDiffs;
	}

	protected function performDiff()
	{
		$localTables = $this->local->getDatabaseTables();
		$remoteTables = $this->remote->getDatabaseTables();

		//var_dump($localTables);
		//var_dump($remoteTables);

		$this->newLocalTables = array_diff($localTables, $remoteTables);
		$this->newRemoteTables = array_diff($remoteTables, $localTables);

		$commonTables = array_intersect($localTables, $remoteTables);
		$commonTables = array('templates');

		$this->tableDiffs = array();
		foreach ($commonTables as $table) {
			$this->tableDiffs[$table] = new TableDiff($this->local, $this->remote, $table);
		}
	}
}
