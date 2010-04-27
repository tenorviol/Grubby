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

abstract class AbstractRecord implements IteratorAggregate {
	
	// does this record exist on the permanent media?
	private $exists = false;
	
	// has this record already been loaded from the permanent media?
	private $loaded = false;
	
	// the data in this record
	private $data = array();
	
	// modified fields that have not yet been stored to the permanent media
	private $dirty = null;
	
	public abstract function id();
	
	/**
	 * Load this record's data from the data source.
	 * @return success/failure
	 */
	public function load() {
		$data = $this->readData();
		if ($data === false) {
			$this->loaded = false;
			$this->exists = false;
		} elseif ($data === null) {
			$this->loaded = true;
			$this->exists = false;
		} else {
			foreach ($data as $key => $value) {
				if (!isset($this->dirty[$key])) {
					$this->data[$key] = $value;
				}
			}
		}
		return $this->loaded;
	}
	
	/**
	 * Save all dirty data to the permanent record.
	 * @return success/failure
	 */
	public function save() {
		if (empty($this->dirty)) {
			return false;
		}
		if ($return = $this->writeData($this->dirty)) {
			$this->exists = true;
			$this->dirty = null;
		}
		return $return;
	}
	
	/**
	 * Delete from the permanent record.
	 */
	//public abstract function delete();
	
	public function exists() {
		$this->loadOnce();
		return $this->exists;
	}
	
	/**
	 * Read from the permanent record.
	 * 
	 * Return the associative array on success.
	 * Return null if the record doesn't exist.
	 * Return false if the read fails.
	 * @param mixed $fields null|false=easy fields, true=all fields, array=selected fields (+easies)
	 * @param array $exclude_fields unneeded fields
	 * @return array|null|false
	 */
	protected abstract function readData($require_fields = null, array $exclude_fields = null);
	
	/**
	 * Write to the permanent record.
	 * 
	 * @param array $dirty_fields those that need saving
	 * @return boolean success/failure
	 */
	protected abstract function writeData(array $dirty_fields);
	
	/**
	 * For use by external functions instantiating records in bulk.
	 * I.e. run sql query, fetch rows, instantiate records, setData().
	 */
	public function setData(array $data, $loaded = true, $clean = true) {
		$this->exists = true;
		if ($this->loaded) {
			$this->loaded = true;
		}
		$this->data = $data;
		if ($clean) {
			$this->dirty = null;
		}
	}
	
	/**
	 * Load this record's data, but only once.
	 */
	public function loadOnce() {
		if (!$this->loaded) {
			$this->load();
		}
	}
	
	/**
	 * Write a record property.
	 */
	public function __set($name, $value) {
		$this->data[$name] = $value;
		$this->dirty[$name] = true;
	}
	
	/**
	 * Read a record property.
	 */
	public function __get($name) {
		return $this->data[$name];
	}
	
	/**
	 * Check on a record property.
	 */
	public function __isset($name) {
		$this->loadOnce();
		return isset($this->data[$name]);
	}
	
	/**
	 * Remove a record property.
	 */
	public function __unset($name) {
		unset($this->data[$name]);
		$this->dirty[$name] = true;
	}
	
	/**
	 * Making this record usable in foreach statements.
	 * 
	 * @return Iterator
	 */
	public function getIterator() {
		$fields = array_keys($this->data);
		return new AbstractRecord_Iterator($this, $fields);
	}
	
	/**
	 * Convert this record to an array.
	 * 
	 * @return array
	 */
	public function toArray() {
		$this->loadOnce();
		return $this->data;
	}
}

class AbstractRecord_Iterator implements Iterator {
	private $record;
	private $fields;
	private $field_count;
	private $marker = 0;
	
	public function __construct($record, $fields) {
		$this->record = $record;
		$this->fields = $fields;
		$this->field_count = count($fields);
	}
	
	/**
	 * @return mixed
	 */
	public function current() {
		$field = $this->fields[$this->marker];
		return $this->record->$field;
	}
	
	/**
	 * @return scalar
	 */
	public function key() {
		return $this->fields[$this->marker];
	}
	
	/**
	 * 
	 */
	public function next() {
		$this->marker++;
	}
	
	/**
	 * 
	 */
	public function rewind() {
		$this->marker = 0;
	}
	
	/**
	 * @return boolean
	 */
	public function valid() {
		return $this->marker < $this->field_count;
	}
}
