<?php

/**
 * 
 */
class Grubby_Mysql_Recordset extends Grubby_Recordset {
    
    /**
     * Creates a new recordset resource handler.
     * @param result mixed result of a call to DB::query
     */
    public function __construct($database, $result) {
        $this->database = $database;
        $this->recordset = $result;
    }
    
    /**
     * Returns the next row in the resultset or null if eof has been reached.
     * @return mixed
     */
    public function fetch() {
        $row = mysql_fetch_assoc($this->recordset);
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
        $all = array();
        while ($row = mysql_fetch_assoc($this->recordset)) {
            $all[] = $row;
        }
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
        return '';
    }
}
