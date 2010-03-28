<?php

/**
 * Grubby database using the DB abstraction layer
 */
class Grubby_DB_Database extends Grubby_Database {
    private $dsn;
    private $options;
    private $connection = null;
    
    public $time;
    
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
                throw new Grubby_Exception('No database connection available: '.$connect->getMessage());
            }
            $this->connection = $connect;
        }
        return $this->connection;
    }
    
    /**
     * Executes a SQL query against the database.
     * Use for UPDATE, INSERT, DELETE.
     * @return Grubby_DB_Result
     */
    public function executeImpl($sql) {
        $connection = $this->getConnection();
        $result = $connection->query($sql);
        return new Grubby_DB_Result($result, $connection);
    }
    
    /**
     * Runs a SQL query against the database.
     * Use for SELECT.
     * @return false or a new DB_Result object.
     */
    public function queryImpl($sql) {
        $connection = $this->getConnection();
        $result = $connection->query($sql);
        return new Grubby_DB_Recordset($this, $result);
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
