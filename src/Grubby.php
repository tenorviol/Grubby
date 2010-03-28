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

define('GRUBBY_INT', 1);
define('GRUBBY_STRING', 2);
define('GRUBBY_DATETIME', 3);

define('GRUBBY_AUTO_CREATE_DATE', 1);
define('GRUBBY_AUTO_UPDATE_DATE', 2);
define('GRUBBY_AUTO_CREATE_REMOTE_ADDR', 3);
define('GRUBBY_AUTO_UPDATE_REMOTE_ADDR', 4);

/**
 * 
 */
class Grubby {
    public static $debug = false;
    public static $time = 0;
    
    public static function debugMessage($message) {
        if (empty($_SERVER['REQUEST_URI'])) {
            print 'Grubby: '.$message."\n";
        } else {
            print '<div class="grubby debug">'.htmlspecialchars($message).'</div>';
        }
    }
}

/**
 * A relational database.
 */
abstract class GrubbyDatabase {
    
    /**
     * Run a SQL statement that manipulates data against this database.
     * I.e. CREATE, UPDATE, DELETE
     * @retun GrubbyResult
     */
    public function execute($sql) {
        if (Grubby::$debug) {
            Grubby::debugMessage('Executing: '.$sql);
        }
        
        $start = microtime(true);
        
        $result = $this->executeImpl($sql);
        
        $time = microtime(true) - $start;
        $this->time += $time;
        Grubby::$time += $time;
        
        if (Grubby::$debug) {
            if ($result->error()) {
                Grubby::debugMessage('Error: '.$result->errorMessage());
            }
            Grubby::debugMessage('Time: '.number_format($time, 4)." secs");
        }
        
        if ($result->error()) {
            throw new GrubbyException($result->errorMessage());
        }
        
        return $result;
    }
    
    /**
     * Run a SQL query against the database.
     * I.e. SELECT
     * @return GrubbyRecordset
     */
    public function query($sql) {
        if (Grubby::$debug) {
            Grubby::debugMessage('Querying: '.$sql);
        }
        
        $start = microtime(true);
        
        $result = $this->queryImpl($sql);
        
        $time = microtime(true) - $start;
        $this->time += $time;
        Grubby::$time += $time;
        
        if (Grubby::$debug) {
            if ($result->error()) {
                Grubby::debugMessage('Error: '.$result->errorMessage());
            }
            Grubby::debugMessage('Time: '.number_format($time, 4)." secs");
        }
        
        if ($result->error()) {
            throw new GrubbyException($result->errorMessage());
        }
        
        return $result;
    }
    
    /**
     * @return mixed
     */
    public abstract function lastInsertID();
    
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
            throw new GrubbyException('Datetime formatting unimplemented.');
            break;
        default:
            throw new GrubbyException('Unknown format type: '.$type);
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
                    throw new GrubbyException('Too few wildcards for filter expression.');
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
                        throw new GrubbyException('Unterminated string in filter expression, "'.$sql.'"');
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
            throw new GrubbyException('Too many wildcards for filter expression.');
        }
        if ($marker < $len) {
            $return .= substr($sql, $marker);
        }
        return $return;
    }
}

/**
 * Allows for CRUD operations.
 * GrubbyQuery objects are chained together allowing for complex and varied queries performed against a database table.
 * This is the base object of all GrubbyTable type queries.
 */
class GrubbyQuery {
    private $parent;
    
    /**
     * Create a new GrubbyQuery beholden to a parent query.
     * The top level query should be a GrubbyTable, which will
     * actually perform the ultimate CRUD operation requested.
     * GrubbyQuery objects are chained by using the helper functions
     * provided here:  all(), sort(), etc.
     */
    public function __construct($parent) {
        $this->parent = $parent;
    }
    
    /**
     * Adds a row of data to the table.
     * @param $data array
     * @return GrubbyResult
     */
    public function create($data) {
        return $this->crudImpl(array('create'=>$data));
    }
    
