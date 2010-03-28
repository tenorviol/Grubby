<?php

/**
 * Allows for CRUD operations.
 * GrubbyQuery objects are chained together allowing for complex and varied queries performed against a database table.
 * This is the base object of all GrubbyTable type queries.
 */
class Grubby_Query {
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
        return new Grubby_Query_Modifier($this, array('filters'=>new Grubby_Filter($filter)));
    }
    
    /**
     * Reduce the set of returned records to those matching the custom filter.
     * @param $expression string SQL boolean expression
     * @param $wildcards string|array replaces ? in the expression
     * @return GrubbyQuery
     */
    public function filterExpression($expression, $wildcards = null) {
        return new Grubby_Query_Modifier($this, array('expressions'=>array($expression, $wildcards)));
    }
    
    /**
     * Reduce the set of returned records to those not matching the filter equality.
     * @param $filter int|string|array
     * @return GrubbyQuery
     */
    public function not($filter) {
        return new Grubby_Query_Modifier($this, array('filters'=>new Grubby_NotFilter($filter)));
    }
    
    /**
     * Only take a certain sub-set of the records.
     * @param $offset int
     * @param $count int
     * @return GrubbyQuery
     */
    public function slice($offset, $count) {
        return new Grubby_Query_Modifier($this, array('slice'=>array('offset'=>$offset, 'limit'=>$count)));
    }
    
    /**
     * Changes the sort order of the query.
     * As queries can have only one sort order, later sorts will overwrite previous sorts.
     * @param $order string|array the field(s) to sort by
     * @return GrubbyQuery
     */
    public function sort($order) {
        return new Grubby_Query_Modifier($this, array('sort'=>$order));
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
        return new Grubby_Query_All($this);
    }
    
    /**
     * Changes the field set being queried.
     * Later field sets totally override earlier ones.
     * @param $fields string|array
     * @return GrubbyQuery
     */
    public function fields($fields) {
        return new Grubby_Query_Modifier($this, array('fields'=>$fields));
    }
    
    /**
     * Aggregates certain fields together.
     * Later aggregates override earlier ones.
     * @param $fields string|array
     * @return GrubbyQuery
     */
    public function aggregate($fields) {
        return new Grubby_Query_Modifier($this, array('aggregate'=>$fields));
    }
    
    public function join($query, $on) {
        return new Grubby_Query_Modifier($this, array('join'=>array($query, $on)));
    }
}
