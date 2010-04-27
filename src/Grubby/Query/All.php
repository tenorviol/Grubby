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
 * 
 */
class Grubby_Query_All extends Grubby_Query {
	
	/**
	 * Allows bulk updates of a database table.
	 * @return GrubbyResult
	 */
	public function update($data) {
		return $this->crudImpl(array('update'=>$data, 'all'=>true));
	}
	
	/**
	 * Allows bulk deletes from the database table.
	 * @return GrubbyResult
	 */
	public function delete($filter = true) {
		return $this->crudImpl(array('delete'=>$filter, 'all'=>true));
	}
}
