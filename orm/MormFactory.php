<?php
/**
 * Like Factory girl but for php and much smaller and simpler
 */
class MormFactory
{

    private $class_name = NULL;

    private $fields = NULL;

    public function __construct($class_name, array $fields = array()) 
    {
        $this->class_name = $class_name;
        $this->fields = $fields;
    }

    public function build()
    {
        $instance = new $this->class_name();
        $this->fillValues($instance, $this->fields);
        $this->fillOtherValues($instance);
        return $instance;
    }

    public function create()
    {
        $instance = new $this->class_name();
        $this->fillValues($instance, $this->fields);
        $this->fillOtherValues($instance);
        $instance->save();
        return $instance;
    }

    protected function fillValues($instance, $fields)
    {
        foreach ($fields as $field => $value)
        {
            if ($instance->isField($field))
            {
                $instance->$field = $value;
            }
        }
    }

    protected function fillOtherValues($instance)
    {
        $tabledesc = $instance->getTableDesc();
        $fields = $tabledesc->getFields();
        $autoincrement = $tabledesc->hasAutoIncrement();
        foreach ($fields as $field_name => $field)
        {
            $value = NULL;
            if (!$field->hasDefaultValue() && MormUtils::isEmpty($instance->$field_name))
            {
                if ($field->type == 'enum' || $field->type == 'set')
                {
                    // random is much funny
                    $value = $field->values[rand(0, (count($field->values) - 1))];
                }
                elseif ($field->php_type == 'string')
                {
                    $value = md5(rand(0, 10000));
                } 
                elseif ($field->isNumeric() && !$field->isAutoIncrement())
                {
                    $value = rand();
                }
            }
            $instance->$field_name = $value;
        }
    }
}
