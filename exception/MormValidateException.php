<?php

class MormValidateException extends MormSqlException
{
    public function __construct($message = 'Erreur de validation')
    {
        if(!is_array($message)) 
        {
            $message = array($message);
        } 
        $this->message = $message;
    }
}
