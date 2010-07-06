<?php

class MorphinxSource
{
    public $name;

    public $index_name;

    public $pre_queries = array();

    public $fetch_query = '';

    public $query_range = '';

    public $cli_query = '';

    public $attributes = array();

    private $model;

    private $index_def;

    private $group_by = null;

    public $type = 'mysql';

    public function __construct($model_name, $index = null)
    {
        $this->name = $model_name;
        $this->index_name = $this->name;
        $this->model = MormDummy::get($model_name);
        $this->setIndexDef($index);
        $this->setSqlConf();
        $this->setSqlPreQueries();
        $this->setFetchQuery();
        $this->setQueryRange();
        $this->setAttributes();
        $this->setCliQuery();
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setIndexDef($index = null)
    {
        if(!is_null($index)) $this->index_name .= '_'.$index;
        $this->index_def = $this->model->getMorphinxIndex($index);
    }

    private function setSqlConf()
    {
        $conf = Config::instance(); 
        $config = $conf['db'];

        $this->sql_host = $config['DB_HOST'];
        $this->sql_user = $config['DB_USER'];
        $this->sql_pass = $config['DB_PASSWORD'];
        $this->sql_db = $config['DB_NAME']; 
        $this->sql_port = 3306; //TODO get from conf
    }

    private function setSqlPreQueries()
    {
        $this->pre_queries []= "SET NAMES 'utf8'";
        $this->pre_queries []= "SET lc_time_names = 'fr_FR'";
    }

    private function setFetchQuery()
    {
        $this->fetch_query = str_replace("\n", ' ', "SELECT `".$this->name."`.`".$this->model->getPkey()."` * %d + %d as id, ".
                        "'".$this->name."' as `class_".$this->name."`, ".
                        '`'.$this->name."`.`".$this->model->getPkey()."` as `".$this->name."_id`, ".
                        SqlBuilder::select($this->getSelectedFields()).
                        $this->getSortableAttrFields().
                        $this->getWithAttrFields().
                        " FROM ".SqlBuilder::from(array($this->name => $this->model->_table)).
                        SqlBuilder::joins($this->buildJoins())." ".
                        $this->getWithSqlJoins()." ".
                        $this->getSqlJoins()." ".
                        SqlBuilder::where($this->getConditions(), $this->getSqlConditions())).' '.
                        SqlBuilder::group_by($this->getGroupBy());
    }

    private function setQueryRange()
    {
        $this->query_range = "SELECT IFNULL(MIN(`".$this->model->getPkey()."`), 1), IFNULL(MAX(`".$this->model->getPkey()."`), 1) FROM `".$this->model->_table."`";
    }

    private function setAttributes()
    {
        $index = $this->index_def;
        /**
         * always want to have these attributes 
         */
        $this->attributes []= array('name' => 'class_'.$this->name, 'type' => 'sql_attr_str2ordinal');

        $this->attributes []= array('name' => $this->name.'_id', 
                                    'type' => 'sql_attr_uint');
        $this->setDefinedAttributes();
        $this->setSortableAttributes();
        $this->setManualAttributes();
        $this->setWithAttributes();
    }

    private function setDefinedAttributes()
    {
        $index = $this->index_def;
        if (isset($index['attributes']))
        {
            foreach($index['attributes'] as $attr_name)
            {
                $table = $this->getAttributeTable($attr_name);
                $this->attributes []= array(
                                      'name' => str_replace('`', '', SqlBuilder::selectAlias($table, $attr_name)),
                                      'type' => $this->getAttributeType($attr_name)
                                      );
            }
        }
    }

    private function setSortableAttributes()
    {
        $index = $this->index_def;
        if (isset($index['sortable'])) 
        {
            $sortable = is_array($index['sortable']) ? $index['sortable'] : array($index['sortable']); 
            foreach($sortable as $key => $attr_name)
            {
                $table = is_numeric($key) ? $this->name : $key; 
                $this->attributes []= array(
                                      'name' => $table.'_'.$attr_name.'_ord',
                                      'type' => 'sql_attr_str2ordinal'
                                      );
            }
        }
    }

    private function setManualAttributes()
    {
        $index = $this->index_def;
        if (isset($index['manual_attributes'])) 
        {
            $this->attributes = array_merge($this->attributes, $index['manual_attributes']);
        }
    }

    private function setWithAttributes()
    {
        $index = $this->index_def;
        if(!isset($index['with'])) return '';
        $with = is_array($index['with']) ? $index['with'] : array($index['with']);
        $ret = array();
        foreach($with as $has_many => $values)
        {
            if(is_numeric($has_many))
            {
                $has_many = $values;
                $values = array();
            }
            //$has_many_stmt = $this->model->getHasManyStatement($has_many);
            if(isset($values['key']))
                $field = $values['key'];
            else
                $field = MormDummy::get($this->model->getForeignMormonsClass($has_many))->getPkey();
            $attr_alias = $has_many.'_'.$field;
            $this->attributes []= array('name' => sprintf("uint %s from field", $attr_alias),
                                        'type' => 'sql_attr_multi');
        }
    }

    /**
     * getAttributeType 
     *
     * TODO get the type from the SQL type of the field
     * 
     * @param mixed $attr_name 
     * @return void
     */
    private function getAttributeType($attr_name)
    {
        return 'sql_attr_uint';
    }

    private function getAttributeTable($attr_name)
    {
        $index = $this->index_def;
        foreach($index['fields'] as $key => $value)
        {
            if(!is_numeric($key))
            {
                if(is_array($value) && in_array($attr_name, $value))
                    return $key;
                if($value == $attr_name)
                    return $key;
            }
            if($value == $attr_name)
                return $this->name;
        }
        throw new Exception('The attribute "'.$attr_name.'" should be in the selected fields');
    }

    private function setCliQuery()
    {
    }

    private function getSortableAttrFields()
    {
        $index = $this->index_def;
        if(!isset($index['sortable'])) return '';
        $ret = array();
        $fields = is_array($index['sortable']) ? $index['sortable'] : array($index['sortable']); 
        foreach($fields as $key => $attr_name)
        {
            $table = is_numeric($key) ? $this->name : $key; 
            $ret[] = 'UPPER('.$table.'.'.$attr_name.') as `'.$table.'_'.$attr_name.'_ord`';
        }
        return empty($ret) ? '' : ', '.implode(', ', $ret);
    }

    private function getWithAttrFields()
    {
        $index = $this->index_def;
        if(!isset($index['with'])) return '';
        $with = is_array($index['with']) ? $index['with'] : array($index['with']);
        $ret = array();
        foreach($with as $has_many => $values)
        {
            $this->setGroupBy();
            if(is_numeric($has_many))
            {
                $has_many = $values;
                $values = array();
            }
            //$has_many_stmt = $this->model->getHasManyStatement($has_many);
            if(isset($values['key']))
                $field = $values['key'];
            else
                $field = MormDummy::get($this->model->getForeignMormonsClass($has_many))->getPkey();
            $table_alias = $has_many;
            $attr_alias = $table_alias.'_'.$field;
            $ret []= sprintf(" GROUP_CONCAT(DISTINCT IFNULL(%s.%s, '0') SEPARATOR ',') AS `%s` ", $table_alias, $field, $attr_alias);
        }
        return is_array($ret) ? ','.implode(",", $ret) : '';
    }

    private function getSelectedFields()
    {
        $index = $this->index_def;
        $ret = array();
        $fields = $index['fields'];
        foreach($fields as $key => $value)
        {
            if(!is_numeric($key))
            {
                $ret[$key] = $value;
            }
            else
            {
                if(isset($ret[$this->name]) && !is_array($ret[$this->name]))
                    $ret[$this->name] = array();
                $ret[$this->name] []= $value;
            }
        }
        return $ret;
    }

    private function buildJoins()
    {
        $index = $this->index_def;
        $ret = array();
        foreach($index['fields'] as $key => $value)
        {
            if(is_string($key))
            {
                $ret []= new MormJoin(get_class($this->model), $key);
            }
        }
        return $ret;
    }

    private function getWithSqlJoins()
    {
        $index = $this->index_def;
        if(!isset($index['with'])) return '';
        $with = is_array($index['with']) ? $index['with'] : array($index['with']);
        $ret = array();
        foreach($with as $has_many => $values)
        {
            if(is_numeric($has_many))
            {
                $has_many = $values;
                $values = array();
            }
            $f_class = $this->model->getForeignMormonsClass($has_many);
            $j = new MormJoin(get_class($this->model), $f_class);
            $ret []= sprintf(" %s JOIN `%s` AS `%s` ON `%s`.`%s`=`%s`.`%s` %s ", 
                             $j->getDirection(),
                             $j->getSecondObj()->getTable(),
                             $has_many,
                             $j->getFirstTableAlias(),
                             $j->getFirstKey(),
                             $has_many,
                             $j->getSecondKey(),
                             sqlBuilder::joinConditions($j->getConditions()));
        }
        return implode("\n", $ret);
    }

    private function getSqlJoins()
    {
        $index = $this->index_def;
        if(isset($index['sql_joins']))
            return $index['sql_joins'];
        return '';
    }

    private function getConditions()
    {
        $conditions = array();
        if($sti_field = $this->model->getStiField()) // there is an sti, we should now add a condition
            $conditions = array($this->name => array($sti_field => $this->model->getStiValue()));
        $index = $this->index_def;
        if(isset($index['conditions']))
            return array_merge($conditions, $index['conditions']);
        return $conditions;
    }

    private function getSqlConditions()
    {
        $index = $this->index_def;
        $sql_conditions = array("`".$this->name."`.`".$this->model->getPkey().'` >= $start', 
                                "`".$this->name."`.`".$this->model->getPkey().'` <= $end');
        if(isset($index['sql_conditions']))
            $sql_conditions = array_merge($sql_conditions, $index['sql_conditions']);
        return implode(' AND ', $sql_conditions);
    }

    private function getGroupBy()
    {
        $index = $this->index_def;
        if(is_null($this->group_by))
        {
            if(isset($index['group']))
            {
                if(is_array($index['group']))
                    $this->group_by = $index['group'];
                else
                    $this->group_by = array($this->name => $index['group']);
            }
        }
        return $this->group_by;
    }

    private function setGroupBy()
    {
        if(is_null($this->group_by))
        {
            $this->group_by = array($this->name => $this->model->getPkey());
        }
    }
}
