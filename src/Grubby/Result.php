<?php

/**
 * Create, update and delete returns a result.
 */
abstract class Grubby_Result {
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
