<?php

class MorphinxSource
{
    public $name;

    public $pre_queries = array();

    public $fetch_query = '';

    public $query_range = '';

    public $cli_query = '';

    public $attributes = array();

    private $model;

    public $type = 'mysql';

    public function __construct($model_name)
    {
        $this->name = $model_name;
        $this->model = new $model_name();
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

    private function setSqlConf()
    {
        //FIXME find a better way to get the conf, this one is sooooo bad
        $config = new Config(SITEBASE .'/conf/conf.ini', $_SERVER["HTTP_HOST"]);

        $this->sql_host = $config->dbserver;
        $this->sql_user = $config->dbuser;
        $this->sql_pass = $config->dbpass;
        $this->sql_db = $config->dbname; 
        $this->sql_port = 3306; //TODO get from conf
    }

    private function setSqlPreQueries()
    {
        $this->pre_queries []= "SET NAMES 'utf8'";
        $this->pre_queries []= "SET lc_time_names = 'fr_FR'";
    }

    private function setFetchQuery()
    {
        $this->fetch_query = "SELECT `".$this->model->_table."`.`".$this->model->getPkey()."` * %d + %d as id, ".
                        "'".$this->name."' as `class_".$this->name."`, ".
                        '`'.$this->model->_table."`.`".$this->model->getPkey()."` as `".$this->name."_id`, ".
                        af_orm_SqlBuilder::select($this->getSelectedFields()).
                        " FROM ".af_orm_SqlBuilder::from($this->model->_table).
                        af_orm_SqlBuilder::joins($this->buildJoins(), 'LEFT')." ".
                        $this->getSqlJoins()." ".
                        af_orm_SqlBuilder::where($this->getConditions(), $this->getSqlConditions());
    }

    private function setQueryRange()
    {
        $this->query_range = "SELECT IFNULL(MIN(`".$this->model->getPkey()."`), 1), IFNULL(MAX(`".$this->model->getPkey()."`), 1) FROM `".$this->model->_table."`";
    }

    private function setAttributes()
    {
        $index = $this->model->getMorphinxIndex();
        /**
         * always want to have these attributes 
         */
        $this->attributes []= array('name' => 'class_'.$this->name, 'type' => 'sql_attr_str2ordinal');

        $this->attributes []= array('name' => $this->name.'_id', 
                                    'type' => 'sql_attr_uint');
        if(isset($index['attributes']))
        {
            foreach($index['attributes'] as $attr_name)
            {
                $table = $this->getAttributeTable($attr_name);
                $this->attributes []= array(
                                      'name' => str_replace('`', '', af_orm_SqlBuilder::selectAlias($table, $attr_name)),
                                      'type' => $this->getAttributeType($attr_name)
                                      );
            }
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
        $index = $this->model->getMorphinxIndex();
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
                return $this->model->_table;
        }
        throw new Exception('The attribute "'.$attr_name.'" should be in the selected fields');
    }

    private function setCliQuery()
    {
    }

    private function getSelectedFields()
    {
        $index = $this->model->getMorphinxIndex();
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
                if(isset($ret[$this->model->_table]) && !is_array($ret[$this->model->_table]))
                    $ret[$this->model->_table] = array();
                $ret[$this->model->_table] []= $value;
            }
        }
        return $ret;
    }

    private function buildJoins()
    {
        $index = $this->model->getMorphinxIndex();
        $ret = array();
        foreach($index['fields'] as $key => $value)
        {
            if(is_string($key))
                $ret []= $this->model->getJoinFor($key);
        }
        return $ret;
    }

    private function getSqlJoins()
    {
        $index = $this->model->getMorphinxIndex();
        if(isset($index['sql_joins']))
            return $index['sql_joins'];
        return '';
    }

    private function getConditions()
    {
        $index = $this->model->getMorphinxIndex();
        if(isset($index['conditions']))
            return $index['conditions'];
        return array();
    }

    private function getSqlConditions()
    {
        $index = $this->model->getMorphinxIndex();
        $sql_conditions = array("`".$this->model->_table."`.`".$this->model->getPkey().'` >= $start', 
                                "`".$this->model->_table."`.`".$this->model->getPkey().'` <= $end');
        if(isset($index['sql_conditions']))
            $sql_conditions = array_merge($sql_conditions, $index['sql_conditions']);
        return implode(' AND ', $sql_conditions);
    }
}
