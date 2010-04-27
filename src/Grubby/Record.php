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
 * TODO: 4 spaces to tabs
 * TODO: change set object to a factory
 * TODO: integrate bug fixes in "my" project
 */

class Grubby_Record extends AbstractRecord {
	
	private $query;
	
	public function __construct($query, $data = array()) {
		$this->query = $query;
		if (is_scalar($data)) {
			$pk = $this->query->primaryKey();
			$data = array($pk => $data);
		}
		$this->setData($data);
	}
	
	public function id() {
		$pk = $this->query->primaryKey();
		return isset($this->$pk) ? $this->$pk : null;
	}
	
	protected function readData($require_fields = null, array $exclude_fields = null) {
		return $this->query->read($this->id());
	}
	
	/**
	 * Creates or updates this object using the grubbyQuery().
	 * @throws GrubbyException if a create/update error occurs
	 */
	protected function writeData(array $dirty_fields) {
		$data = array_intersect_key($this->toArray(), $dirty_fields);
		foreach ($dirty_fields as $field => $dirty) {
			if (!isset($data[$field])) {
				$data[$field] = null;
			}
		}
		
		$pk = $this->query->primaryKey();
		
		if (!empty($this->$pk)) {
			$data[$pk] = $this->$pk;
			$result = $this->query->update($data);
			if ($result->affected_rows == 1) {
				return true;
			}
		}
		
		// if updating did not do anything, try inserting
		$result = $this->query->create($data);
		if (empty($this->$pk)) {
			$this->$pk = $result->insert_id;
		}
		
		// something strange happened, throw an exception
		if (!$result->affected_rows == 1) {
			$this->query->log('Attempted to update and insert the object, but the database returned 0 affected rows.', LOG_ERR);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Removes the row in the grubbyQuery() corresponding to this object's primary key value.
	 * @return boolean removed one row vs. nothing removed
	 * @throws GrubbyException if a delete error occurs
	 */
	public function delete() {
		$this->id();
		$result = $this->query->delete($this->id());
		return $result->affected_rows == 1;
	}
}
