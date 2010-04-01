<?php

/**
 * 
 */
class Grubby_DB_Recordset extends Grubby_Recordset {
    
    /**
     * Creates a new recordset resource handler.
     * @param result mixed result of a call to DB::query
     */
    public function __construct($database, $result) {
        $this->database = $database;
        if ($result instanceof DB_Error) {
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
        $row = $this->recordset->fetchRow(DB_FETCHMODE_ASSOC);
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
        while ($row = $this->recordset->fetchRow(DB_FETCHMODE_ASSOC)) {
            $all[] = $row;
        }
        if ($this->object_type) {
            foreach ($all as $key => $row) {
                $all[$key] = $this->unmarshal($row);
            }
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
