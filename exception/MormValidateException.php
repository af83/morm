<?php

class MormValidateException extends MormSqlException
{
    private $errors = array();

    public function __construct($message = 'Erreur de validation')
    {
        if(!is_array($message)) 
        {
            $message = array($message);
        } 
        else
            $this->errors = $message;
        $this->message = $message;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
