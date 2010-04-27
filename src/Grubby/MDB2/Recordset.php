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

/*
 * 
 */
class Grubby_MDB2_Recordset extends Grubby_Recordset {
	
	/**
	 * Creates a new recordset resource handler.
	 * @param result mixed result of a call to MDB2::query
	 */
	public function __construct($result) {
		if ($result instanceof MDB2_Error) {
			$this->error = $result;
		} else {
			$this->recordset = $result;
		}
	}
	
	/**
	 * Returns the next row in the resultset or null if eof has been reached.
	 * @return mixed
	 */
	public function fetch() {
		$row = $this->recordset->fetchRow(MDB2_FETCHMODE_ASSOC);
		if ($row) {
			return $this->unmarshal($row);
		} else {
			return null;
		}
	}
	
	/**
	 * Returns all remaining rows in the result set in an ordered array.
	 * @return array
	 */
	public function fetchAll() {
		$all = $this->recordset->fetchAll(MDB2_FETCHMODE_ASSOC);
		foreach ($all as $key => $row) {
			$all[$key] = $this->unmarshal($row);
		}
		return $all;
	}
	
	/**
	 * Returns all members of a particular column of the result set as an ordered array.
	 * @return array
	 */
	public function fetchColumn($column) {
		return $this->recordset->fetchCol($column);
	}
	
	/**
	 * True if the query resulted in an error.
	 * @return boolean
	 */
	public function error() {
		return isset($this->error);
	}
	
	/**
	 * Available when error() is true.
	 * @return string
	 */
	public function errorMessage() {
		return $this->error->getMessage();
	}
}