    /**
     * Reads data from the query following all of the rules that have been chained onto the end.
     * @param $filter int|string|array
     * @return GrubbyRecordset
     */
    public function read($filter = true) {
        return $this->crudImpl(array('read'=>$filter));
    }
    
    /**
     * Update a record in this database table.
     * Batch updates can be performed only after all() has been chained onto the query.
     * @return GrubbyResult
     */
    public function update($data) {
        return $this->crudImpl(array('update'=>$data));
    }
    
    /**
     * Delete a record from this database table.
     * Bulk deletions can be performed only after all() has been chained onto the query.
     * @return GrubbyResult
     */
    public function delete($filter = true) {
        return $this->crudImpl(array('delete'=>$filter));
    }
    
    /**
     * Count records in this query, accounting for all filters, etc.
     * @return int
     */
    public function count($filter = true) {
        $result = $this->fields(array('tally'=>'COUNT(*)'))->read($filter);
        $row = $result->fetch();
        return $row['tally'];
    }
    
    /**
     * $query can have information attached to it as the update tree is traversed.
     * See GrubbyTable::updateImpl for information on the $query array.
     * @return GrubbyResult|GrubbyRecordset
     */
    protected function crudImpl($query) {
        return $this->parent->crudImpl($query);
    }
    
    /**
     * Reduce the set of returned records to those matching the filter equality.
     * @param $filter int|string|array
     * @return GrubbyQuery
     */
    public function filter($filter) {
        return new GrubbyQueryModifier($this, array('filters'=>new GrubbyFilter($filter)));
    }
    
    /**
     * Reduce the set of returned records to those matching the custom filter.
     * @param $expression string SQL boolean expression
     * @param $wildcards string|array replaces ? in the expression
     * @return GrubbyQuery
     */
    public function filterExpression($expression, $wildcards = null) {
        return new GrubbyQueryModifier($this, array('expressions'=>array($expression, $wildcards)));
    }
    
    /**
     * Reduce the set of returned records to those not matching the filter equality.
     * @param $filter int|string|array
     * @return GrubbyQuery
     */
    public function not($filter) {
        return new GrubbyQueryModifier($this, array('filters'=>new GrubbyNotFilter($filter)));
    }
    
    /**
     * Only take a certain sub-set of the records.
     * @param $offset int
     * @param $count int
     * @return GrubbyQuery
     */
    public function slice($offset, $count) {
        return new GrubbyQueryModifier($this, array('slice'=>array('offset'=>$offset, 'limit'=>$count)));
    }
    
    /**
     * Changes the sort order of the query.
     * As queries can have only one sort order, later sorts will overwrite previous sorts.
     * @param $order string|array the field(s) to sort by
     * @return GrubbyQuery
     */
    public function sort($order) {
        return new GrubbyQueryModifier($this, array('sort'=>$order));
    }
    
    /**
     * Batch updates and bulk deletions are disallowed by default.
     * Calling all() at the end of a query chain overrides that.
     * Note that further chaining the query removes the all() capability.
     * 
     * Example:
     * // the former works, the latter throws an exception
     * $table->constrain('id < 10')->all()->delete();
     * $table->all()->constrain('id < 10')->delete();
     * 
     * @return GrubbyQuery
     */
    public function all() {
        return new GrubbyQueryAll($this);
    }
    
    /**
     * Changes the field set being queried.
     * Later field sets totally override earlier ones.
     * @param $fields string|array
     * @return GrubbyQuery
     */
    public function fields($fields) {
        return new GrubbyQueryModifier($this, array('fields'=>$fields));
    }
    
    /**
     * Aggregates certain fields together.
     * Later aggregates override earlier ones.
     * @param $fields string|array
     * @return GrubbyQuery
     */
    public function aggregate($fields) {
        return new GrubbyQueryModifier($this, array('aggregate'=>$fields));
    }
    
    public function join($query, $on) {
        return new GrubbyQueryModifier($this, array('join'=>array($query, $on)));
    }
}

