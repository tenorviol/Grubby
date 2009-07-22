<?php
/**
 * GrubbyMDB2 : Grubby database using the MDB2 abstraction layer
 * 
 * Version 0.1
 * Copyright (c) 2009 Christopher Johnson
 */

require_once 'Grubby.php';
require_once 'MDB2.php';

class GrubbyMDB2 extends GrubbyDatabase {
    private $dsn;
    private $options;
    
    /**
     * Creates a new Grubby database.
     */
    public function __construct($dsn, $options = null) {
        $this->dsn     = $dsn;
        $this->options = $options;
    }
    
    /**
     * Returns a database connection.
     */
    public function getConnection() {
        $connection = MDB2::singleton($this->dsn, $this->options);
        if (PEAR::isError($connection)) {
            throw new GrubbyException('No database connection available: '.$connection->getMessage());
        }
        return $connection;
    }
    
    /**
     * Executes a SQL query against the database.
     * Use for UPDATE, INSERT, DELETE.
     * @return GrubbyMDB2Result
     */
    public function execute($sql) {
        if (Grubby::$debug) {
            Grubby::debugMessage('Executing: '.$sql);
            $start = microtime(true);
        }
        
        $connection = $this->getConnection();
        $result = $connection->exec($sql);
        
        if (Grubby::$debug) {
            if ($result instanceof MDB2_Error) {
                Grubby::debugMessage('Error: '.$result->getMessage());
            }
            $end = microtime(true);
            Grubby::debugMessage('Time: '.number_format($end-$start, 4)." secs");
        }
        
        if ($result instanceof MDB2_Error) {
            throw new GrubbyException($result->getMessage());
        }
        
        return new GrubbyMDB2Result($result);
    }
    
    /**
     * Runs a SQL query against the database.
     * Use for SELECT.
     * @return false or a new MDB2_Result object.
     */
    public function query($sql) {
        if (Grubby::$debug) {
            Grubby::debugMessage('Querying: '.$sql);
            $start = microtime(true);
        }
        
        $connection = $this->getConnection();
        $result = $connection->query($sql);
        
        if (Grubby::$debug) {
            if ($result instanceof MDB2_Error) {
                Grubby::debugMessage('Error: '.$result->getMessage());
            }
            $end = microtime(true);
            Grubby::debugMessage('Time: '.number_format($end-$start, 4)." secs");
        }
        
        if ($result instanceof MDB2_Error) {
            throw new GrubbyException($result->getMessage());
        }
        
        return new GrubbyMDB2Recordset($result);
    }
    
    public function lastInsertID() {
        return $this->getConnection()->lastInsertID();
    }
    
    public function formatString($s) {
        if (is_null($s)) {
            return 'NULL';
        } elseif ($s) {
            return $this->getConnection()->quote($s, 'text');
        } else {
            return "''";
        }
    }
}

/**
 * Result object returned by a GrubbyMDB2Database execute function call.
 */
class GrubbyMDB2Result extends GrubbyResult {
    
    /**
     * Creates an MDB2 execute result object.
     * @param result mixed result of a call to MDB2::exec
     */
    public function __construct($result) {
        if ($result instanceof MDB2_Error) {
            $this->error = $result;
        } else {
            $this->affected_rows = $result;
        }
    }
}

/*
 * 
 */
class GrubbyMDB2Recordset extends GrubbyRecordset {
    
    /**
     * Creates a new recordset resource handler.
     * @param result mixed result of a call to MDB2::query
     */
    public function __construct($result) {
        if ($result instanceof MDB2_Error) {
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
        $row = $this->recordset->fetchRow(MDB2_FETCHMODE_ASSOC);
        if ($row) {
            return $this->unmarshal($row);
        } else {
            return $row;
        }
    }
    
    /**
     * Returns all remaining rows in the result set in an ordered array.
     * @return array
     */
    public function fetchAll() {
        $all = $this->recordset->fetchAll(MDB2_FETCHMODE_ASSOC);
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
}
