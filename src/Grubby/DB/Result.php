<?php

/**
 * Result object returned by a GrubbyDBDatabase execute function call.
 */
class Grubby_DB_Result extends Grubby_Result {
    
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
    
    /**
     * True if the query resulted in an error.
     * @return boolean
     */
    public function error() {
        return isset($this->error);
    }
    
    /**
     * Available when error() is true.
     * @return string
     */
    public function errorMessage() {
        return $this->error->getMessage();
    }
}