/*
 * Modifies queries as they pass through the CRUD method chain.
 * Criteria are added as they are encountered.
 * All other modifiers take the priority of first written (like css).
 */
class GrubbyQueryModifier extends GrubbyQuery {
    private $modifier;

    public function __construct($parent, $modifier) {
        parent::__construct($parent);
        $this->modifier = $modifier;
    }
    
    /**
     * Modify the query and pass it up the chain.
     */
    protected function crudImpl($query) {
        foreach ($this->modifier as $key => $value) {
            if ($key == 'filters') {
                $query['filters'][] = $value;
            } elseif ($key == 'expressions') {
                $query['expressions'][] = $value;
            } elseif (!isset($query[$key])) {
                $query[$key] = $value;
            }
        }
        return parent::crudImpl($query);
    }
}

/**
 * 
 */
class GrubbyQueryAll extends GrubbyQuery {
    
    /**
     * Allows bulk updates of a database table.
     * @return GrubbyResult
     */
    public function update($data) {
        return $this->crudImpl(array('update'=>$data, 'all'=>true));
    }
    
    /**
     * Allows bulk deletes from the database table.
     * @return GrubbyResult
     */
    public function delete($filter = true) {
        return $this->crudImpl(array('delete'=>$filter, 'all'=>true));
    }
}

/**
 * Defines the generally used filter, employed by read, delete, filter, and not.
 */
class GrubbyFilter {
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
                    throw new GrubbyException('Filter arrays can only contain column=>value pairings.');
                } elseif (is_null($value)) {
                    $where[] = $field.' IS NULL';
                } elseif (is_array($value)) {
                    throw new GrubbyException('Array filter values not implemented yet.');
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
                throw new GrubbyException('Multi-column primary keys cannot use shorthand pk notation.');
            }
            $this->empty = false;
        }
    }
}

class GrubbyNotFilter extends GrubbyFilter {
    public function getExpression() {
        return 'NOT ('.parent::getExpression().')';
    }
}

/**
 * Represents a database table, or a two dimensional ordering of fields.
 */
class GrubbyTable extends GrubbyQuery {
    private $database;
    private $info;
    
    /**
     * Creates a new Grubby table.
     * 
     * $database a reference to a Grubby SQL database.
     * $table_info an array of information about the table
     *      array(
     *          ['name'] => table name
     *          ['primary_key'] => string|array
     *          ['fields'] => array(
     *              [1] => array(
     *                  ['name'] => column name
     *                  ['type'] => data type
     *                  ['size'] => data size
     *                  ['null'] => true, NULL; false, NOT NULL
     *                  ['character_set'] => i.e. UTF-8
     *                  ['collate'] => sort order
     *                  ['default'] => value
     *                  ['auto_increment'] => boolean
     *              )
     *              [2] => array(...)
     *              ...
     *          )
     *          ['character_set'] => i.e. UTF-8
     *          ['collate'] => specify a default collation for the table
     *          ['sort'] => array(column1, column2, etc.) affecting all reads of the table
     *      )
     */
    public function __construct($info) {
        if (is_string($info)) {
            $info = array('name'=>$info);
        }
        $this->info = $info;
    }
    
    /**
     * 
     * @return string|array|null
     */
    public function primaryKey() {
        return $this->info['primary_key'];
    }
    
    /**
     * Returns the value formatted for insertion into or comparison against the field.
     * @return string
     */
    public function formatFieldValue($field, $value) {
        return $this->info['database']->formatString($value);
    }
    
    /**
     * @see src/GrubbyQuery#crudImpl()
     */
    protected function crudImpl($query) {
        if (array_key_exists('create', $query)) {
            return $this->createImpl($query);
        } elseif (array_key_exists('read', $query)) {
            return $this->readImpl($query);
        } elseif (array_key_exists('update', $query)) {
            return $this->updateImpl($query);
        } elseif (array_key_exists('delete', $query)) {
            return $this->deleteImpl($query);
        } else {
            throw new GrubbyException('Malformed Grubby query. This is an internal library problem. Please report.');
        }
    }
    
