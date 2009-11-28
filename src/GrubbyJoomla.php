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

require_once 'Grubby.php';

/**
 * Grubby database using Joomla's JDatabase abstraction layer
 */
class GrubbyJoomla extends GrubbyDatabase {
    private $options;
    private $jdatabase;
    
    public $time;
    
    /**
     * Creates a new Grubby database.
     */
    public function __construct($options = null) {
        $this->options = $options;
    }
    
    private function getJDatabase() {
        if (!$this->jdatabase) {
            if ($this->options) {
                $this->jdatabase = JDatabase::getInstance($this->options);
            } else {
                $this->jdatabase = JFactory::getDBO();
            }
        }
        return $this->jdatabase;
    }
    
    /**
     * Executes a SQL query against the database.
     * Use for UPDATE, INSERT, DELETE.
     * @return GrubbyDBResult
     */
    public function execute($sql) {
        if (Grubby::$debug) {
            Grubby::debugMessage('Executing: '.$sql);
        }
        
        $start = microtime(true);
        
        $jdatabase = $this->getJDatabase();
        $jdatabase->setQuery($sql);
        $result = $jdatabase->query();
        
        $time = microtime(true) - $start;
        $this->time += $time;
        Grubby::$time += $time;
        
        if (Grubby::$debug) {
            if ($result === false) {
                Grubby::debugMessage('Database error');  // TODO: more specific message please
            }
            Grubby::debugMessage('Time: '.number_format($time, 4)." secs");
        }
        
        if ($jdatabase->getErrorNum()) {
            throw new GrubbyException('Error: '.$jdatabase->getErrorMsg());
        }
        
        return new GrubbyJoomlaResult($result, $jdatabase);
    }
    
    /**
     * Runs a SQL query against the database.
     * Use for SELECT.
     * @return false or a new DB_Result object.
     */
    public function query($sql) {
        if (Grubby::$debug) {
            Grubby::debugMessage('Querying: '.$sql);
        }
        
        $start = microtime(true);
        
        $jdatabase = $this->getJDatabase();
        $jdatabase->setQuery($sql);
        $result = $jdatabase->query();
        
        $time = microtime(true) - $start;
        $this->time += $time;
        Grubby::$time += $time;
        
        if (Grubby::$debug) {
            if ($result === false) {
                Grubby::debugMessage('Database error');  // TODO: more specific message please
            }
            Grubby::debugMessage('Time: '.number_format($time, 4)." secs");
        }
        
        if ($jdatabase->getErrorNum()) {
            throw new GrubbyException('Error: '.$jdatabase->getErrorMsg());
        }
                
        return new GrubbyJoomlaRecordset($this, $jdatabase);
    }
    
    public function lastInsertID() {
        return $this->jdatabase->insertid();
    }
    
    public function formatString($s) {
        if (is_null($s)) {
            return 'NULL';
        } elseif ($s) {
            return $this->getJDatabase()->quote(strval($s));
        } else {
            return "''";
        }
    }
}

/**
 * Result object returned by a GrubbyDBDatabase execute function call.
 */
class GrubbyJoomlaResult extends GrubbyResult {
    
    /**
     * Creates an DB execute result object.
     * @param result mixed result of a call to DB::exec
     */
    public function __construct($result, $jdatabase) {
        $this->affected_rows = $jdatabase->getAffectedRows();
        $this->insert_id = $jdatabase->insertid();
    }
}

/**
 * 
 */
class GrubbyJoomlaRecordset extends GrubbyRecordset {
    
    /**
     * Creates a new recordset resource handler.
     * @param result mixed result of a call to DB::query
     */
    public function __construct($database, $jdatabase) {
        $this->database = $database;
        $this->result = $jdatabase->loadAssocList();
        $this->count = count($this->result);
        $this->cursor = 0;
    }
    
    /**
     * Returns the next row in the resultset or null if eof has been reached.
     * @return mixed
     */
    public function fetch() {
        if ($this->cursor < $this->count) {
            $row = $this->result[$this->cursor];
            $this->cursor++;
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
        $all = $this->result;
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
        return $this->recordset->loadResultArray($column);
    }
}
