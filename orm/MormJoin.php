<?php

class MormJoin
{
    private $first_obj = null;
    private $second_obj = null;
    private $origin_obj = null;//used in case of a has_many[using]

    private $first_class = null;
    private $second_class = null;

    private $source = null;
    private $referer = null;
    private $direction = 'LEFT';
    private $class_from_field_value = array();

    private $load = true;

    private $first_belongs_to_second = false;
    private $second_belongs_to_first = false;

    public function __construct($first, $second, Array $opts = array())
    {
        $this->loadObject('first', $first);
        $this->loadObject('second', $second);
        
        $this->setOptions($opts);
        $this->setKeys();
    }

    private function loadObject($position, $obj)
    {
        $obj_name = $position . "_obj";
        $obj_class = $position . "_class";
        if(is_object($obj))
        {
            $this->$obj_name = $obj;
            $this->$obj_class = get_class($obj);
        }
        else
        {
            $this->$obj_class = $obj;
            $this->$obj_name = MormDummy::get($obj);
        }
    }

    private function setOptions($opts)
    {
        $this->direction = isset($opts['dir']) ? strtoupper($opts['dir']) : $this->direction;
        $this->source = isset($opts['source']) ? $opts['source'] : null;
        $this->referer = isset($opts['referer']) ? $opts['referer'] : null;
        $this->load = isset($opts['load']) && is_bool($opts['load']) ? $opts['load'] : true;
        $this->class_from_field_value = isset($opts['class_from_field_value']) ? $opts['class_from_field_value'] : array();
        $this->setSource();
        $this->setReferer();
    }

    private function setReferer()
    {
        $obj_refering = $this->first_belongs_to_second ? $this->second_obj : $this->first_obj;
        if(is_null($this->referer))
        {
            $class_that_belongs = $this->first_belongs_to_second ? $this->first_class : $this->second_class;
            $has_many_stmts = $obj_refering->getHasManyStatementsFor($class_that_belongs, array('source' => $this->source), false);
            if(count($has_many_stmts) == 1)
            {
                $this->referer = key($has_many_stmts);
                $this->referer_stmt = $has_many_stmts;
                return;
            }
            if(empty($has_many_stmts))
                //FIXME not able to refer, I should keep this info in a var, this can be very usefull
                return;
            //and now, I can refer to more than one has_many, this is too bad and I should at least give an advice in the Logs
            return;
        }
        else
        {
            $this->referer_stmt = $obj_refering->getHasManyStatement($this->referer);
        }
    }

    private function setSource()
    {
        if(!is_null($this->source))
        {
            $source = $this->source;
            if($this->second_obj->getBelongsToStmt($this->source))
                $this->second_belongs_to_first = true;
            else
                $this->first_belongs_to_second = true;
        }
        else //nothing was given to help me find my joining refs, let's try to guess a source
        {
            if($source = $this->second_obj->getBelongsToNameFor($this->first_obj, false))
                $this->second_belongs_to_first = true;
            else if($source = $this->first_obj->getBelongsToNameFor($this->second_obj, false))
                $this->first_belongs_to_second = true;
            if(!is_null($source) && !is_array($source))
            {
                $this->source = $source;
                $obj_that_belongs = $this->first_belongs_to_second ? $this->first_obj : $this->second_obj;
                $this->source_stmt = $obj_that_belongs->getBelongsToStmt($this->source);
            }
        }
        //ok too bad we could not guess. Can we use a referer to do that maybe ?
        //if we do, that means that we gave a referer without specifying the source.
        //We did this because we thought that the referer could lead us to the source.
        //Since the source HAS to be defined in either class, if we get in there, that's because we must have the choice on the sources
        if(!is_null($this->referer) && is_array($source) && ($this->first_belongs_to_second || $this->second_belongs_to_first))
        {
            $obj_refering = $this->first_belongs_to_second ? $this->second_obj : $this->first_obj;
            $has_many_stmt = $obj_refering->getHasManyStatement($this->referer);
            if(is_null($has_many_stmt)) //OK fuck off, I'm not david copperfield
                throw new Exception(sprintf("%s is not a reference between %s and %s", $this->referer, $this->first_class, $this->second_class));
            if(isset($has_many_stmt['source']) && in_array($has_many_stmt['source'], $source))
            {
                $this->source = $has_many_stmt['source'];
                $obj_that_belongs = $this->first_belongs_to_second ? $this->first_obj : $this->second_obj;
                $this->source_stmt = $obj_that_belongs->getBelongsToStmt($this->source);
            }
        }
        if(is_null($this->source))
            throw new Exception(sprintf("Could not find a relation between %s and %s", $this->first_class, $this->second_class));
    }

    private function setKeys()
    {
        if(isset($this->referer) && isset($this->source_stmt['class_from_field']))
        {
            $refering_obj = $this->first_belongs_to_second ? 'second_obj' : 'first_obj';
            $other_class = str_replace('obj', 'class', $refering_obj);
            $this->first_key = $this->$refering_obj->getKeyForRef($this->referer);
            $this->second_key = $this->$refering_obj->getForeignKeyForRefOn($this->referer, $this->$other_class);
        }
        else if($this->second_belongs_to_first) //second object belongs to first one 
        {
            $this->first_key = $this->second_obj->getForeignTableKey($this->source);
            $this->second_key = $this->second_obj->getForeignKeyFor($this->source);
        }
        else if($this->first_belongs_to_second) //first object belongs to second one
        {
            $this->first_key = $this->first_obj->getForeignKeyFor($this->source);
            $this->second_key = $this->first_obj->getForeignTableKey($this->source, $this->class_from_field_value);
        }
        else
        {
            throw new Exception(sprintf("The source between %s and %s could not be found", $this->first_class, $this->second_class));
        }
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getFirstTableAlias()
    {
        return $this->first_class;
    }

    public function getSecondTableAlias()
    {
        return $this->second_class;
    }

    public function getDirection()
    {
        return $this->direction;
    }

    public function getFirstObj()
    {
        return $this->first_obj;
    }

    public function getFirstClass()
    {
        return $this->first_class;
    }

    public function getSecondObj()
    {
        return $this->second_obj;
    }

    public function getSecondClass()
    {
        return $this->second_class;
    }

    public function getFirstKey()
    {
        return $this->first_key;
    }

    public function getSecondKey()
    {
        return $this->second_key;
    }

    public function getReferer()
    {
        return $this->referer;
    }

    public function needToload()
    {
        return $this->load;
    }

    public function getSqlBuilderInfos()
    {
        return array($this->first_class->getTable() => $this->first_key, $this->second_class->getTable() => $this->second_key);
    }

    public function usesClass($class)
    {
        return $this->first_class == $class || $this->second_class == $class;
    }

    public function secondIsABelongsToFor($first_class)
    {
        return $this->first_class == $first_class && $this->first_belongs_to_second;
    }

    public function isTheSameAs(MormJoin $join)
    {
        return $join->getFirstClass() == $this->first_class && $join->getSecondClass() == $this->second_class && $join->getSource() == $this->source;
    }
}