    /**
     * Creates a row in this table.
     * TODO: Undo the code triplication in this function.
     */
    private function createImpl($query) {
        //print_r($query);
        $data = $query['create'];
        $fields = array();
        $values = array();
        if (isset($this->info['fields'])) {
            foreach ($this->info['fields'] as $field) {
                $name = $field['name'];
				if (@$field['auto'] == GRUBBY_AUTO_CREATE_DATE || @$field['auto'] == GRUBBY_AUTO_UPDATE_DATE) {
                    $fields[] = $name;
                    $values[] = 'NOW()';
                } elseif (@$field['auto'] == GRUBBY_AUTO_CREATE_REMOTE_ADDR || @$field['auto'] == GRUBBY_AUTO_UPDATE_REMOTE_ADDR) {
                    $fields[] = $name;
                    $values[] = $this->info['database']->formatString($_SERVER['REMOTE_ADDR']);
                } elseif (is_array($data) && isset($data[$name])) {
                    $fields[] = $name;
                    $values[] = $this->info['database']->formatString($data[$name]);
                } elseif (isset($data->$name)) {
                    $fields[] = $name;
                    $values[] = $this->info['database']->formatString($data->$name);
                }
            }
        } else {
            foreach ($data as $key => $value) {
                $fields[] = $key;
                $values[] = $this->info['database']->formatString($value);
            }
        }
        $sql = 'INSERT INTO '.$this->info['name'].' ('.implode(',',$fields).') VALUES ('.implode(',',$values).')';
        $result = $this->info['database']->execute($sql);
        if ($result->affected_rows == 1) {
            $result->insert_id = $this->info['database']->lastInsertID();
        }
        return $result;
    }
    
    /**
     * Reads a row from this table.
     * If $query is an array, we will attempt to match all values exactly, returning those that match.
     * If $query is a scalar value, it will be assumed to be the value of the primary key of the row.
     * Returns the row to be read.
     */
    private function readImpl($query) {
        
        $first = is_scalar($query['read']) && !is_bool($query['read']);
        
        // FIELDS
        $fields = $this->buildFieldList($query);
        
        // JOINS
        $join = '';
        if (isset($query['join'])) {
            $join = ' JOIN '.$query['join'][0]->info['name'].' USING ('.$query['join'][1].')';
        }
        
        // WHERE clause
        if ($query['read'] !== true) {
            $query['filters'][] = new GrubbyFilter($query['read']);
        }
        $where = $this->buildWhereClause($query);
        
        // GROUP BY clause
        $group = '';
        if (isset($query['aggregate'])) {
            $aggregate = is_array($query['aggregate']) ? $query['aggregate'] : array($query['aggregate']);
            $group = ' GROUP BY '.implode(', ', $aggregate);
        }
        
        // ORDER BY clause
        $order = '';
        if (isset($query['sort']) && $query['sort']) {
            $sort = $query['sort'];
            if (is_array($sort)) {
                $order = ' ORDER BY '.implode(', ', $sort);
            } else {
                $order = ' ORDER BY '.$sort;
            }
        }
        
        // LIMIT clause
        $limit = '';
        if (isset($query['slice'])) {
            $limit = $query['slice']['limit'] ? $query['slice']['limit'] : '999999999999999';
            $limit = ' LIMIT '.($query['slice']['offset'] ? $query['slice']['offset'].',' : '').$limit;
        }
        
        $sql = 'SELECT '.$fields.' FROM '.$this->info['name'].$join.$where.$group.$order.$limit;
        $result = $this->info['database']->query($sql);
        if (!empty($this->info['class'])) {
	        $result->setObjectType($this->info['class']);
        }
	    if ($first) {
            return $result->fetch();  // Return the first result of the iterator
        } else {
            return $result;
        }
    }
    
