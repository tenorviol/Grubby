<?php
/**
 * GrubbyDB : Grubby database using the DB abstraction layer
 * 
 * Version --version--
 * Copyright (c) 2009 Christopher Johnson
 */

require_once 'Grubby.php';
require_once 'DB.php';

class GrubbyDB extends GrubbyDatabase {
    private $dsn;
    private $options;
    private $connection = null;
    
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
        if ($this->connection === null) {
            $connect = DB::connect($this->dsn, $this->options);
            if (PEAR::isError($connect)) {
                throw new GrubbyException('No database connection available: '.$connect->getMessage());
            }
            $this->connection = $connect;
        }
        return $this->connection;
    }
    
    /**
     * Executes a SQL query against the database.
     * Use for UPDATE, INSERT, DELETE.
     * @return GrubbyDBResult
     */
    public function execute($sql) {
        if (Grubby::$debug) {
            Grubby::debugMessage('Executing: '.$sql);
            $start = microtime(true);
        }
        
        $connection = $this->getConnection();
        $result = $connection->query($sql);
        
        if (Grubby::$debug) {
            if (PEAR::isError($result)) {
                Grubby::debugMessage('Error: '.$result->getMessage());
            }
            $end = microtime(true);
            Grubby::debugMessage('Time: '.number_format($end-$start, 4)." secs");
        }
        
        if (PEAR::isError($result)) {
            throw new GrubbyException($result->getMessage());
        }
        
        return new GrubbyDBResult($result, $connection);
    }
    
    /**
     * Runs a SQL query against the database.
     * Use for SELECT.
     * @return false or a new DB_Result object.
     */
    public function query($sql) {
        if (Grubby::$debug) {
            Grubby::debugMessage('Querying: '.$sql);
            $start = microtime(true);
        }
        
        $connection = $this->getConnection();
        $result = $connection->query($sql);
        
        if (Grubby::$debug) {
            if (PEAR::isError($result)) {
                Grubby::debugMessage('Error: '.$result->getMessage());
            }
            $end = microtime(true);
            Grubby::debugMessage('Time: '.number_format($end-$start, 4)." secs");
        }
        
        if (PEAR::isError($result)) {
            throw new GrubbyException($result->getMessage());
        }
        
        return new GrubbyDBRecordset($result);
    }
    
    public function lastInsertID() {
        $result = $this->query('SELECT LAST_INSERT_ID() AS last_id')->fetch();
        return $result['last_id'];
    }
    
    public function formatString($s) {
        if (is_null($s)) {
            return 'NULL';
        } elseif ($s) {
            return $this->getConnection()->quoteSmart(strval($s));
        } else {
            return "''";
        }
    }
}

/**
 * Result object returned by a GrubbyDBDatabase execute function call.
 */
class GrubbyDBResult extends GrubbyResult {
    
    /**
     * Creates an DB execute result object.
     * @param result mixed result of a call to DB::exec
     */
    public function __construct($result, $connection) {
        if ($result instanceof DB_Error) {
            $this->error = $result;
        } else {
            $this->affected_rows = $connection->affectedRows();
        }
    }
}

/*
 * 
 */
class GrubbyDBRecordset extends GrubbyRecordset {
    
    /**
     * Creates a new recordset resource handler.
     * @param result mixed result of a call to DB::query
     */
    public function __construct($result) {
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
            return $row;
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
}
