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
 * Result object returned by a GrubbyDBDatabase execute function call.
 */
class Grubby_Mysql_Result extends Grubby_Result {
	
	/**
	 * Creates an DB execute result object.
	 * @param result mixed result of a call to DB::exec
	 */
	public function __construct($connection, $result) {
		$this->affected_rows = mysql_affected_rows($connection);
	}
	
	/**
	 * True if the query resulted in an error.
	 * @return boolean
	 */
	public function error() {
		return '';
	}
	
	/**
	 * Available when error() is true.
	 * @return string
	 */
	public function errorMessage() {
		return $this->error->getMessage();
	}
}
