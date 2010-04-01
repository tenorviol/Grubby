<?php

define('GRUBBY_INT', 1);
define('GRUBBY_STRING', 2);
define('GRUBBY_DATETIME', 3);

define('GRUBBY_AUTO_CREATE_DATE', 1);
define('GRUBBY_AUTO_UPDATE_DATE', 2);
define('GRUBBY_AUTO_CREATE_REMOTE_ADDR', 3);
define('GRUBBY_AUTO_UPDATE_REMOTE_ADDR', 4);


/**
 * A relational database.
 */
abstract class Grubby_Database {
    
	public $time = 0;
	public $logger = null;
	
	public function log($message, $level) {
		if (isset($this->logger)) {
			$this->logger->log($message, $level);
		}
	}
	
    /**
     * Run a SQL statement that manipulates data against this database.
     * I.e. CREATE, UPDATE, DELETE
     * @retun GrubbyResult
     */
    public function execute($sql) {
        $this->log('Executing: '.$sql, LOG_DEBUG);
        
        $start = microtime(true);
        
        $result = $this->executeImpl($sql);
        
        $time = microtime(true) - $start;
        $this->time += $time;
        
        if ($result->error()) {
            $this->log('Error: '.$result->errorMessage(), LOG_ERR);
        }
        $this->log('Time: '.number_format($time, 4)." secs", LOG_DEBUG);
        
        if ($result->error()) {
            throw new Grubby_Exception($result->errorMessage());
        }
        
        return $result;
    }
    
    /**
     * Run a SQL query against the database.
     * I.e. SELECT
     * @return GrubbyRecordset
     */
    public function query($sql) {
        $this->log('Querying: '.$sql, LOG_DEBUG);
        
        $start = microtime(true);
        
        $result = $this->queryImpl($sql);
        
        $time = microtime(true) - $start;
        $this->time += $time;
        
        if ($result->error()) {
            $this->log('Error: '.$result->errorMessage(), LOG_ERR);
        }
        $this->log('Time: '.number_format($time, 4)." secs", LOG_DEBUG);
        
        if ($result->error()) {
            throw new Grubby_Exception($result->errorMessage());
        }
        
        return $result;
    }
    
    /**
     * @return mixed
     */
    public function lastInsertID() {
        $result = $this->query('SELECT LAST_INSERT_ID() AS last_id')->fetch();
        return $result['last_id'];
    }
    
    /**
     * 
     * @param $value mixed
     * @param $type string
     * @return string
     */
    public function format($value, $type = GRUBBY_STRING) {
        switch ($type) {
        case GRUBBY_INT:
            return $this->formatInt($value);
            break;
        case GRUBBY_STRING:
        case null:
            return $this->formatString($value);
            break;
        case GRUBBY_DATETIME:
            throw new Grubby_Exception('Datetime formatting unimplemented.');
            break;
        default:
            throw new Grubby_Exception('Unknown format type: '.$type);
        }
    }
    
    /**
     * Escapes and delimits a string for insertion into sql intended for this database.
     * If $string is null this function returns the sql empty string "''".
     * If $string contains another datatype it will be converted to a string by way of the strval function.
     * @param $string mixed
     * @return string
     */
    public abstract function formatString($string);
    
    /**
     * Prepares an integer for insertion into a sql statement,
     * making sure it is an string encoded integer.
     * @param $int mixed
     * @return string
     */
    public function formatInt($int) {
        if (is_int($int)) {
            return (string)$int;
        } elseif (is_float($int)) {
            return (string)round($int);
        } else {
            $int = strval($int);
            if (is_numeric($int)) {
                return $int;
            } else {
                return 0;
            }
        }
    }
    
    /**
     * Replaces wildcards (?) with formatted wildcard values.
     * Coded to MySQL's version of SQL.
     * TODO: Generalize for all database (accept '''' from SQL Server)
     * TODO: Allow ? in column identifiers delimited by ` or cross-database equivalents
     */
    public function replaceWildcards($sql, $wildcards, $types = array()) {
        if (is_scalar($wildcards)) {
            $wildcards = array($wildcards);
        }
        if (is_scalar($types)) {
            $types = array($types);
        }
        $return = '';
        $len = strlen($sql);
        $wc = 0;
        $marker = 0;
        $i = 0;
        while ($i < $len) {
            if ($sql[$i] == '?') {
                if (!isset($wildcards[$wc])) {
                    throw new Grubby_Exception('Too few wildcards for filter expression.');
                }
                $return .= substr($sql, $marker, $i-$marker);
                $return .= $this->format($wildcards[$wc], @$types[$wc]);
                $marker = $i+1;
                $wc++;
            } elseif ($sql[$i] == '\'') { // string starting, skip to end
                $end = false;
                while (!$end) {
                    $i++;
                    if ($i == $len) {
                        throw new Grubby_Exception('Unterminated string in filter expression, "'.$sql.'"');
                    } elseif ($sql[$i] == '\\') {
                        $i++;
                    } elseif ($sql[$i] == '\'') {
                        $end = true;
                    }
                }
            }
            $i++;
        }
        $wildcard_count = count($wildcards);
        if ($wc < $wildcard_count && ($wildcard_count > 1 || $wildcards[0])) {
            throw new Grubby_Exception('Too many wildcards for filter expression.');
        }
        if ($marker < $len) {
            $return .= substr($sql, $marker);
        }
        return $return;
    }
}
