<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ProcessDeploy;

use Config;
use ProcessDeploy\Connection\Local;
use ProcessDeploy\Connection\SSH;
use WireDatabasePDO;
use WireException;

/**
 * Description of DestinationFactory
 *
 * @package ProcessDeploy
 */
class DestinationFactory
{
	protected $database;
	protected $config;

	public function __construct(WireDatabasePDO $database, Config $config)
	{
		$this->database = $database;
		$this->config = $config;
	}

	public function create($options)
	{
		$id = isset($options['id']) ? $options['id'] : null;
		$title = isset($options['title']) ? $options['title'] : null;
		$connectionOptions = isset($options['connection']) ? $options['connection'] : array('type' => null);

		$connection = null;
		switch($connectionOptions['type']) {
			case 'ssh':
				$connection = new SSH($connectionOptions);
			break;
			case 'local':
				$connection = new Local($this->database, $this->config, $connectionOptions);
			break;
			default:
				throw new WireException('Unsupported connection type \'' . $connectionOptions['type'] . '\'');
			break;
		}

		return new Destination($id, $title, $connection);
	}
}
