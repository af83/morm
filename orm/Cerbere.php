<?php

Class Cerbere extends Mormons
{
    private $keyword = null;

    private $sphinx_search = null;
    private $sphinx_results = null;
    private $sphinx_args = null;
    private $_sphinx_executed = false;

    public function __construct($init, $keyword)
    {
        $this->keyword = $this->add_joker($keyword);
        $class = $init;
        $index = '';
        if(is_array($init))
        {
            $class = key($init);
            $index = '_'.$init[$class];
        }
        parent::__construct($class);
        $this->sphinx_args['conf'] = array('index' => $this->base_class.$index.'_index');
    }

    public function paginate($page, $per_page)
    {
        $offset = ( intval($page) - 1 ) * $per_page;
        $this->sphinx_args['pagination'] = array($offset, $per_page);
    }

    public function offset($offset)
    {
        $this->sphinx_args['pagination'][0] = intval($offset);
    }

    public function limit($limit)
    {
        $this->sphinx_args['pagination'][1] = intval($limit);
    }

    public function conditions($conditions)
    {
        $index = $this->base_object->getMorphinxIndex();
        foreach($conditions as $field => $condition)
        {
            /**
             * TODO decide what to do with conditions not included in index 
             */
            if(in_array($field, $index['fields']))
            {
                $this->setSphinxArg('conditions', array($this->getMorphinxFieldName($field) => $condition));
            }
            if(isset($index['manual_attributes']))
            {
                foreach($index['manual_attributes'] as $attr)
                {
                    if($attr['name'] == $field)
                        $this->setSphinxArg('conditions', array($field => $condition));
                }
            }
        }
    }

    public function executeSphinxQuery()
    {
        $this->sphinx_search = new MorphinxSearch($this->sphinx_args);
        $this->sphinx_results = $this->sphinx_search->search($this->keyword);
        if(!$this->sphinx_results)
        {
            //TODO throw Exception with informations from sphinx
            throw new Exception('Sphinx failed');
        }
        $this->_sphinx_executed = true;
    }

    public function execute()
    {
        $this->executeSphinxQuery();
        if(!empty($this->sphinx_results['matches']))
        {
            $ids = array();
            foreach($this->sphinx_results['matches'] as $match)
            {
                $ids []= $match['attrs'][strtolower($this->base_class).'_id']; 
            }
            $this->add_conditions(array($this->base_object->getPkey() => $ids));
            parent::execute();
        }
        else
        {
            $this->_executed = true;
            $this->mormons = array();
            $this->mormon_keys = array_keys($this->mormons);
            $this->nb_mormons = 0;
        }
    }

    public function weights($weights)
    {
        foreach($weights as $field => $weight)
        {
            $f = $this->getMorphinxFieldName($field);
            $this->sphinx_args['weights'][$f] = $weight;
        }
    }

    public function setSphinxArg($name, $value)
    {
        if(isset($this->sphinx_args[$name]) && is_array($this->sphinx_args[$name]))
            $this->sphinx_args[$name] = array_merge($this->sphinx_args[$name], $value);
        else
            $this->sphinx_args[$name] = $value;
    }

    public function getMorphinxFieldName($field)
    {
        $table = $this->getMorphinxTableForField($field);
        if(is_null($table)) return $field;
        return str_replace('`', '', SqlBuilder::selectAlias($table, $field));
    }

    public function getMorphinxTableForField($field)
    {
        $index = $this->base_object->getMorphinxIndex();
        foreach($index['fields'] as $key => $value)
        {
            if(!is_numeric($key))
            {
                if(is_array($value) && in_array($field, $value))
                    return $key;
                if($value == $field)
                    return $key;
            }
            if($value == $field)
                return $this->base_class;
        }
    }

    public function get_count($with_limit = false)
    {
        if(!$this->_sphinx_executed) $this->executeSphinxQuery();
        return $this->sphinx_results['total_found'];
    }

    public function add_joker($keystring)
    {
        $newkeystring = str_replace(' ', '* ', mysql_real_escape_string($keystring)) . '*';
        return $newkeystring;
    }
}
