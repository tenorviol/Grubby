<?php

/**
 * Represents a database table, or a two dimensional ordering of fields.
 */
class Grubby_Table extends Grubby_Query {
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
     * @see src/Grubby/Query.php#crudImpl()
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
            throw new Grubby_Exception('Malformed Grubby query. This is an internal library problem. Please report.');
        }
    }
    
    /**
     * Creates a row in this table.
     * TODO: Undo the code triplication in this function.
     */
    private function createImpl($query) {
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
            $query['filters'][] = new Grubby_Filter($query['read']);
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
			$class = new ReflectionClass($this->info['class']);
			if ($this->info['class'] == 'Grubby_Record' || $class->isSubclassOf('Grubby_Record')) {
				$query = $this;
				$result->setRecordFactory(function ($row) use ($class, $query) {
					return $class->newInstance($query, $row);
				});
			} else {
				$result->setRecordFactory(function ($row) use ($class) {
					$object = $class->newInstance();
					foreach ($row as $key => $value) {
						$object->$key = $value;
					}
					return $object;
				});
			}
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
                $query['filters'][] = new Grubby_Filter($data[$pk]);
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
            throw new Grubby_Exception('Bulk updates must be accompanied by an all() qualifier.');
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
            throw new Grubby_Exception('Bulk deletes must be accompanied by an all() qualifier.');
        }
        
        $query['filters'][] = new Grubby_Filter($query['delete']); // delete contains a filter
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
