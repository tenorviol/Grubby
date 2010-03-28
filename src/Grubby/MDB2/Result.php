<?php

/**
 * Result object returned by a GrubbyMDB2Database execute function call.
 */
class Grubby_MDB2_Result extends Grubby_Result {
    
    /**
     * Creates an MDB2 execute result object.
     * @param result mixed result of a call to MDB2::exec
     */
    public function __construct($result) {
        if ($result instanceof MDB2_Error) {
            $this->error = $result;
        } else {
            $this->affected_rows = $result;
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
