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
 * GrubbyMDB2 : Grubby database using the MDB2 abstraction layer
 */
class Grubby_PDO_Database extends Grubby_Database {
	private $dsn;
	private $username;
	private $password;
	private $options;
	private $pdo = null;
	
	/**
	 * Creates a new Grubby database.
	 */
	public function __construct($dsn, $username, $password, $options = null) {
		$this->dsn	  = $dsn;
		$this->username = $username;
		$this->password = $password;
		$this->options  = $options;
	}
	
	/**
	 * Returns a database connection.
	 */
	public function getConnection() {
		if ($this->pdo === null) {
			$this->pdo = new PDO($this->dsn, $this->username, $this->password, $this->options);
		}
		return $this->pdo;
	}
	
	/**
	 * Executes a SQL query against the database.
	 * Use for UPDATE, INSERT, DELETE.
	 * @return Grubby_MDB2_Result
	 */
	public function executeImpl($sql) {
		$pdo = $this->getConnection();
		$result = $pdo->exec($sql);
		if ($result === false) {
			$info = $pdo->errorInfo();
			throw new Grubby_Exception(json_encode($info));
					}
		return new Grubby_PDO_Result($result);
	}
	
	/**
	 * Runs a SQL query against the database.
	 * Use for SELECT.
	 * @return false or a new MDB2_Result object.
	 */
	public function queryImpl($sql) {
		$pdo = $this->getConnection();
		$result = $pdo->query($sql);
		if ($result === false) {
			$info = $pdo->errorInfo();
			throw new Grubby_Exception(json_encode($info));
		}
		return new Grubby_PDO_Recordset($result);
	}
	
	public function lastInsertID() {
		return $this->getConnection()->lastInsertId();
	}
	
	public function formatString($s) {
		if (is_null($s)) {
			return 'NULL';
		} elseif ($s) {
			return $this->getConnection()->quote($s, 'text');
		} else {
			return "''";
		}
	}
}
