<?php
/**
 * Morm 
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class Morm 
{
    /**
     * @access protected
     * @var string
     */
    protected $_table;

    /**
     * @access public
     */
    private $_original = array();

    /**
     * @access private
     */
    private $_fields;

    /**
     * @access private
     */
    private $_errors;

    /**
     * @access protected
     * @var array
     */
    protected $_belongs_to = array();

    /**
     * @access private
     */
    private $_foreign_values;

    /**
     * @access private
     */
    private $_foreign_object;
    
    private $_table_desc;
    
    /**
     * @access protected
     * @var array
     */
    protected $_has_many = array();

    protected $_filters = array();

    /**
     * @access private
     */
    private $_foreign_mormons;

    /**
     * dummy 
     *
     * used to prevent changes on the object
     * 
     * @var mixed
     */
    private $dummy = false;

    /**
     * @access private
     */
    private $_associated_mormons = null;

    /**
     * @access public
     */
    public $_columns;

    /**
     * @access public
     */
    protected $_pkey = null;

    /**
     * @access protected
     * @var array
     */
    protected $mandatory_errors = array();

    /**
     * @access protected
     * @var array
     */
    protected $type_errors = array();

    /**
     * @access protected
     * @var array
     */
    protected $access_level = array( 
                                   'read' => array(),
                                   'write' => array(),
                                   );
                                   
    protected $is_new = TRUE;

    /**
     * sti_field 
     * 
     * default STI field is 'type'
     *
     * @var string
     */
    protected $sti_field = 'type';

    /**
     * _plugin
     *
     * list of loaded plugins
     *
     * @access protected
     * @var array
     */
    protected static $_plugin = array();

    /**
     * _plugin_method
     *
     * list of methods loaded through plugins
     *
     * @access protected
     * @var array
     */
    protected static $_plugin_method = array();

    /**
     * _plugin_options
     *
     * plugins constructors options
     *
     * @access protected
     * @var array
     */
    protected static $_plugin_options = array();


    protected $lazy_loading_limit = 50;

    /**
     * Constructor. 
     * 
     * load model from 
     * - primary key (can be an array if the primary key is made of mutliple 
     * fields)
     * - array of values (typically from a mysql_fetch_assoc)
     *
     * @param mixed $to_load
     */
    public function __construct ($to_load = null)
    {
        //set the primary key name for the table if there's one
        $this->setPKey();
        if(!is_null($to_load))
        {
            if(is_array($to_load))
            {
                if(is_array($this->_pkey) && count(array_diff(array_keys($to_load),$this->_pkey)) == 0)
                    $this->loadByPKey($to_load);
                else
                    $this->loadFromArray($to_load);
            }
            else if(!is_null($this->_pkey) && !$this->isEmpty($to_load))
                $this->loadByPKey($to_load);
        }
    }

    /**
     * __set 
     * 
     * if $name is a field, set $this->_fields[$name]
     * else just acts as a normal setter
     *
     * @param mixed $name 
     * @param mixed $value 
     * @return void
     */
    public function __set($name, $value)
    {
        if($this->dummy) return;
        if($this->isField($name))
            $this->_fields[$name] = $value;
        else
            $this->$name = $value;
    }

    public function __get ($name)
    {
        if($this->isField($name))
            return isset($this->_fields[$name]) ? $this->_fields[$name] : NULL ;
        $matches = array();
        $no_cache = false;
        if(preg_match('/^(.+)Clone$/', $name, $matches))
        {
            $name = $matches[1];
            $no_cache = true;
        }
        if($this->isForeignObject($name))
            return $this->getForeignObject($name, $no_cache);
        if($this->isForeignMormons($name)) 
            return $this->getManyForeignObjects($name, $no_cache);
        if(method_exists($this, $method_name = 'get'.str_replace(' ', '', ucwords(str_replace('_', ' ', $name))))) {
            /**
             * if nothing worked before, try to see if the method called get<CamelCased($name)> exists
             * if it does, call it and return the result
             */
            return $this->$method_name();
        }
        return NULL;
    }

    public function __call($method, $args)
    {
        // Check plugins
        if(self::$_plugin_method[$method])
        {
            $plugin_class = self::$_plugin_method[$method];
            $plugin_options = self::$_plugin_options[$plugin_class];
            $plugin = new $plugin_class ($this, $plugin_options);
            if(is_callable(array($plugin, $method)))
                return call_user_func_array(array($plugin, $method), $args);
        }
    }

    /**
     * isNew 
     * 
     * true if $this->_original[pkey] is not set 
     *
     * @return boolean
     */
    public function isNew() {
        return $this->is_new;
    }

    public function hasFieldChanged($field) {
        
        return $this->isNew() || !isset($this->_original[$field]) || $this->$field != $this->_original[$field];
        
    }
    
    public function setAsDummy()
    {
        $this->dummy = true;
    } 

    /**
     * save 
     *
     * if model is new, inserts a row in the database
     * if model is not new, tries to update the row
     *
     * @uses Morm::validate()
     * @uses SqlTools::sqlQuery()
     * @throws MormValidateException through Morm::validateException()
     * @throws MormSqlException through SqlTools::sqlQuery()
     * @param boolean $validate
     * @return boolean
     */
    public function save ($validate=true)
    {
        $valid = $validate ? $this->validate() : true;
        if($valid)
        {
            $this->castFields();
            if(!$this->isNew())
            {      
                if(count($this->fieldsToUpdate()) > 0)
                {
                    $ret = SqlTools::sqlQuery($this->createUpdateSql());
                    if($ret) {
                        $this->_original = $this->_fields;
                        $this->is_new = FALSE;
                    }
                    return $ret;
                }

                return true;
            }
            else
            {
                $sql = $this->createInsertSql();
                $ret = SqlTools::sqlQuery($sql);
                if($ret) { 
                    $this->is_new = FALSE;
                }
                if($ret && $this->hasAutoIncrement()) {
                    $this->_fields[$this->getTableDesc()->getAutoIncrementField()] = mysql_insert_id();
                    $this->_original = $this->_fields;
                }
                return $ret;
            }
        }
        return false;
    }


    public function unJoin($has_many, $foreign_key_value)
    {
        //@todo create specific exception
        if(!$this->isForeignMormons($has_many))
            throw new Exception(get_class($this)." does not have many ".$has_many."s");
        if(isset($this->_has_many[$has_many]['using']))
        {
            $previous_class = null;
            $dummy_id = array();
            foreach($this->_has_many[$has_many]['using'] as $using_class => $using_opts)
            {
                if(is_integer($using_class)) //nothing else than the joining class
                {
                    $using_class = $using_opts;
                    $using_opts = array();
                } 
                $first_class = is_null($previous_class) ? $this : $previous_class;
                $using_opts['referer'] = $has_many;
                $join = new MormJoin($first_class, $using_class, $using_opts); 
                $dummy_id[$join->getSecondKey()] = $join->getFirstObj()->{$join->getFirstKey()}; 
                $previous_class = $using_class;
                if(count($dummy_id) == 2)
                {
                    $dummy = new $previous_class($dummy_id);
                    //$dummy->delete();
                    $dummy_id = array();
                }
            }
            $join = new MormJoin($previous_class, $this->_has_many[$has_many]['class'], $this->_has_many[$has_many]);
            $dummy_id[$join->getSecondKey()] = $foreign_key_value; 
            $dummy = new $previous_class($dummy_id);
            $dummy->delete();
        }
        else
        {
            $class_name = $this->getForeignMormonsClass($has_many);
            $f_key = $this->getForeignKeyForRefOn($has_many, $class_name);
            $dummy = new $class_name($foreign_key_value);
            //what should we do here ? Set to zero is certainly not a good idea
            $dummy->$f_key = $dummy->getTableDesc()->$f_key->Null == 'NO' ? 0 : null;
            $dummy->save();
        }
    }

    public function joinWithMorm($has_many, $foreign_key_value)
    {
        //@todo create specific exception
        if(!$this->isForeignMormons($has_many))
            throw new Exception(get_class($this)." does not have many ".$has_many."s");
        if(isset($this->_has_many[$has_many]['using']))
        {
            $previous_class = null;
            $to_set = array();
            foreach($this->_has_many[$has_many]['using'] as $using_class => $using_opts)
            {
                if(is_integer($using_class)) //nothing else than the joining class
                {
                    $using_class = $using_opts;
                    $using_opts = array();
                } 
                $first_class = is_null($previous_class) ? $this : $previous_class;
                $using_opts['referer'] = $has_many;
                $join = new MormJoin($first_class, $using_class, $using_opts); 
                $to_set[$join->getSecondKey()] = $join->getFirstObj()->{$join->getFirstKey()}; 
                $previous_class = $using_class;
                if(count($to_set) == 2)
                {
                    $dummy = new $previous_class();
                    $dummy->setFromArray($to_set);
                    $dummy->save();
                    $to_set = array();
                }
            }
            $join = new MormJoin($previous_class, $this->_has_many[$has_many]['class'], $this->_has_many[$has_many]);
            $to_set[$join->getSecondKey()] = $foreign_key_value; 
            $dummy = new $previous_class();
            $dummy->setFromArray($to_set);
            $dummy->save();
        }
        else
        {
            $class_name = $this->getForeignMormonsClass($has_many);
            $f_key = $this->getForeignKeyForRefOn($has_many, $class_name);
            $dummy = new $class_name($foreign_key_value);
            $dummy->$f_key = $this->{$this->_pkey};
            $dummy->save();
        }
    }

    public function isFilter ($filter)
    {
        if(isset($this->_filters[$filter]))
            return $this->_filters[$filter];
        return false;
    }
    
    public function getFilter ($filter_name)
    {
        return $this->_filters[$filter_name];
    }


    public function getAccessLevel()
    {
        return $this->access_level;
    }
    /**
     * update 
     *
     * alias on save
     *
     * @param boolean $validate
     *   
     * @return boolean
     */
    public function update ($validate = true)
    {
        return $this->save($validate);
    }

    /**
     * delete 
     *
     * delete row corresponding to the models pkey
     * 
     * @return boolean
     */
    public function delete ($cascading_delete = false)
    {
        if($cascading_delete !== false)
        {
            $has_many_to_delete = $cascading_delete === true ? array_keys($this->_has_many): $cascading_delete;
            foreach($has_many_to_delete as $to_delete)
            {
                foreach($this->getManyForeignObjects($to_delete) as $to_unjoin)
                {
                    $this->unJoin($to_delete, $to_unjoin->{$to_unjoin->_pkey});
                }
            }
        }
        return SqlTools::sqlQuery($this->createDeleteSql());
    }

    /**
     * loadByPKey 
     * 
     * called by __construct
     * fills _fields and _original arrays with row's values
     *
     * @access private
     * @throws NoPrimaryKeySqlException
     * @param mixed $pkey 
     * @return void
     */
    private function loadByPKey ($pkey)
    {
        $rs = SqlTools::sqlQuery("select * from `".$this->_table."` ".$this->createIdentifyingWhereSql($pkey));
        if($rs && mysql_num_rows($rs) > 0) 
        {
            $this->_original = mysql_fetch_assoc($rs);
            foreach($this->getTableDesc() as $field => $field_desc)
            {
                settype($this->_original[$field], $field_desc->php_type);
            }
            $this->_fields = $this->_original; 
            $this->is_new = FALSE;
        }
        else
            throw new NoPrimaryKeySqlException($pkey, $this->_table);
    }

    /**
     * loadFromArray
     *
     * called by __construct
     * fills _fields and _original arrays with row's values
     * load Foreign Objects associated to this if needed
     * 
     * @access private
     * @param array $array 
     * @return void
     */
    private function loadFromArray ($array)
    {
        $foreign_to_load = array();
        foreach($array as $field => $value)
        {
            $matches = explode(MormConf::MORM_SEPARATOR, $field);
            if($matches[0] != $field)
            {
                if($this->isForeignTable($matches[1]))
                {
                    $f_key = $this->getForeignKeyFromTable($matches[1]);
                    $foreign_to_load[$f_key][$matches[2]] = $value;
                }
                else if($matches[1] == $this->_table)
                    $field = $matches[2];
            }
            if($this->isField($field))
            {
                $field_desc = $this->getFieldDesc($field);
                $this->_original[$field] = $value;
                settype($this->_original[$field], $field_desc->php_type);
            }
        }   
        foreach($foreign_to_load as $f_key => $to_load)
            $this->loadForeignObject($f_key, $to_load);
        $this->_fields = $this->_original;
        $this->is_new = FALSE;
    }

    /**
     * associateWithMormons 
     * 
     * @param Mormons $mormons
     * @return void
     */
    public function associateWithMormons(Mormons $mormons)
    {
        if(!is_null($this->_associated_mormons)) throw new Exception("A model can only be associated with one Mormons instance");
        $this->_associated_mormons = $mormons;
    }

    /**
     * loadFromMormons 
     * 
     * @todo documentation
     * @param array $array 
     * @return void
     */
    public function loadFromMormons ($to_load, $super_class, $joins)
    {
        foreach($this->getTableDesc() as $field_name => $field_desc)
        {
            $morm_name = isset($to_load[$field_name]) ? $field_name : implode(MormConf::MORM_SEPARATOR, array(MormConf::MORM_PREFIX, $super_class, $field_name));
            if(isset($to_load[$morm_name]))
            {
                    $this->_original[$field_name] = $to_load[$morm_name];
                    settype($this->_original[$field_name], $field_desc->php_type);
                    $this->_fields[$field_name] = $this->_original[$field_name]; 
            }
        }
        foreach($joins as $join)
        {
            if($join->secondIsABelongsToFor(get_class($this)))
            {
                $should_load = extractMatchingKeys(implode('\\'.MormConf::MORM_SEPARATOR, array(MormConf::MORM_PREFIX, $join->getSource(), '(\w+)')), $to_load);
                if(!empty($should_load))
                {
                    //$this->loadForeignObject($join->getSource(), $to_load);//FIXME should I do what I'm doing next in loadForeignObject ???
                    $this->_foreign_object[$join->getSource()] = self::Factory($join->getSecondClass(), $should_load);
                }
            }
        }
        $this->is_new = FALSE;
    }

    /**
     * setFromArray 
     *
     * sets the models values using a hash
     * the hash does not need to have every field values set.
     * 
     * @param array $array
     * @return void
     */
    public function setFromArray ($array)
    {
        foreach($array as $field => $value)
        {
            if($this->isField($field))
                $this->$field = $value;
        }   
    }

    /**
     * isForeignKey 
     *
     * check if the given field has been declared as a foreign key
     * 
     * @param string $field 
     * @return boolean
     */
    public function isForeignKey ($field, $name = false)
    {
        if($this->isField($field))
        {
            foreach($this->_belongs_to as $n => $stmt)
            {
                if($field == $stmt['key'])
                    return $name ? $n : true;
        }
        }
        else
            return false;
    }

    /**
     * isForeignClassFromField 
     * 
     * check if the given field has been declared as a class_from_field
     * that means that the class to load for that field is the value of that 
     * field in the row
     * typically used for an (object_type, object_id) couple used for social 
     * actions 
     *
     * @param string $field 
     * @return boolean
     */
    public function isForeignClassFromField ($field)
    {
        foreach($this->_belongs_to as $key => $val)
            if(isset($val['class_from_field']) && $val['class_from_field'] == $field)
                return true;
        return false;
    }

    /**
     * isForeignTable 
     *
     * check if the given table name has been declared as a foreign table.
     * 
     * @param string $table
     * @return boolean
     */
    public function isForeignTable ($table)
    {
        foreach($this->_belongs_to as $name => $stmt)
        {
            $class = isset($stmt['class']) ? $stmt['class'] : MormConf::getClassName($name);
            if($table == MormDummy::get($class)->getTable())
                return true;
        }
        return false;
    }
    
    public function isForeignClass ($class)
    {
        foreach($this->_belongs_to as $name => $stmt)
        {
            /**
             * @fixme
             * should not return true when class_from_field isset but should check in the table instead.
             * Either by checking for an enum in the class_from_field field or by doing a select distinct on that very field
             */
            if(isset($stmt['class_from_field'])) return true;
            $stmt_class = isset($stmt['class']) ? $stmt['class'] : MormConf::getClassName($name);
            if($class == $stmt_class)
                return true;
        }
        return false;
    }
    
    public function isForeignObject($name)
    {
        return isset($this->_belongs_to[$name]);
    }

    public function getBelongsToStmt($name)
    {
        return isset($this->_belongs_to[$name]) ? $this->_belongs_to[$name] : NULL;
    }
    
    public function isForeignUsing ($name)
    {
        return isset($this->_has_many[$name]) && isset($this->_has_many[$name]['using']);
    }

    public function isForeignUsingTable ($table)
    {
        foreach($this->_has_many as $key => $val)
        {
            if(isset($val['using']) && isset($val['using'][$table]))
                return true;
        }
        return false;
    }

    /**
     * isForeignMormons 
     * 
     * check if the given string has been declared as a key in the _has_many hash 
     *
     * @param string $has_many 
     * @return boolean
     */
    public function isForeignMormons ($has_many)
    {
        return isset($this->_has_many[$has_many]);
    }

    public function getForeignKeyFor($name)
    {
        return $this->_belongs_to[$name]['key'];
    }

    public function getKeyForRef($referer)
    {
        if(isset($this->_has_many[$referer]))
        {
            $key = null;
            if(isset($this->_has_many[$referer]['source']))
            {
                try {
                    $key = MormDummy::get($this->_has_many[$referer]['class'])->getForeignTableKey($this->_has_many[$referer]['source']);
                }
                catch (Exception $e)
                {
                    $key = null;
                    if(FALSE === strpos($e->getMessage(), 'Could not retrieve Foreign class from field'))
                        throw $e;
                }
            }
            if(is_null($key))
                $key = $this->getPKey();
            return $key;
        }
        throw new Exception(sprintf("%s referer was not found in %s", $referer, get_class($this)));
    }

    public function getForeignKeyForRefOn($referer, $on_class)
    {
        if(isset($this->_has_many[$referer]))
        {
            $key = null;
            if(isset($this->_has_many[$referer]['source']))
            {
                $source = $this->_has_many[$referer]['source'];
            }
            else
            {
                $sources_info = MormDummy::get($on_class)->findSourceFor($this);
                $source = $sources_info['source'];
            }
            return MormDummy::get($this->_has_many[$referer]['class'])->getForeignKeyFor($source);
        }
        throw new Exception(sprintf("%s referer was not found in %s", $referer, get_class($this)));
    }

    /**
     * getForeignKeyFromTable 
     * 
     * tries to find the field declared as a foreign key bound on the given 
     * table name and return it
     *
     * @param string $table
     * @return string
     * @throws Exception if foreign table is not for specified model
     */
    public function getForeignKeyFromTable ($table)
    {
        foreach($this->_belongs_to as $field => $val)
        {
            if($this->getBelongsToNameForTable($table))
                return $field;
            else if(isset($val['class_from_field']) && !empty($this->_fields[$val['class_from_field']]))
            {
                $class = $this->getForeignClassFromField($val['class_from_field']);
                if($table == MormDummy::get($class)->getTable()) return $field;
            }
        }
        //FIXME get the key from foreign model instead of returning this->_pkey 
        if($this->isForeignMormons($table)) return $this->_pkey;
        throw new Exception($table.' is not a foreign table for the model '.get_class($this));
    }

    public function getForeignKeyFromUsingTable($table_or_alias)
    {
        if(!$this->isForeignUsingTable($table_or_alias))
            throw new Exception(get_class($this).' does not use '.$table_or_alias.' indirectly');
        foreach($this->_has_many as $key => $val)
        {
            if(isset($val['using']) && isset($val['using'][$table_or_alias]))
            {
                $class_name = MormConf::getClassName($this->getForeignMormonsTable($key));
                continue;
            }
        }
        return MormDummy::get($class_name)->getForeignMormonsUsingKey($table_or_alias);
    }

    /**
     * getForeignTable 
     *
     * try to find the foreign table for the given field, assuming that this 
     * field has been declared as a foreign key
     * 
     * @param string $name 
     * @throws Exception if the field does not exist into table
     * @return string
     */
    public function getForeignTable ($name, Array $class_from_field_value = array())
    {
        if($this->isForeignObject($name))
        {
            if(isset($this->_belongs_to[$name]['class_from_field']))
                $class = $this->getForeignClassFromField($this->_belongs_to[$name]['class_from_field'], null, $class_from_field_value);
            else
                $class = $this->getForeignClass($name);
            return MormDummy::get($class)->getTable();
        }
        else
            throw new Exception($name.' is not a foreign object in class '.get_class($this));
    }

    /**
     * getForeignTableKey 
     *
     * tries to guess the foreign table key for the given field
     * either by taking the declared one in the foreign_keys hash
     * or by returning the primary key of the foreign table
     * 
     * @param string $field 
     * @throws Exception if field is not a foreign key in the table
     * @return string
     */
    public function getForeignTableKey ($name, Array $class_from_field_value = array())
    {   
        if($this->isForeignObject($name))
        {
            if(isset($this->_belongs_to[$name]['f_key']))
                return $this->_belongs_to[$name]['f_key'];
            else
                return TableDesc::getTable($this->getForeignTable($name, $class_from_field_value))->getPKey();
        }
        else
            throw new Exception($name.' is not a foreign object in class '.get_class($this));
    }

    /**
     * getForeignMormonsKey 
     *
     * tries to guess the field that should be used as a key to link the foreign 
     * table with the model. 
     * if a key is defined in the has_many hash, it is returned
     * else return this model's primary key
     * 
     * @param string $alias_or_table 
     * @throws Exception if the given alias is not defined as a foreign object 
     * @return string
     */
    public function getForeignMormonsKey ($name)
    {
        if($this->isForeignMormons($name))
        {
            if(isset($this->_has_many[$name]['using']))
            {
                $ret = array();
                foreach($this->_has_many[$name]['using'] as $using_class)
                {
                    $ret[$using_class] = $to_set['key'];
                }
                return $ret;
            }
            return isset($this->_has_many[$name]['key']) ? $this->_has_many[$name]['key'] : $this->_pkey;
        }
        else
            throw new Exception(get_class($this)." does not have many ".$name."s");
    }

    public function getForeignMormonsUsingKey ($alias_or_table)
    {
        if($this->isForeignUsingTable($alias_or_table))
        {
            foreach($this->_has_many as $key => $val)
            {
                if(isset($val['using']) && isset($val['using'][$alias_or_table]))
                    return $val['using'][$alias_or_table]['key'];
            }
        }
        else
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
    }

    /**
     * getForeignMormonsTable 
     * 
     * tries to guess the name of the table that should be used to join the 
     * model with the given alias defined in the has_many hash
     *
     * @param string $alias_or_table 
     * @throws Exception if the given alias is not defined as a foreign object 
     * @return string
     */
    public function getForeignMormonsTable ($alias_or_table)
    {
        if($this->isForeignMormons($alias_or_table))
        {
            return isset($this->_has_many[$alias_or_table]['table']) ? $this->_has_many[$alias_or_table]['table'] : $alias_or_table;
        }
        else
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
    }

    public function getForeignMormonsClass ($name)
    {
        if(!$this->isForeignMormons($name)) throw new Exception(get_class($this)." does not have many ".$name."s");
        return $this->_has_many[$name]['class'];
    }

    /**
     * getForeignClass 
     *
     * tries to return the class that should be loaded as a foreign object
     * 
     * @param string $name 
     * @throws Exception if field is not a foreign key into the table
     * @return string a valid class name
     */
    public function getForeignClass ($name, $to_load = null)
    {
        if($this->isForeignObject($name))
        {
            if(isset($this->_belongs_to[$name]['class']))
                return $this->_belongs_to[$name]['class'];
            if(isset($this->_belongs_to[$name]['class_from_field']))
                return $this->getForeignClassFromField($this->_belongs_to[$name]['class_from_field'], $to_load);
            return MormConf::getClassName($name);
        }
        else
        {
            throw new Exception($name.' is not a foreign object in class '.get_class($this));
        }
    }

    /**
     * getForeignClassFromField 
     * 
     * tries to return the class that should be loaded as a foreign object using 
     * the class_form_field statement in the foreign_key hash
     *
     * @access private
     * @param string $field 
     * @throws exception if the corresponding field is empty
     * @return string a valid class name
     */
    private function getForeignClassFromField($field, $to_load = null, Array $class_from_field_value = array())
    {
        if(!empty($this->_fields[$field]))
            return MormConf::getClassName($this->_fields[$field]);
        if(!isset($class_from_field_value[$field]))
            throw new Exception('Could not retrieve Foreign class from field '.$field);
        return MormConf::getClassName($class_from_field_value[$field]);
    }

    /**
     * getForeignObject 
     *
     * load and cache the foreign object corresponding to the given field
     * if the object has already been loaded, it is returned from cache
     * 
     * @param string $name 
     * @throws Exception if field is not a foreign key into the table
     * @return object Morm Model
     */
    public function getForeignObject ($name, $no_cache = false)
    {
        if($this->isForeignObject($name))
        {
            if($no_cache || !isset($this->_foreign_object[$name]))
            {
                $this->loadForeignObject($name);
            }
            return $this->_foreign_object[$name];
        }
        else
            throw new Exception($name.' is not a foreign object in class '.get_class($this));
    }

    /**
     * getManyForeignObjects 
     * 
     * load and cache the foreign models corresponding to the given field
     * if the object has already been loaded, it is returned from cache
     *
     * @param string $alias_or_table 
     * @throws exception if the given parameter has not been declared in the 
     * has_many hash
     * @return Mormons 
     */
    public function getManyForeignObjects ($name, $no_cache = false)
    {
        if($this->isForeignMormons($name))
        {
            if($no_cache || !isset($this->_foreign_mormons[$name])) //Check cache
            {
                $this->loadForeignMormons($name);
            }
            return $this->_foreign_mormons[$name];
        }
        else {
            
            throw new Exception(get_class($this)." does not have many ".$name."s");
        }
    }

    /**
     * loadForeignObject 
     * 
     * try to load the foreign object identified by the $field parameter.
     * either by requesting the database
     * or using the second parameter to load an Morm object
     * or use the second paramter as the foreign object itself and link it only
     * 
     * @param string $name declared in the belongs_to statement 
     * @param mixed $to_load (NULL, array or Morm object) 
     * @throws Exception if foreign object cannot be loaded
     * @throws Exception if field is not a foreign key into the table
     * @return void
     */
    public function loadForeignObject ($name, $to_load = null)
    {
        if($this->isForeignObject($name))
        {
            $foreign_class = $this->getForeignClass($name, $to_load);
            $key = $this->getForeignKeyFor($name);
            /**
             * FIXME, use Mormons or do something with a find method instead of writing down a query 
             */
            if(is_null($to_load))
            {
                $key_str = $this->isInteger($this->$key) ? $this->$key : "'".$this->$key."'";
                $sql = "SELECT * FROM `".$this->getForeignTable($name)."` WHERE `".$this->getForeignTableKey($name)."`=".$key_str;
                $rs = SqlTools::sqlQuery($sql);
                if($rs && mysql_num_rows($rs) != 0)
                {
                    $to_load = mysql_fetch_assoc($rs);
                }
                else
                {
                    $this->_foreign_object[$name] = NULL;
                    return;
                }
            }
            if(is_array($to_load))
            {
                $this->_foreign_object[$name] = self::Factory($foreign_class, $to_load);
            }
            else if (is_object($to_load) && $to_load->is_a($foreign_class))
                $this->_foreign_object[$name] = $to_load;
            else
                throw new Exception('Could not load foreign object '.$name.': wrong data to load');
        }
        else
            throw new Exception($name.' is not a foreign object in class '.get_class($this));
    }

    /**
     * loadForeignMormons 
     * 
     * try to load the foreign objects identified by the $alias_or_table parameter by requesting the database
     *
     * @throws Exception
     * @param string $alias_or_table 
     * @param mixed $to_load (NULL or Mormons object) FIXME strange never used parameter ?
     * @return void
     */
    public function loadForeignMormons ($name, $to_load = null)
    {
        if($this->isForeignMormons($name))
        {
            if(is_null($to_load))
            {
                $mormons = new Mormons($this->getForeignMormonsClass($name));
                $mormons->manageHasManyStatmt($name, $this, $this->_has_many[$name]);
                    //TODO manage multiple primary keys
                $mormons->associateForeignObject($name, $this);
                $this->_foreign_mormons[$name] = $mormons;
                }
            else
            {
                if(!isset($this->_foreign_mormons[$name]))
                    $this->loadForeignMormons($name);
                $this->_foreign_mormons[$name]->addMormFromArray($table, $to_load);
            }
        }
        else
            throw new Exception(get_class($this)." does not have many ".$alias_or_table."s");
    }

    /**
     * @throws Exception
     * @param string $table
     * @param mixed $to_load
     */
    /**
     * loadForeignObjectFromMormons 
     * 
     * same as loadForeignObject but from an associated mormon so that the 
     * objects keep a reference between themselves
     *
     * @throws exception if the given alias_or_table is not declared in the 
     * has_many hash
     * @param string $alias_or_table 
     * @param array $to_load 
     * @return void
     */
    public function loadForeignObjectFromMormons ($join, Array $to_load = array())
    {
        if(!is_null($join->getReferer()))
        {
            if(!isset($this->_foreign_mormons[$join->getReferer()]))
            {
                $mormons = new Mormons($join->getSecondClass());
                //TODO manage multiple primary keys
                $mormons->addHasManyJoiningCondition($join->getReferer(), 
                                                     $join->getFirstObj(), 
                                                     $join->getFirstObj()->getHasManyStatement($join->getReferer()));
                if(isset($this->_has_many[$join->getReferer()]['condition']))
                    $mormons->add_conditions($this->_has_many[$join->getReferer()]['condition']);
                if(isset($this->_has_many[$join->getReferer()]['order']))
                {
                    foreach($this->_has_many[$join->getReferer()]['order'] as $field => $direction)
                    {
                        $mormons->set_order($field); 
                        $mormons->set_order_dir($direction); 
                    }
                }
                $mormons->associateForeignObject($this->_pkey, $this);
                $this->_foreign_mormons[$join->getReferer()] = $mormons;
            }
            if(!empty($to_load))
                $this->_foreign_mormons[$join->getReferer()]->addMormFromArray($join, $to_load, $this);
        }
    }

    /**
     * getForeignValues 
     *
     * only used by the scaffolding, should probably be removed or extended to 
     * make it more generic 
     * 
     * @throws Exception if field is not a foreign key
     * @param mixed $field 
     * @param array $foreign_fields (can be null))
     * @param mixed $conditions 
     * @return array
     */
    public function getForeignValues ($field, $foreign_fields = NULL, $conditions = NULL)
    {
        if(!isset($this->_foreign_values[$field]))
        {
            $select = is_null($foreign_fields) ? '*' : '`'.implode('`,`', $foreign_fields).'`';
            $conditions = is_null($conditions) ? '' : $conditions;
            $sql = "select ".$select." from `".$this->getForeignTable($field)."` ".$conditions;
            $rs = SqlTools::sqlQuery($sql);
            $foreign_values = array();
            while($line = mysql_fetch_assoc($rs))
                $foreign_values[] = $line;
            $this->_foreign_values[$field] = $foreign_values;
        }
        return $this->_foreign_values[$field];
    }

    /**
     * fillDefaultValues 
     *
     * takes the default values from the table structure and fill the 
     * corresponding fields with them
     * 
     * @return void
     */
    public function fillDefaultValues()
    {
        foreach($this->getTableDesc() as $field_name => $field_desc)
        {
            if($this->hasDefaultValue($field_name) && $this->isEmpty($this->$field_name))
                $this->$field_name = $this->getDefaultValue($field_name);
        }
    }

    /**
     * findSourceFor 
     *
     * 
     * 
     * @param mixed $joined_class 
     * @return void
     */
    public function findSourceFor($joined_class)
    {
        //if(isset($opts['referer']))
        //{
        //    $stmts = $dummy->getHasManyStatements();
        //    if(isset($stmts[$opts['referer']]['source']))
        //        $opts['source'] = $stmts[$opts['referer']]['source'];
        //}
        //else
        
        if($source = $this->getBelongsToNameFor($joined_class))
        {
            return array('source' => $source, 'second_belongs_to_first' => true);
        }

        $dummy = is_object($joined_class) ? $joined_class : MormDummy::get($joined_class);
        if($source = $dummy->getBelongsToNameFor($this))
        {
            return array('source' => $source, 'first_belongs_to_second' => true);
        }
        //I should never be here
    }

    public function getBelongsToNameForTable($table)
    {
        /**
         * used to make a useful error message 
         */
        $ret = array();
        foreach($this->_belongs_to as $name => $stmt)
        {
            $class = isset($stmt['class']) ? $stmt['class'] : MormConf::getClassName($name);
            if($table == MormDummy::get($class)->getTable())
                $ret []= $name;
        }
        if (!isset($ret[1]))
        {
            return (isset($ret[0]) ? $ret[0] : null);
        }
        else
        {
            throw new Exception(sprintf("Source is unclear, could be one of \"%s\" ", implode(',', $ret)));
        } 
    }

    public function getBelongsToNameFor($obj_or_class, $throw_exception = true)
    {
        /**
         * used to make a useful error message 
         */
        $ret = array();
        $obj = is_object($obj_or_class) ? $obj_or_class : MormDummy::get($obj_or_class);
        foreach($this->_belongs_to as $name => $stmt)
        {

            if((isset($stmt['class_from_field']) && !$throw_exception) || $obj->is_a($this->getForeignClass($name)))
            {
                $ret []= $name;
            }
        }
        if (!isset($ret[1]))
        {
            return (isset($ret[0]) ? $ret[0] : null);
        }
        else if($throw_exception)
                throw new Exception(sprintf("Join source is unclear, you should specify one of \"%s\" sources to your statement", implode(',', $ret)));
        else
            return $ret;
    }

    public function getTable()
    {
        return $this->_table;
    }

    /**
     * getTableDesc 
     *
     * returns the TableDesc of the model's table
     * 
     * @return TableDesc
     */
    public function getTableDesc ()
    {   
        if(!isset($this->_table_desc)) {
            $this->_table_desc = TableDesc::getTable($this->_table); 
        }
        return $this->_table_desc;
    }

    /**
     * getHasManyStatements 
     *
     * return the declared ha_many statements
     * 
     * @return array
     */
    public function getHasManyStatements()
    {
        return $this->_has_many;
    }


    public function joinsdirecltyWith($class)
    {
        if(MormDummy::get($class)->isForeignClass(get_class($this))) return true; //second object belongs to first one 
        if($this->isForeignClass($class)) return true; //first object belongs to second one
        return false;
    }

    public function joinsIndirecltyWith($class)
    {
        if($this->joinsdirecltyWith($class)) return false;
        foreach($this->_has_many as $referer => $stmt)
        {
            if($stmt['class'] == $class && isset($stmt['using']))
                return true;
        }
        return false;
    }

    public function getHasManyStatement($referer)
    {
        return isset($this->_has_many[$referer]) ? $this->_has_many[$referer] : NULL;
    }

    public function getHasManyStatementsFor($class, Array $opts = array(), $throw_exception = true)
    {
        if(isset($opts['referer']) && isset($this->_has_many[$opts['referer']]) && $this->_has_many[$opts['referer']]['class'] == $class)
            return $this->_has_many[$opts['referer']];
        $stmts = array();
        foreach($this->_has_many as $referer => $stmt)
        {
            if($stmt['class'] == $class)
            {
                if(isset($opts['source']) && isset($stmt['source']) && $opts['source'] == $stmt['source'])
                    return array($referer => $stmt);
                $stmts[$referer] = $stmt;
            }
            if(isset($stmt['using']))
            {
                foreach($stmt['using'] as $using_class => $using_opts)
                {
                    if(is_numeric($class))
                        $using_class = $using_opts;
                    if($using_class == $class)
                    {
                        if(isset($opts['source']) && isset($stmt['source']) && $opts['source'] == $stmt['source'])
                            return array($referer => $stmt);
                        $stmts[$referer] = $stmt;
                    }
                }
            }
        }
        if(empty($stmts) && $throw_exception) throw new Exception(sprintf("No has_many statement found for class %s in %s", $class, get_class($this)));
        return $stmts;
    }
    /**
     * getFieldDesc 
     * 
     * returns the FieldDesc of the given field
     *
     * @throws Exception if field is not into table
     * @param string $field 
     * @return FieldDesc
     */
    public function getFieldDesc ($field)
    {
        if($this->isField($field))
        {
            return $this->getTableDesc()->$field;
        }
        else
            throw new Exception($field.' is not a field of the table '.$this->_table);
    }

    /**
     * validate 
     *
     * try to validate the fields values using the field types and restrictions 
     * from the database or the defined validation methods if they exist.
     * These validation methods should be named after the following pattern:
     * "validate<Field_name>" where <Field_name> is the name of the field to 
     * valide with its first caracter uppercased. The method must throw an MormFieldValidate if there 
     * is a validation error
     * 
     * @throws MormValidateException
     * @return boolean
     */
    public function validate()
    {
        $this->_errors = array();
        foreach($this->getTableDesc() as $field_name => $field_desc)
        {
            $error = false;
            $validate_method = 'validate'.ucfirst($field_name);
            if(method_exists($this, $validate_method))
            {
                try 
                {
                    $this->$validate_method();
                } 
                catch (MormFieldValidateException $e) 
                {
                    $error = $e->getMessage();
                }
            }
            else
            {
                $value = $this->$field_name;
                if(!$this->checkMandatory($field_name))
                    $error = isset($this->mandatory_errors[$field_name]) ? $this->mandatory_errors[$field_name] : "This field can not be empty";
                if($error === false && !$this->isEmpty($value) && !$this->checkTypeOf($field_name))
                    $error = isset($this->type_errors[$field_name]) ? $this->type_errors[$field_name] : "Wrong data type. This field is suppose to be a ".$field_desc->php_type;
            }
            if($error)
                $this->_errors[$field_name] = $error;
        }
//        if(empty($this->_errors))
//            $this->fillDefaultValues();
        if(count( $this->_errors) != 0)
        {
            throw new exception_MormValidate($this->_errors);
        }

        return count($this->_errors) == 0;
    }

    /**
     * checkTypeOf 
     *
     * check the value type of the given field according to the table 
     * description
     * 
     * @param string $field
     * @throws Exception if field is not into table
     * @return boolean
     */
    public function checkTypeOf($field)
    {
        if($this->isField($field))
        {
            $field_desc = $this->getFieldDesc($field);
            if($field_desc->isPrimary() && $this->hasAutoIncrement() && $this->isEmpty($this->$field))
                return true;
            if($field_desc->isNumeric() && !is_numeric($this->$field))
                return false;
            if($field_desc->php_type == 'integer' && !$this->isInteger($this->$field)) 
                return false;
            if($field_desc->php_type == 'float' && !$this->isFloat($this->$field)) 
                return false;
            if($field_desc->php_type == 'string' && !is_string(sprintf('%s',$this->$field))) 
                return false;
            return true;
        }
        else
            throw new Exception($field.' is not a field of the table '.$this->_table);
    }

    /**
     * checkMandatory 
     * 
     * check if the given field has a value and return false if it's but should 
     * not
     *
     * @param string $field
     * @throws Exception if field is not into table
     * @return boolean
     */
    public function checkMandatory($field)
    {
        if($this->isField($field))
        {
            $value = $this->$field;
            return !($this->isMandatory($field) && $this->isEmpty($value));
        }
        else
            throw new Exception($field.' is not a field of the table '.$this->_table);
    }

    /**
     * isMandatory 
     *
     * returns true if the given field can not be null and has no default value
     * 
     * @param string $field
     * @throws Exception if field is not into table
     * @return boolean
     */
    public function isMandatory($field)
    {
        if($this->isField($field))
        {
            $field_desc = $this->getFieldDesc($field);
            if($field_desc->isPrimary() && $this->hasAutoIncrement())
                return false;
            return $field_desc->Null == 'NO' && !$this->hasDefaultValue($field);
        }
        else
            throw new Exception($field.' is not a field of the table '.$this->_table);
    }

    /**
     * getErrors 
     * 
     * returns the _errors array
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * hasError 
     *
     * check the _errors array for values
     * 
     * @return boolean
     */
    public function hasError()
    {
        return (count($this->_errors)>0);
    }

    /**
     * castFields 
     *
     * force the php types of the fields values according to those defined in the 
     * database table description
     * 
     * @return void
     */
    public function castFields()
    {
        foreach($this->getTableDesc() as $field_name => $field_desc)
        {
            if($field_desc->php_type == 'integer' && $this->isEmpty($this->$field_name))
                $this->_fields[$field_name] = NULL;
            else if(isset($this->_fields[$field_name]) && is_object($this->_fields[$field_name]) && $this->_fields[$field_name] instanceof MormField_SqlFunction)
                continue;
            else if(isset($this->_fields[$field_name]) && !is_null($this->_fields[$field_name]))
                settype($this->_fields[$field_name], $field_desc->php_type);
        }
    }

    /**
     * setPKey 
     *
     * called from the constructor
     * set the _pkey member by getting it from the table description
     * and return it
     * this value may be a string or an array if the table's primary key is a 
     * multiple one
     * 
     * @todo throw exception if table !isset
     * @return mixed (array or string)
     */
    private function setPKey()
    {
        if(is_null($this->_pkey))
        {
            $this->_pkey = $this->getTableDesc()->getPKey();
        }
        return $this->_pkey;
    }

    /**
     * getPkey 
     *
     * returns the field name (or names as an array) of the table's primary key.
     * if $to_string is set to true (used specially for mormons object 
     * identification) and table has multiple primary key, returns a string with 
     * the rpimary keys names separated with the defined MORM_SEPARATOR
     * 
     * @param boolean $to_string
     * @return mixed
     */
    public function getPkey($to_string = false)
    {
        if(is_null($this->_pkey) && $to_string)
            return '';
        if(is_array($this->_pkey) && $to_string)
        {
            return implode(MormConf::MORM_SEPARATOR, $this->_pkey);
        }
        return $this->_pkey;
    }

    /**
     * getPkeyVal 
     * 
     * returns the value of the primary key
     * returns an hash of values if multiple primary keys
     * returns null if no primary key defined in the table
     *
     * @return mixed
     */
    public function getPkeyVal($to_string = false)
    {
        if(is_null($this->_pkey))
            return NULL;
        if(is_array($this->_pkey))
        {
            $ret = array();
            foreach($this->_pkey as $key)
            {
                $ret[$key] = $this->$key;
            }
            return $to_string ? implode(MormConf::MORM_SEPARATOR, $ret) : $ret;
        }
        else
            return $this->{$this->_pkey};
    }

    /**
     * isField 
     *
     * check if the given parameter is actually a field name in the model's table
     * 
     * @access protected
     * @param string $field
     * @return boolean
     */
    protected function isField ($field)
    {
        return $this->getTableDesc()->isField($field);   
    }

    /**
     * hasDefaultValue 
     *
     * check if the given field name has a defined default value in the table's 
     * description
     * 
     * @access private
     * @param string $field
     * @return boolean
     */
    private function hasDefaultValue ($field)
    {
        $field_desc = $this->getFieldDesc($field);
        if($field_desc->Null == 'YES')
            return true;
        return !$this->isEmpty($field_desc->Default);
    }

    /**
     * getDefaultValue 
     *
     * return the default value defined in the table's description for the given 
     * field name
     * 
     * @access private
     * @param string $field
     * @return string
     */
    private function getDefaultValue ($field)
    {
        $field_desc = $this->getFieldDesc($field);
        return $field_desc->Default;   
    }

    /**
     * isEmpty 
     *
     * tries to reproduce php's "empty()" function's behaviour in a "not stupid" 
     * way
     *
     * @fixme strlen may be replaced by isset($str[0]) for better performance if 
     * we are sure it has the same effect
     * 
     * @access private
     * @param mixed 
     * @return boolean
     */
    private function isEmpty($val)
    {
        return MormUtils::isEmpty($val);
    }

    /**
     * isFLoat 
     *
     * tries to be a little less stupid than php's "is_float()" function
     * 
     * @access private
     * @param mixed $val 
     * @return void
     */
    private function isFLoat($val)
    {
        if(is_bool($val))
            return false;
        $f_val = floatval($val);
        $s_val = strval($val);
        return strval($f_val) === $s_val;
    }


    /**
     * isInteger 
     *
     * tries to be a little less stupid than php's "is_int()" function
     * 
     * @access private
     * @param mixed $val 
     * @return boolean
     */
    private function isInteger($val)
    {
        if(is_bool($val))
            return false;
        $f_val = intval($val);
        $s_val = strval($val);
        return strval($f_val) === $s_val;
    }


    /**
     * hasAutoIncrement 
     *
     * check if the model's table has an auto incrment field
     * 
     * @access private
     * @return boolean
     */
    private function hasAutoIncrement ()
    {
        return $this->getTableDesc()->hasAutoIncrement();   
    }

    /**
     * fieldsToInsert 
     * 
     * returns a hash with the fields which values need to be inserted in the 
     * database
     *
     * @access private
     * @return array
     */
    private function fieldsToInsert ()
    {
        $to_insert = $this->_fields;
        foreach($this->getTableDesc() as $field_name => $field_desc)
        {
            if($field_desc->isPrimary() && $this->hasAutoIncrement() && $this->isEmpty($this->$field_name))
                unset($to_insert[$field_name]);
            if($this->hasDefaultValue($field_name) && $this->isEmpty($this->$field_name) && in_array($field_name, array_keys($to_insert)))
                unset($to_insert[$field_name]);
        }
        return $to_insert;
    }

    /**
     * createInsertSql 
     *
     * build the insert sql query for the model and return it
     *
     * @access private
     * @return string
     */
    private function createInsertSql ()
    {
        $to_insert = $this->fieldsToInsert();
        return "INSERT INTO `".$this->_table."` ".SqlBuilder::set($to_insert);
    }

    /**
     * fieldsToUpdate 
     * 
     * returns a hash with the fields which values need to be updated in the 
     * database
     * this is done by comparing the original values with the new ones. (diff 
     * betweeen the _original and _fields hash)
     *
     * @access private
     * @return array
     */
    protected function fieldsToUpdate ()
    {
        $to_update = array_diff_assoc($this->_fields, $this->_original);
        foreach($this->getTableDesc() as $field_name => $field_desc)
        {
            if($field_desc->isPrimary() && $this->hasAutoIncrement() && $this->isEmpty($this->$field_name))
                unset($to_update[$field_name]);
            //else if($this->hasDefaultValue($field_name) && $this->isEmpty($this->$field_name) && in_array($field_name, array_keys($to_update)))
            //    unset($to_update[$field_name]);
        }
        return $to_update;
    }

    /**
     * createUpdateSql 
     *
     * build the update sql query for the model and return it
     *
     * @access private
     * @return string
     */
    private function createUpdateSql ()
    {
        $set=array();
        $to_update = $this->fieldsToUpdate();
        foreach($to_update as $key => $value)
        {
            $set[] = "`".$key."`=".SqlTools::formatSqlValue($value);
        }
        $set = implode(',', $set);
        return "UPDATE `".$this->_table."` SET ".$set.$this->createIdentifyingWhereSql(); 
    }

    /**
     * createDeleteSql 
     *
     * build the delete sql query for the model and return it
     * 
     * @access private
     * @return string
     */
    private function createDeleteSql ()
    {
        return "delete from `".$this->_table."` ".$this->createIdentifyingWhereSql(); 
    }

    /**
     * createIdentifyingWhereSql 
     *
     * build the sql where statement used to get the model's corresponding row or 
     * the given key's one and return it
     * 
     * @param mixed $not_yet_loaded_key 
     * @return string
     */
    private function createIdentifyingWhereSql($not_yet_loaded_key = null)
    {
        $pkey = is_null($not_yet_loaded_key) ? $this->getPkeyVal() : $not_yet_loaded_key;
        if(is_array($this->_pkey))
        {
            $req = array();
            foreach($this->_pkey as $key)
            {
                $req[] = '`'.$this->_table.'`.`'.$key.'` = '.SqlTools::formatSqlValue($pkey[$key]);
            }
            $where = " where ".implode(' AND ', $req); 
        }
        else
            $where = ' where `'.$this->_table.'`.`'.$this->_pkey."`=".SqlTools::formatSqlValue($pkey); 
        return $where;
    }

    /**
     * castTo 
     *
     * use this with caution
     * cast a Morm model to another Morm model.
     * This function uses a bad php hack and can therefore only get a class name 
     * that is an instance of this
     * 
     * @param string $class 
     * @return void
     */
    public function castTo($class)
    {
        
        if(!class_exists($class)) {
            trigger_error("The object can't be casted : the class '{$class}' doesn't exist", E_USER_ERROR);
        }
        $this_class = get_class($this);
        if(!is_subclass_of($class,$this_class)) {
            trigger_error("The object can't be casted : '{$class}' is not a subclass of '{$this_class}'", E_USER_ERROR);
        }
        $str = serialize($this);
        $str = preg_replace('/^O:\d*:"' . $this_class . '"/','O:' . strlen($class) . ':"' . $class . '"',$str);
        
        $instance = unserialize($str);
        
        if(method_exists($instance, 'prepare')) {
            $instance->prepare();
        }
        
        return $instance;
    }


    public function getSame()
    {
        $morm = new self();
        $values = $this->_fields;
        if(is_array($this->_pkey))
        {
            foreach($this->_pkey as $value)
                unset($values[$value]);
        }
        else
            unset($values[$this->_pkey]);
        $morm->setFromArray($values);
        return $morm;
    }

    public function getStiField()
    {
        /**
         * I may have a 'type' field in this model but I don't want to use it as a 
         * STI 
         */
        if($this->sti_field === NULL) return NULL;
        /**
         * morm uses the 'type' field for STI but don't be stupid and throw an 
         * exception if there isn't any 'type' field for the model
         */
        if($this->sti_field == 'type' && !$this->isField($this->sti_field)) return NULL;
        /**
         * Using 'type' for STI is bad (or not, or was already used for something 
         * else on this model) but now I want to use Morm's cool STI feature on it 
         * and let it guess the class name from another field.
         */
        if($this->isField($this->sti_field)) return $this->sti_field;
        /**
         * come on, the field I defined for sti does not even exist in the table.
         * Silly me. 
         */
        throw new Exception($this->sti_field.' is not a field of the table '.$this->_table.' and can therefore not be used as an sti field');
    }

    /**
     * is_a 
     * 
     * checks if $this is an instance of or inherits from the given class 
     * name or given object's class name
     *
     * @param string|object $obj_or_class 
     * @return boolean
     */
    public function is_a($obj_or_class)
    {
        if (!is_object($obj_or_class) && !is_string($obj_or_class)) return false;
        $obj = is_object($obj_or_class) ? $obj_or_class : MormDummy::get($obj_or_class);//FIXME this will not work if the class constructor needs a parameter
        return ($this instanceof $obj);
    }

    /**
     * Factory 
     *
     * instantiate a Morm and loads it using the sti field if declared, needed 
     * and filled
     *
     * FIXME, maybe this could be used in FactoryFromMormons but to o that, Morm 
     * should be able to load an object from an Array as if it was given to the 
     * constructor
     * 
     * @param string $super_class top class of the STI
     * @params array $to_load array used to load the mmorm object
     * @return Morm
     */
    public static function Factory($super_class, $to_load)
    {
        $class = $super_class;
        $model = new $class();
        if($sti_field = $model->getStiField())
        {
            if(isset($to_load[$sti_field]) && !empty($to_load[$sti_field]))
            {
                $sti_class = MormConf::getClassName($to_load[$sti_field], $model->_table);
                $sti_model = new $sti_class();
                if($sti_model->is_a($super_class) || MormDummy::get($super_class)->is_a($sti_model))
                {
                    $class = $sti_class;
                    unset($sti_model);
                }                
                else throw new Exception('The class '.$sti_class.' is not a '.$super_class.' and could not be used as a sti model');
            }
            else
                throw new Exception('(in Factory) ' . $super_class . ' : Could not guess the class to instantiate from this array, the sti field wasn\'t there');
        }
        unset($model);
        return new $class($to_load);
    }

    public static function FactoryFromId($super_class, $pkey)
    {
        $rs = SqlTools::sqlQuery("select * from `".MormDummy::get($super_class)->getTable().
                                        "` ".MormDummy::get($super_class)->createIdentifyingWhereSql($pkey));
        if($rs && mysql_num_rows($rs) > 0) 
        {
            return self::Factory($super_class, mysql_fetch_assoc($rs));
        }
        return NULL;
    }

    /**
     * FactoryFromMormons 
     *
     * almost the same as Factory but does strange things useful for Mormons
     * 
     * @param string $super_class top class of the STI
     * @param Mormons $mormons mormons object to associate with the model
     * @params array $to_load array used to load the mmorm object
     * @access public
     * @return Morm
     */
    public static function FactoryFromMormons($super_class, &$mormons, $to_load, $joins)
    {
        $model = new $super_class();
        if($sti_field = $model->getStiField())
        {
            $sti_field_mormonized = MormConf::MORM_PREFIX.MormConf::MORM_SEPARATOR.$super_class.MormConf::MORM_SEPARATOR.$sti_field;
            if(isset($to_load[$sti_field_mormonized]) && !empty($to_load[$sti_field_mormonized]))
            {
                $sti_class = MormConf::getClassName($to_load[$sti_field_mormonized], $model->_table);
                $sti_model = new $sti_class();
                if($sti_model->is_a($super_class)) $model = $sti_model;
                else throw new Exception('The class '.$sti_class.' is not a '.$super_class.' and could not be used as a sti model');
            }
            else
                throw new Exception('(in FactoryFromMormons) ' . $super_class . ' : Could not guess the class to instantiate from this array, the sti field wasn\'t there');
        }
        $model->associateWithMormons($mormons);
        $model->loadFromMormons($to_load, $super_class, $joins);
        return $model;
    }

    /**
     * plug
     *
     * load a Morm plugin
     *
     * @param string $plugin_name class name to load for your plugin
     * @param array $plugin_options optional array passed to the constructor when instanciating a plugin
     * @access public
     * @return boolean
     */
    public static function plug($name, $options=array())
    {
        // Silently plugins loaded twice
        if(isset(self::$_plugin[$name]))
            return true;

        // Try to load plugin source
        $plugin_file = dirname(__FILE__) . "/plugins/$name/$name.php";
        if(!file_exists($plugin_file))
            throw new Exception("Can't load plugin $name: source file is missing");

        require_once($plugin_file);

        // Check for conflicts between plugin methods
        $registered_methods = array_keys(self::$_plugin_method);
        $new_methods = call_user_func(array($name, 'extend_with'));
        foreach($new_methods as $new_method)
        {
            if(in_array($new_method, $registered_methods))
                throw new Exception("Can't load plugin $name: method conflict " .
                                    "with plugin: " . self::$_plugin_method[$method] .
                                    " ($new_method)");
        }

        // Register plugin and its methods
        self::$_plugin[$name] = $new_methods;
        foreach(self::$_plugin[$name] as $method)
            self::$_plugin_method[$method] = $name;

        // Register plugin constructor options
        self::$_plugin_options[$name] = $options;
    }

    /**
     * DATA PROVIDERS 
     */

    public function toObj($linked_objs = array())
    {
        $obj = new Data();
        $callbacks = array();
        if(isset($linked_objs['callbacks']))
        {
            $callbacks = $linked_objs['callbacks'];
            unset($linked_objs['callbacks']);
        }
        $field_keys = $this->getTableDesc()->field_keys;
        foreach($field_keys as $key)
            $obj->$key = $this->$key;
        foreach($callbacks as $key => $callback)
        {
            $params = array();
            $to_call = $callback;
            if(is_array($callback))
            {
                $to_call = array_shift($callback);
                $params = $callback;
            }
            $obj->$key = call_user_func_array(array($this, $to_call), $params);
        }
        //TODO handle linked objects
        //foreach($linked_objs as $key => $to_link)
        //{
        //    $link = is_array($to_link) ? $key : $to_link;
        //    $link_links = is_array($to_link) ? $to_link : array();
        //    $link_name = strtolower($link);
        //    $obj->$link_name = null;
        //    if(Contentlink::isLinkedType($this, $link))
        //    {
        //        $this->loadLinkedContents($link);
        //        $l = $this->$link_name;
        //        if(!is_null($l))
        //        {
        //            if(is_array($l))
        //            {
        //                $obj->$link_name = array();
        //                foreach($l as $to_get)
        //                    array_push($obj->$link_name, $to_get->toObj($link_links));
        //            }
        //            else
        //                $obj->$link_name = $l->toObj($link_links);
        //        }
        //    }
        //}
        return $obj;
    }

    public function toJSON($opts=array())
    {
        return json_encode($this->toObj($opts));
    }

    public function getMorphinxIndex()
    {
        return $this->index;
    }

    public function getLazyLoadingLimit()
    {
        return $this->lazy_loading_limit;
    }

}

