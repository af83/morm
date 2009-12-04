<?php

class MormFieldSqlFunction
{
    
    private $fun;

    public function __construct($fun) {
        $this->fun = $fun;
    }
    
    public function __tostring() {
        return $this->fun;
    }
}
