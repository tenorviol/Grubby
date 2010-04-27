<?php

/**
 * Read returns a recordset.
 */
abstract class Grubby_Recordset {
    private $factory = null;
    
    public function setRecordFactory($factory) {
    	$this->factory = $factory;
    }
    
    /**
     * 
     * @param $result
     * @return unknown_type
     */
    protected function unmarshal($result) {
        if ($this->factory) {
        	$factory = $this->factory;
            $object = $factory($result);
            return $object;
        } else {
            return $result;
        }
    }
    
    /**
     * Returns the next row of the result set.
     * When there are no more rows, returns null.
     */
    public abstract function fetch();
    
    /**
     * Returns all rows as a two dimensional array.
     */
    public abstract function fetchAll();
    
    /**
     * Returns the values of a single column as an ordered array.
     */
    public abstract function fetchColumn($column);
    
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