    /**
     * Updates a row in this table.
     * $data is an array containing all values to be updated and the primary key of the row to be updated.
     * @return the number of rows affected
     */
    private function updateImpl($query) {
        //print_r($query);
        $data = $query['update'];
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        
        $field_index = $this->getFieldIndex();
        $auto_index  = $this->getAutoIndex();
        
        $pk_bound = false;
        if ($this->info['primary_key']) {
            $pk = $this->info['primary_key'];
            if (array_key_exists($pk, $data)) {
                $query['filters'][] = new GrubbyFilter($data[$pk]);
                unset($data[$pk]);
                $pk_bound = true;
            }
        }
        
        $changes = array();
        foreach ($data as $field => $value) {
            if (array_key_exists($field, $field_index)) {
                $changes[$field] = $this->info['database']->formatString($value);
            }
        }
        foreach ($auto_index as $field => $auto) {
            switch ($auto) {
            case GRUBBY_AUTO_UPDATE_DATE:
                $changes[$field] = 'NOW()';
                break;
            case GRUBBY_AUTO_UPDATE_REMOTE_ADDR:
                $changes[$field] = $this->info['database']->formatString($_SERVER['REMOTE_ADDR']);
            }
        }
        foreach ($changes as $field => $change) {
            $changes[$field] = "$field=$change";
        }
        $change = implode(', ', $changes);
        
        // WHERE clause
        $where = $this->buildWhereClause($query);
        
        if (!$pk_bound && empty($query['all'])) {
            throw new GrubbyException('Bulk updates must be accompanied by an all() qualifier.');
        }
        
        $sql = 'UPDATE '.$this->info['name'].' SET '.$change.$where;
        $result = $this->info['database']->execute($sql);
        return $result;
    }
    
    /**
     * Deletes a row from this table.
     * If $query is an array, we will attempt to match all values exactly, deleting those that do.
     * If $query is a scalar value, it will be assumed to be the value of the primary key of the row.
     * @return the number of rows affected
     */
    private function deleteImpl($query) {
        if ((!is_scalar($query['delete']) || is_bool($query['delete'])) && empty($query['all'])) {
            throw new GrubbyException('Bulk deletes must be accompanied by an all() qualifier.');
        }
        
        $query['filters'][] = new GrubbyFilter($query['delete']); // delete contains a filter
        $where = $this->buildWhereClause($query);
        
        $sql = 'DELETE FROM '.$this->info['name'].$where;
        $result = $this->info['database']->execute($sql);
        return $result;
    }
    
    private $field_index = null;
    private $auto_index = null;
    
    private function getFieldIndex() {
        if ($this->field_index === null) {
            $this->buildFieldIndex();
        }
        return $this->field_index;
    }
    
    private function getAutoIndex() {
        if ($this->auto_index === null) {
            $this->buildFieldIndex();
        }
        return $this->auto_index;
    }
    
    private function buildFieldIndex() {
        $this->field_index = array();
        $this->auto_index = array();
        
        foreach ($this->info['fields'] as $field) {
            $name = $field['name'];
            
            switch (strtoupper($field['type'])) {
            case 'INT':
                $type = GRUBBY_INT;
                break;
            case 'DATETIME':
                $type = GRUBBY_DATETIME;
                break;
            default:
                $type = GRUBBY_STRING;
            }
            
            $this->field_index[$name] = array('name'=>$name, 'type'=>$type);
            
            if (!empty($field['auto'])) {
                $this->auto_index[$name] = $field['auto'];
            }
        }
    }
    
    /**
     * 
     * @param $query
     * @return unknown_type
     */
    private function buildFieldList(&$query) {
        if (isset($query['fields'])) {
            if (is_array($query['fields'])) {
                $fields = array();
                foreach ($query['fields'] as $alias => $field) {
                    if (is_string($alias) && $alias != $field) {
                        $fields[] = $field.' AS '.$alias;
                    } else {
                        $fields[] = $field;
                    }
                }
                $fields = implode(', ', $fields);
            } else {
                $fields = $query['fields'];
            }
        } else {
            $fields = '*';
        }
        return $fields;
    }
    
