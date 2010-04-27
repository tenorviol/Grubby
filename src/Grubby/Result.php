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
 * Create, update and delete returns a result.
 */
abstract class Grubby_Result {
	/**
	 * True if the query resulted in an error.
	 * @return boolean
	 */
	public abstract function error();
	
	/**
	 * Available when error() is true.
	 * @return string
	 */
	public abstract function errorMessage();
}
