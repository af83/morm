<?php

class MormDummy
{
    private static $dummies = array();

    public static function get($class)
    {
        if(!isset(self::$dummies[$class]))
        {
            self::$dummies[$class] = new $class();
            self::$dummies[$class]->setAsDummy();
        }
        return self::$dummies[$class];
    }
}
