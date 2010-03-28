<?php

/**
 * Defines the generally used filter, employed by read, delete, filter, and not.
 */
class Grubby_Filter {
    private $filter;
    private $table;
    private $expression = null;
    private $empty = null;
    
    public function __construct($filter) {
        $this->filter = $filter;
    }
    
    /**
     * 
     * @param $table
     * @return unknown_type
     */
    public function setTable($table) {
        $this->table = $table;
    }
    
    /**
     * 
     * @return boolean
     */
    public function emptySet() {
        if ($this->expression === null) {
            $this->buildExpression();
        }
        return $this->empty;
    }
    
    /**
     * 
     * @return string
     */
    public function getExpression() {
        if ($this->expression === null) {
            $this->buildExpression();
        }
        return $this->expression;
    }
    
    /**
     * 
     */
    private function buildExpression() {
        if ($this->filter === false) {
            $this->empty = false;
            $this->expression = 'FALSE';
        } elseif ($this->filter === true) {
            $this->empty = false;
            $this->expression = 'TRUE';
        } elseif (empty($this->filter)) {
            // empty set
            $this->empty = true;
            $this->expression = 'FALSE';
        } elseif (is_array($this->filter)) {
            // expression of field=>value equalities
            $this->empty = false;
            $where = array();
            foreach ($this->filter as $field => $value) {
                if (is_int($field)) {
                    throw new Grubby_Exception('Filter arrays can only contain column=>value pairings.');
                } elseif (is_null($value)) {
                    $where[] = $field.' IS NULL';
                } elseif (is_array($value)) {
                    throw new Grubby_Exception('Array filter values not implemented yet.');
                } else {
                    $where[] = $field.'='.$this->table->formatFieldValue($field, $value);
                }
            }
            $this->expression = implode(' AND ', $where);
        } else {
            // scalar; match against the primary key
            $pk = $this->table->primaryKey();
            if (is_scalar($pk)) {
                $this->expression = $pk.'='.$this->table->formatFieldValue($pk, $this->filter);
            } else {
                throw new Grubby_Exception('Multi-column primary keys cannot use shorthand pk notation.');
            }
            $this->empty = false;
        }
    }
}
