<?php

/*
 * Modifies queries as they pass through the CRUD method chain.
 * Criteria are added as they are encountered.
 * All other modifiers take the priority of first written (like css).
 */
class Grubby_Query_Modifier extends Grubby_Query {
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