    /**
     * @param criteria array
     */
    private function buildWhereClause(&$query) {
        $filters = isset($query['filters']) ? $query['filters'] : array();
        
        $pk = @$this->info['primary_key'];
        $pk_anchor = array_fill_keys(is_array($pk) ? $pk : array($pk), false);
        
        $where = array();
        foreach ($filters as $filter) {
            $filter->setTable($this);
            if ($filter->emptySet()) {
                return ' WHERE 1=0';
            }
            $expression = $filter->getExpression();
            if ($expression === true) {
                continue;
            }
            $where[] = $expression;
        }
        if (isset($query['expressions'])) {
            foreach ($query['expressions'] as $expression) {
                $sql = $expression[0];
                $wildcards = isset($expression[1]) ? $expression[1] : array();
                $types = isset($expression[2]) ? $expression[2] : array();
                $where[] = '('.$this->info['database']->replaceWildcards($sql, $wildcards, $types).')';
            }
        }
        
        $row_bound = false;
        foreach ($pk_anchor as $pk=>$anchored) {
            $row_bound = $row_bound | $anchored;
        }
        $query['row_bound'] = $row_bound;
        
        if (isset($where[0])) {
            return ' WHERE '.implode(' AND ', $where);
        }
        return '';
    }
    
    /**
     * Builds and executes the sql to create this table.
     */
    public function createTable() {
        $column_sql = array();
        foreach ($this->info['fields'] as $column) {
            $type = isset($column['type']) ? $column['type'] : INT;
            
            if (isset($column['null'])) {
                $null = (bool)$column['null'];  // manual setting
            } else {
                $null = true;
            }
            
            if (isset($column['size'])) {
                $size = $column['size'];  // manual setting
            } elseif (in_array(strtoupper($type), array('VARCHAR', 'VARBINARY'))) {
                $size = 255;  // VARCHAR and VARBINARY types have an obligatory size requirement
            } else {
                unset($size);
            }
            
            $column_sql[] = $column['name'].' '.$type.(isset($size) ? '('.$size.')' : '').
                    (isset($column['character_set']) ? ' CHARACTER SET '.$column['character_set'] : '').
                    (isset($column['collate']) ? ' COLLATE '.$column['collate'] : '').
                    ($null ? ' NULL' : ' NOT NULL').
                    (isset($column['default']) ? ' DEFAULT '.$this->info['database']->formatInt($column['default']) : '').
                    (isset($column['auto_increment']) && $column['auto_increment'] ? ' AUTO_INCREMENT' : '');
        }
        if (!empty($this->info['primary_key'])) {
            if (is_array($this->info['primary_key'])) {
                $pk = implode(',', $this->info['primary_key']);
            } else {
                $pk = $this->info['primary_key'];
            }
            $column_sql[] = 'PRIMARY KEY ('.$pk.')';
        }
        $sql = 'CREATE TABLE '.$this->info['name']." (\n    ".implode(",\n    ", $column_sql).')';
        return $this->info['database']->execute($sql);
    }
    
    /**
     * 
     * @return unknown_type
     */
    public function dropTable() {
        $sql = 'DROP TABLE IF EXISTS '.$this->info['name'];
        return $this->info['database']->execute($sql);
    }
}

/**
 * Create, update and delete returns a result.
 */
abstract class GrubbyResult {
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

/**
 * Read returns a recordset.
 */
abstract class GrubbyRecordset {
    protected $object_type;
    
    /**
     * 
     * @param $object_type
     * @return unknown_type
     */
    public function setObjectType($object_type) {
        $this->object_type = $object_type;
    }
    
    /**
     * 
     * @param $result
     * @return unknown_type
     */
    protected function unmarshal($result) {
        if ($this->object_type) {
            $object = new $this->object_type;
            foreach ($result as $key => $value) {
                $object->$key = $value;
            }
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

/**
 * When something goes wrong, a grubby exception will be thrown.
 */
class GrubbyException extends Exception {
}
