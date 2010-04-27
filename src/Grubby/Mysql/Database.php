<?php
/**
 * Grubby : Quick and dirty CRUD operations
 * http://grubbycrud.com/
 * 
 * Version: @version@
 * Date: @date@
 * 
 * Copyright (c) @year@ Christopher Johnson
 * Licensed under the MIT license (see LICENSE file).
 */

/**
 * Grubby database using the DB abstraction layer
 */
class Grubby_Mysql_Database extends Grubby_Database {
	private $server;
	private $username;
	private $password;
	private $database;
	private $client_flags;
	private $connection = null;
	
	/**
	 * Creates a new Grubby database.
	 */
	public function __construct($server, $username, $password, $database, $client_flags = 0) {
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->client_flags = $client_flags;
	}
	
	/**
	 * Returns a database connection.
	 */
	public function getConnection() {
		if ($this->connection === null) {
			$this->connection = mysql_connect($this->server, $this->username, $this->password, $this->client_flags);
			mysql_select_db($this->database, $this->connection);
		}
		return $this->connection;
	}
	
	/**
	 * Executes a SQL query against the database.
	 * Use for UPDATE, INSERT, DELETE.
	 * @return Grubby_DB_Result
	 */
	public function executeImpl($sql) {
		$connection = $this->getConnection();
		$result = mysql_query($sql, $connection);
		if (!$result) {
			throw new Grubby_Exception(mysql_error($connection));
		}
		return new Grubby_Mysql_Result($connection, $result);
	}
	
	/**
	 * Runs a SQL query against the database.
	 * Use for SELECT.
	 * @return false or a new DB_Result object.
	 */
	public function queryImpl($sql) {
		$connection = $this->getConnection();
		$result = mysql_query($sql, $connection);
		if (!$result) {
			throw new Grubby_Exception(mysql_error($connection));
		}
		return new Grubby_Mysql_Recordset($this, $result);
	}
	
	public function formatString($s) {
		if ($s === null) {
			return 'NULL';
		} elseif ($s) {
			return "'".mysql_real_escape_string($s, $this->getConnection())."'";
		} else {
			return "''";
		}
	}
}
