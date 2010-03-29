<?php

/**
 * GrubbyMDB2 : Grubby database using the MDB2 abstraction layer
 */
class Grubby_MDB2_Database extends Grubby_Database {
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
            throw new Grubby_Exception('No database connection available: '.$connection->getMessage());
        }
        return $connection;
    }
    
    /**
     * Executes a SQL query against the database.
     * Use for UPDATE, INSERT, DELETE.
     * @return Grubby_MDB2_Result
     */
    public function executeImpl($sql) {
        $connection = $this->getConnection();
        $result = $connection->exec($sql);
        return new Grubby_MDB2_Result($result);
    }
    
    /**
     * Runs a SQL query against the database.
     * Use for SELECT.
     * @return false or a new MDB2_Result object.
     */
    public function queryImpl($sql) {
        $connection = $this->getConnection();
        $result = $connection->query($sql);
        return new Grubby_MDB2_Recordset($result);
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
