<?php
/**
 * Mormons 
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class Mormons implements Iterator, Countable, ArrayAccess
{

    /**
     * @access protected
     */
    protected $mormons = null; 

    /**
     * @access protected
     * @var integer
     */
    protected $nb_mormons = null;

    /**
     * @access protected
     */
    protected $mormon_keys = null; 

    /**
     * @access private
     */
    private $key = 0;

    private $classes = array();

    /**
     * @access private
     * @var array
     */
    private $select = array();

    /**
     * @access private
     * @var array
     */
    private $joins = array();

    /**
     * @access private
     * @var array
     */
    private $join_classes = array();

    private $join_aliases = array();

    /**
     * @access private
     * @var array
     */
    private $where = array();

    /**
     * @access private
     * @var string
     */
    private $sql_where = '';

    private $group_by = null;

    /**
     * @access private
     * @var array
     */
    private $order = array();

    /**
     * @access private
     * @var string
     */
    private $order_dir = 'DESC';

    /**
     * @access private
     * @var integer
     */
    private $offset = null;

    /**
     * @access private
     * @var integer
     */
    private $limit = null;

    private $per_page = 42;

    private $lazy_load = null;
    
    private $class_from_field_value = array();

    protected $base_class = null;
    protected $base_object = null;

    protected $_executed = false;
    private $_total_elements = null;
    protected $executed_filters = array();

    private $_foreign_object_waiting_for_association = null;

    /**
     * __construct 
     *
     * Constructor for the Mormons.
     * The given parameter must be a table name or model class and will be used as the base 
     * table name
     *
     * giving a table as init parameter is now deprecated and will soon not be supported anymore
     * 
     * @param string $init 
     * @return void
     */
    public function __construct ($init)
    {
        $init = class_exists($init) && is_subclass_of($init, 'Morm') ? $init : MormConf::getClassName($init);
        $this->addClass($init);
        $this->base_class = $init;
        $this->base_object = MormDummy::get($this->base_class);
        $this->manageStiConds();
    }

    /**
     * __call 
     *
     * call the method if it exists
     * else if the method exists for the Morm objects in the $this->mormons 
     * array, call it for each of these object.
     * 
     * @fixme check this method, the foreach does not do what it is suppose to do
     *
     * @throws Exception if the method does not exist neither in Mormons 
     * nor in Morm or if no element present in the array
     * @param string $method name of the called method
     * @param array $arguments array of parameters for the method
     * @return mixed
     */
    public function __call ($method, $arguments)
    {
        $clone = false;
        if(preg_match('/^(.+)Clone$/', $method, $matches))
        {
            $method = $matches[1];
            $clone = true;
        }
        if(method_exists($this->base_object, 'filter_'.$method))
        {
            return $clone ? $this->getFilteredByClone($method, $arguments) : $this->getFilteredBy($method, $arguments);
        }
        if(is_array($this->mormons))
        {
            foreach($this->mormons as $obj_id => $morm)
            {
                if(method_exists($morm, $method))
                    return call_user_func_array(array($morm, $method), $arguments);
                else
                    throw new Exception("The method ".$method." does not exist for ".get_class($morm)." object");
            }
            throw new Exception("No elements in this Mormons");
        }
        throw new Exception("The method ".$method." does not exist in Mormons class");
    }

    public function __get ($get)
    {
        if(is_numeric($get))
            return $this->getById($get);
        $clone = false;
        if(preg_match('/^(.+)Clone$/', $get, $matches))
        {
            $get = $matches[1];
            $clone = true;
        }
        if($this->base_object->isFilter($get))
            return $clone ? $this->getFilteredByClone($get) : $this->getFilteredBy($get);
        return NULL;
    }

    public function getById ($id)
    {
        if(!$this->_executed)
            $this->execute();
        if($this->isLoaded($this->base_class, $id))
        {
            return $this->mormons[$this->base_class.'_'.$id];
        }
        else
            throw new Exception("The object identified by $id for class {$this->base_class} is not in this mormons");
    }

    public function getFilteredByClone($filter_name, Array $opts = array())
    {
        $clone = $this->getClone();
        return $clone->getFilteredBy($filter_name, $opts);
    }

    public function getFilteredBy($filter_name, Array $opts = array())
    {
        if(!$this->isFilteredBy($filter_name, $opts))
            $this->setFilter($filter_name, $opts);
        return $this;
    }

    public function isFilteredBy($filter_name, Array $opts = array())
    {    
        if(empty($opts))
            return isset($this->executed_filters[$filter_name]);
        return isset($this->executed_filters[$filter_name.'()']) && $this->executed_filters[$filter_name.'()'] == $opts;//FIXME how does the array comparison works ?
    }

    protected function setFilter($filter_name, Array $opts = array())
    {
        if(empty($opts))
        {
            $this->executed_filters[$filter_name] = true; 
            $filters = $this->base_object->getFilter($filter_name);
        }
        else
        {
            $this->executed_filters[$filter_name.'()'] = $opts;
            $filters = call_user_func_array(array($this->base_object, 'filter_'.$filter_name), $opts);
        }
        
        foreach($filters as $type => $to_set)
            call_user_func_array(array($this, $type), $to_set);
        
    }

    public function manageHasManyStatmt($has_many_stmt, $base_obj, $stmt, $add_joining_condition = true)
    {
        //TODO manage multiple primary keys
        foreach($stmt as $f => $param)
        {
            $func = 'hasMany' . ucfirst($f) . 'Statmt';
            if(method_exists($this, $func)) 
            {
                call_user_func_array(array($this, $func), array($has_many_stmt, $base_obj, $param));
            }
        }
        if($add_joining_condition)
            $this->addHasManyJoiningCondition($has_many_stmt, $base_obj, $stmt);
    }

    public function addHasManyJoiningCondition($has_many_stmt, $base_obj, $stmt)
    {
        if(isset($stmt['using']))
        {
            /**
             * TODO manage when passing through many tables 
             * in fact I should just setJoin for each of these values but I first need to:
             *  - check if the using_class can be directly joined with the base_class in which case I need to add_conditions
             *  - specify with which class my $using_class should be joined 
             *  (for this, I could just suppose that the using statement is given in the right order so that the each given class can be directly joined with the one before)
             */
            foreach($stmt['using'] as $using_class => $opts)
            {
                if(is_integer($using_class)) //nothing else than the joining class
                {
                    $using_class = $opts;
                    $opts = array();
                } 
                $dummy = MormDummy::get($using_class);
                $sources_info = isset($stmt['source']) ? $stmt : $dummy->findSourceFor($base_obj);//this may throw an Exception if no source could be found
                $ft_key = $dummy->getForeignKeyFor($sources_info['source']);
                $this->add_conditions(array($ft_key => $base_obj->{$base_obj->getPKey()}), $using_class);
            }
        }
        else
        {
            if(isset($stmt['source']))
            {
                $source = $stmt['source'];
            }
            else
            {
                $j = new MormJoin($this->base_class, get_class($base_obj), array('referer' => $has_many_stmt));
                $source = $j->getSource();
            }
            $ft_key = $this->base_object->getForeignKeyFor($source);

            $this->add_conditions(array($ft_key => $base_obj->getPkeyVal()));
        }
    }

    protected function manageStiConds()
    {
        if($sti_field = $this->base_object->getStiField()) // there is an sti, we should now add a condition
            $this->conditions(array($sti_field => $this->base_object->getStiValue()));
    }

    /**
     * hasManyUsingStatmt 
     *
     * sets the join statement and other things for a has_many mormons using the "using" hash in 
     * the $alias_or_table has_many hash
     *
     * @access private
     * @param string $alias_or_table 
     * @param Mormons $mormons (reference)
     * @return void
     */
    private function hasManyUsingStatmt($has_many_stmt, $base_obj, $stmt)
    {
        //The commented lines may do th multiple joins job
        //$previous_class = null;
        //foreach($stmt['using'] as $using_class => $using_opts)
        //{
        //    if(is_integer($using_class)) //nothing else than the joining class
        //    {
        //        $using_class = $using_opts;
        //        $using_opts = array();
        //    } 
        //    $first_class = is_null($previous_class) ? $this->base_class : $previous_class;
        //    $using_opts['referer'] = $referer;
        //    $this->setJoin($first_class, $using_class, $using_opts);
        //    $previous_class = $using_class;
        //}
        /**
         * TODO manage when passing through many tables 
         * in fact I should just setJoin for each of these values but I first need to:
         *  - check if the using_class can be directly joined with the base_class in which case I need to add_conditions
         *  - specify with which class my $using_class should be joined 
         *  (for this, I could just suppose that the using statement is given in the right order so that the each given class can be directly joined with the one before)
         */
        foreach($stmt as $using_class => $opts)
        {
            if(is_integer($using_class)) //nothing else than the joining class
            {
                $using_class = $opts;
                $opts = array();
            } 
            //$opts['referer'] = $has_many_stmt;
            $opts['origin_obj'] = $base_obj;
            $this->joins($using_class, $opts);
        }
    }

    private function hasManyConditionStatmt($has_many_stmt, $base_obj, $stmt)
    {
        $stmts = $base_obj->getHasManyStatements();
        $this->add_conditions($stmt, $stmts[$has_many_stmt]['class']);
    }

    private function hasManySql_whereStatmt($has_many_stmt, $base_obj, $stmt)
    {
        $this->set_sql_where($stmt);
    }

    private function hasManyOrderStatmt($has_many_stmt, $base_obj, $stmt)
    {
        foreach($stmt as $field => $direction)
        {
            $this->set_order($field); 
            $this->set_order_dir($direction); 
        }
    }

    protected function addClass($class)
    {
        if($this->isUsedClass($class)) throw new Exception("The class ".$class." already exists in this object");
        $dummy = MormDummy::get($class);
        $this->classes[$class] = true;
        $this->where[$class] = array();
    }

    public function set_sql_whereClone($sql)
    {
        $clone = $this->getClone();
        return $clone->set_sql_where($order, $alternate_table);
    }

    /**
     * @param string $sql
     * @return void
     */
    public function set_sql_where($sql)
    {
        if(!is_string($sql)) throw new Exception("The where clause is supposed to be a string");
        if($this->sql_where != $sql)
        {
            $this->sql_where .= ' '.$sql;
            $this->resetFlags();
        }
        return $this;
    }

    public function whereClone($sql)
    {
        return $this->set_sql_whereClone($sql);
    }

    public function where($sql)
    {
        return $this->set_sql_where($sql);
    }

    public function joins($class, Array $opts = array())
    {
        if($this->base_object->joinsIndirecltyWith($class))//means that there is no possible direct relation between base_class and class in either way
        {
            $this->joinsIndirectly($class, $opts);
        }
        else
        {
            $this->setJoin($this->base_class, $class, $opts);
        }
    }

    /**
     * joinsIndirectly 
     *
     * FIXME
     * this is almost redundant with hasManyUsingStatmt except for the direct joining class.
     * Here the direct join should be a real join
     * in hasManyUsingStatmt, it's just another condition
     * 
     * @param mixed $class 
     * @param Array $opts 
     * @return void
     */
    private function joinsIndirectly($class, Array $opts = array())
    {
        $stmts = $this->base_object->getHasManyStatementsFor($class, $opts);//these should only be statements containing a "using" key
        //FIXME strange workaround, need to check this
        if(isset($stmts['class'])) $stmts = array($stmts);
//        if(count($stmts) > 1) throw new Exception(sprintf("Join could refer to \"%s\" you should specify the one you need", implode(' or ', $stmts)));
        foreach($stmts as $referer => $stmt)
        {
            $previous_class = null;
            foreach($stmt['using'] as $using_class => $using_opts)
            {
                if(is_integer($using_class)) //nothing else than the joining class
                {
                    $using_class = $using_opts;
                    $using_opts = array();
                } 
                $first_class = is_null($previous_class) ? $this->base_class : $previous_class;
                $using_opts['referer'] = $referer;
                $this->setJoin($first_class, $using_class, $using_opts);
                $previous_class = $using_class;
            }
            $this->setJoin($previous_class, $stmt['class'], $stmt);
        }
    }

    private function setJoin($first_class, $second_class, Array $opts = array())
    {
        if(!empty($this->class_from_field_value))
            $opts['class_from_field_value'] = $this->class_from_field_value;
        $join = new MormJoin($first_class, $second_class, $opts); 

        if($this->isJoinedWith($join)) return;
       
        if(!$this->isUsedClass($second_class))
        {
            $this->addClass($second_class);
            $this->join_classes[$second_class] = true;
        }

        $this->join_aliases[$join->getSecondTableAlias()] = $join->getSecondObj()->getTable();

        $this->joins []= $join; 
        $this->resetFlags();

        if(isset($opts['referer']) && $stmt = MormDummy::get($first_class)->getHasManyStatementsFor($second_class, $opts))
        {
            $revised_stmt = array_diff_key($stmt, array('class' => true, 'source' => true));
            $this->manageHasManyStatmt($opts['referer'], MormDummy::get($first_class), $revised_stmt, false);    
        }
    }

    private function isJoinedWith(MormJoin $join)
    {
        foreach($this->joins as $j)
        {
            if($j->isTheSameAs($join))
                return true;
        }
        return false;
    }

    public function set_orderClone($order, $alternate_table=null)
    {
        $clone = $this->getClone();
        return $clone->set_order($order, $alternate_table);
    }
    /**
     * set_order 
     * 
     * @todo Consider the possibility of having more than one order fields
     *
     * @param string $order ordering field
     * @param string $alternate_class optionnal alternate table
     * @return void
     */
    public function set_order($order, $alternate_class = null)
    {
        if(!is_string($order) && !is_array($order)) throw new Exception("The order is suppose to be a field or an array of fields");
        $class = $this->base_class; 
        if(!is_null($alternate_class))
        {
            $possible_class = MormConf::getClassName($alternate_class);
            if(!$this->isUsedClass($possible_class)) 
                $this->addClass($possible_class); 
            $class = $possible_class;
        }
        if(is_string($order)) 
            $order = array($order);
        if(!isset($this->order[$class]) || $this->order[$class] != $order)
        {
            $this->order[$class] = $order;
            $this->resetFlags();
        }
        return $this;
    }

    public function group($group, $alternate_class = null)
    {
        if(!is_string($group) && !is_array($group)) throw new Exception("The order is suppose to be a field or an array of fields");
        $class = $this->base_class; 
        if(!is_null($alternate_class))
        {
            $possible_class = MormConf::getClassName($alternate_class);
            if(!$this->isUsedClass($possible_class)) 
                $this->addClass($possible_class); 
            $class = $possible_class;
        }
        if(!isset($this->group_by))
        {
            $this->group_by = array($class => $group);
            $this->resetFlags();
        }
        return $this;
    }

    public function unset_order()
    {
        $this->order = array();
    }

    /**
     * @throws Exception is $dir is not correct value
     * @param string $dir
     * @return void
     */
    public function set_order_dirClone($dir)
    {
        $clone = $this->getClone();
        return $clone->set_order_dir($dir);
    }

    public function set_order_dir($dir)
    {
        $dir = strtoupper($dir);
        if(!in_array($dir, array('DESC', 'ASC'))) throw new Exception("The direction is suppose to be DESC or ASC (case insensitive)");
        if($this->order_dir != $dir)
        {
            $this->order_dir = $dir;
            $this->resetFlags();
        }
        return $this;
    }

    /**
     * offset 
     *
     * alias for set_offset
     * 
     */
    public function offset($offset)
    {
        return $this->set_offset($offset);
    }

    /**
     * @throws Exception if $offset is not numeric value
     * @param integer $offset
     * @return void
     */
    public function set_offsetClone($offset)
    {
        $clone = $this->getClone();
        return $clone->set_offset($dir);
    }

    public function set_offset($offset)
    {
        if(!is_numeric($offset)) throw new Exception("The offset is suppose to be a numeric value");
        if($this->offset != $offset)
        {
            $this->offset = $offset;
            $this->resetFlags();
        }
        return $this;
    }

    /**
     * limit 
     *
     * alias for set_limit
     * 
     * @param mixed $limit 
     * @return void
     */
    public function limit($limit)
    {
        return $this->set_limit($limit);
    }

    public function set_limitClone($limit)
    {
        $clone = $this->getClone();
        return $clone->set_limit($limit);
    }

    /**
     * @throws Exception if $limit is not a numeric value
     * @param integer $limit
     * @return void
     */
    public function set_limit($limit)
    {
        if(!is_numeric($limit)) throw new Exception("The limit is suppose to be a numeric value");
        if($this->limit != $limit)
        {
            $this->limit = $limit;
            $this->resetFlags();
        }
        return $this;
    }

    private function resetFlags() {
        $this->_executed = FALSE;
        $this->_total_elements = NULL;
    }


    public function paginateClone($page, $per_page = 42)
    {
        $clone = $this->getClone();
        return $clone->paginate($page, $per_page);
    }

    /**
     * paginate 
     * 
     * sets offset and limit according to given parameters
     *
     * @param integer $page will be used to calculate the offset according to 
     * the second parameter
     * @param integer $per_page will set the limit as is 
     * @return void
     */
    public function paginate($page, $per_page = 42)
    {
        $this->offset(( intval($page) - 1 ) * $per_page);
        $this->limit($per_page);
        $this->per_page = $per_page;
        return $this;
    }

    /**
     * Get nb pages
     */
    public function nb_pages($per_page = null)
    {
        $per_page = is_null($per_page) ? $this->per_page : $per_page;
        return ceil($this->get_count() / $per_page);
    }

    public function isUsedClass($class)
    {
        return isset($this->classes[$class]);
    }

    public function conditionsClone($conds, $alternate_table=null)
    {
        return $this->add_conditionsClone($conds, $alternate_table);
    }

    public function add_conditionsClone($conds, $alternate_table=null)
    {
        $clone = $this->getClone();
        return $clone->add_conditions($conds, $alternate_table);
    }
    
    public function conditions($conds, $alternate_table=null)
    {
        return $this->add_conditions($conds, $alternate_table);
    }

    /**
     * @param array $conds
     * @return void
     */
    public function add_conditions($conds, $alternate_class=null)
    {
        $class = $this->base_class; 
        if(!is_null($alternate_class))
        {
            $possible_class = MormConf::getClassName($alternate_class);
            if(!$this->isUsedClass($possible_class)) 
                $this->addClass($possible_class); 
            $class = $possible_class;
        }

        /**
         * FIXME is this really usefull ? Shouldn't I just let mysql explode if the request fails ? 
         */
        foreach ($conds as $field => $void) {
            if(!MormDummy::get($class)->table_desc->isField($field)) {
                throw new exception_MormFieldUnexisting($class, $field);
            }
        }

        $this->lookForClassFromFieldToSet($conds);
        $this->where[$class] = array_merge($this->where[$class], $conds);
        $this->resetFlags();
        return $this;
    }

    public function getClone()
    {
        return clone $this;
    }

    public function forceLazyLoading()
    {
        $this->lazy_load = true;
    }

    public function lazyLoad()
    {
        if(is_null($this->lazy_load))
        {
            $max = (int) is_null($this->limit) ? $this->get_count() : $this->limit;
            $this->lazy_load = $max > $this->base_object->lazy_loading_limit;
        }
        return $this->lazy_load;
    }

    /**
     * @throws Exception if MySQL error occurs
     * @return void
     */
    public function execute()
    {
        if($this->lazyLoad())
            $select = SqlBuilder::singleSelect($this->base_class, $this->base_object->getPkey());
        else
            $select = SqlBuilder::select_with_aliases($this->getSelectTables());
        $rs = SqlTools::sqlQuery("SELECT ".$select.
                        " \nFROM ".SqlBuilder::from($this->get_from_tables()).
                        SqlBuilder::joins($this->joins)."\n".
                        SqlBuilder::where($this->where, $this->sql_where)."\n".
                        SqlBuilder::group_by($this->group_by)."\n".
                        SqlBuilder::order_by($this->order, $this->order_dir)."\n".
                        SqlBuilder::limit($this->offset, $this->limit));
        if($rs)
        {
            $this->load($rs);
            $this->_executed = true;
            if (!$this->lazyLoad() && !is_null($this->_foreign_object_waiting_for_association))
            {
                $this->associateForeignObject($this->_foreign_object_waiting_for_association[0], $this->_foreign_object_waiting_for_association[1]);
            }
        }
        else
            throw new Exception("Fatal error:".mysql_error());
    }


    public function delete()
    {
        if(!empty($this->order) || !is_null($this->limit) || !is_null($this->offset)) {
            throw new exception_MormImpossibleDeletion($this->base_class);
        }
        
        $rs = SqlTools::sqlQuery("DELETE {$this->base_class} \n".
            " FROM ".SqlBuilder::from($this->get_from_tables())."\n".
            SqlBuilder::joins($this->joins)."\n".
            SqlBuilder::where($this->where, $this->sql_where)."\n".
            SqlBuilder::group_by($this->group_by)."\n"
        );
    
        if(!$rs)
            throw new Exception("Fatal error:".mysql_error());
      
    }

    /**
     * @return integer
     */
    public function get_count()
    {
                
        if(is_null($this->_total_elements)) {
  
            $select = isset($this->group_by) ? sprintf("count(distinct(%s.%s))", key($this->group_by), $this->group_by[key($this->group_by)]) : 'count(1)'; 
            $rs = SqlTools::sqlQuery("SELECT $select
                            \nFROM ".SqlBuilder::from($this->get_from_tables()).
                            SqlBuilder::joins($this->joins).
                            SqlBuilder::where($this->where, $this->sql_where)."\n"
            );
            if($rs)
                $this->_total_elements = (int) mysql_result($rs, 0);
            else
                throw new Exception("Fatal error:".mysql_error());
            
        }
        
        return $this->_total_elements;

    }

    public function count()
    {
        return $this->get_count();
    }

    /**
     * @return array
     */
    private function get_from_tables()
    {
        $tables = array();
        foreach(array_diff(array_keys($this->classes), array_keys($this->join_classes)) as $class)
            $tables[$class] = MormDummy::get($class)->getTable();
        return $tables;
    }

    private function getSelectTables()
    {
        return array_merge($this->get_from_tables(), $this->join_aliases);
    }

    /**
     * @param resource $rs
     * @return void
     */
    private function load($rs)
    {
        $this->mormons = array();
        while($line = mysql_fetch_assoc($rs))
        {
            if($this->lazyLoad() && !$this->isBaseModelLoaded($line))
            {
                $this->mormons[$this->base_class.'_'.$this->extractMormonIdFromLine($line, $this->base_class)] = ':lazy_load';
                continue;
            }
            //check if base model is loaded and load it if not
            $base_model = $this->getLoadedBaseModel($line);
            foreach($this->joins as $join)
            {
                /**
                 * FIXME
                 * the first condition is a workaround to avoid having problems with n->m relationships. 
                 * I must find a way to load everybody according to the joins. 
                 */
                if($join->needToload() && $this->base_class == $join->getFirstClass() && !$join->secondIsABelongsToFor($this->base_class))
                {
                    $should_load = extractMatchingKeys(implode('\\'.MormConf::MORM_SEPARATOR, array(MormConf::MORM_PREFIX, $join->getSecondTableAlias(), '(\w+)')), $line);
                    if(!empty($should_load))
                    {
                        $base_model->loadForeignObjectFromMormons($join, $should_load);
                    }
                }
            }
            unset($base_model);
        }
        $this->mormon_keys = array_keys($this->mormons);
        $this->nb_mormons = count($this->mormons);
    }

    private function loadBaseModelForLine($line)
    {
        $model = Morm::FactoryFromMormons($this->base_class, $this, $line, $this->joins);
        $model_fields = $model->getTableDesc();
        if($model_fields->getPKey())
        {
            $key = $model->getPkeyVal(true);
            $this->mormons[$this->base_class.'_'.$key] = $model;
        }
        else //TODO check if this case (the table has no primary key) effectively works
            $this->mormons[] = $model;
        return $model;
    }

    private function isBaseModelLoaded($line)
    {
        $mormon_id = $this->extractMormonIdFromLine($line, $this->base_class);
        return isset($this->mormons[$this->base_class.'_'.$mormon_id]) ? $this->mormons[$this->base_class.'_'.$mormon_id] : false;
    }

    private function getLoadedBaseModel($line)
    {
        $base_model = $this->isBaseModelLoaded($line);
        if(false === $base_model)
        {
            $base_model = $this->loadBaseModelForLine($line);
        }
        return $base_model;
    }

    /**
     * @param array $object_array
     * @return boolean
     */
    private function isLoaded($class, $id)
    {
        return isset($this->mormons[$class.'_'.$id]);
    }

    private function extractMormonIdFromLine($line, $class)
    {
        $key = MormDummy::get($class)->getPkey();
        if(is_array($key))
        {
            $to_implode = array();
            foreach($key as $field_name)
            {
                $to_implode[] = $line[MormConf::MORM_PREFIX.MormConf::MORM_SEPARATOR.$class.MormConf::MORM_SEPARATOR.$field_name];
            }
            return implode(MormConf::MORM_SEPARATOR, $to_implode);
        }
        $ret = MormConf::MORM_PREFIX.MormConf::MORM_SEPARATOR.$class.MormConf::MORM_SEPARATOR.$key;
        return $line[$ret];
    }

    public function addMormFromArray($join, $to_load, &$to_associate = null)
    {   
        $model = Morm::FactoryFromMormons($this->base_class, $this, $to_load, $this->joins);
        if($model->getTableDesc()->getPKey())
            $this->mormons[$this->base_class.'_'.$model->getPkeyVal(true)] = $model;
        else
            $this->mormons[] = $model;
        if(is_object($to_associate))
            $model->loadForeignObject($join->getSource(), $to_associate);
        $this->mormon_keys = array_keys($this->mormons);
        $this->nb_mormons = count($this->mormons);
        $this->_executed = true;
    }

    public function associateForeignObject($referer, &$to_load)
    {
        if(!$this->_executed)
            $this->_foreign_object_waiting_for_association = array($referer, $to_load);
        else
        {
            foreach($this->mormons as $obj_id => $morm)
            {
                if($to_load->isForeignUsing($referer))//FIXME repair this when 'using' is set
                {
                    
                }
                else
                {
                    $j = new MormJoin($morm, $to_load, array('referer' => $referer));
                    $morm->loadForeignObject($j->getSource(), $to_load);
                }
            }
        }
    }

    /**
     * @return boolean
     */
    public function hasElements() {
        return !empty($this->mormons);
    }

    private function lookForClassFromFieldToSet($conditions)
    {
        foreach($conditions as $cond_field => $condition)
        {
            if($this->base_object->isForeignClassFromField($cond_field) && !is_array($condition))
            {
                $field_value = isset($this->class_from_field_value[$cond_field]) ? $this->class_from_field_value[$cond_field] : NULL;
                if(empty($field_value))
                    $this->class_from_field_value[$cond_field] = $condition;
                else if ($field_value != $condition)
                    throw new Exception('Impossible to redefine a condition on a \'class_form_field\' field');
            }
        }
    }

    private function getMorm($mormon_key)
    {
        if(is_object($this->mormons[$mormon_key]))
            return $this->mormons[$mormon_key]; 
        return call_user_func_array(array($this->base_class, 'FactoryFromId'), array($this->base_class, $this->extractPKeyFromMormonKey($mormon_key)));
    }

    private function extractPKeyFromMormonKey($mormon_key)
    {
        $key = $this->base_object->getPkey();
        if(is_array($key))
        {
            $to_find = array();
            foreach($key as $field_name)
                $to_find[] = '(?P<'.$field_name.'>[^\\'.MormConf::MORM_SEPARATOR.']+)';
            $regexp = '/'.$this->base_class.'_'.implode(MormConf::MORM_SEPARATOR, $to_find).'/';
            $matches = array();
            preg_match($regexp, $mormon_key, $matches);
            return $matches;//TODO remove the values having a numerical key first
        }
        $regexp = '/'.$this->base_class.'_'.'(?P<'.$key.'>[^\\'.MormConf::MORM_SEPARATOR.']+)'.'/';
        $matches = array();
        preg_match($regexp, $mormon_key, $matches);
        return $matches[$key];
    }
    
    public function first()
    {
        if(!$this->_executed)
            $this->limit(1);
        $this->rewind();
        return $this->current();
    }

    public function last()
    {
        $this->rewind();
        if($this->nb_mormons == 0) {
            return NULL;
        } else {
            return $this->mormons[$this->mormon_keys[$this->nb_mormons - 1]]; 
        }
    }

    /************ Iterator methods **************/
    public function current(){ 
        if (isset($this->mormon_keys[$this->key]) && 
            isset($this->mormons[$this->mormon_keys[$this->key]]))
        {
            return $this->getMorm($this->mormon_keys[$this->key]); 
        }
        return NULL;
    }

    public function key() { 
        return $this->mormon_keys[$this->key]; 
    }

    public function next(){ 
        $this->key++; 
    }

    public function rewind(){ 
        if(!$this->_executed)
            $this->execute();
        $this->key = 0; 
    }

    /**
     * @return boolean
     */
    public function valid() { 
        if(!$this->_executed)
            $this->execute();
        return $this->key < $this->nb_mormons;
    }

    /*********** ArrayAccess methods **************/
    // Méthode d'ajout d'une valeur dans le tableau
    public function offsetSet($offset, $value) {
        throw new Exception('Forbidden');
    }
    
    // Supprime une valeur du tableau
    public function offsetUnset($offset) {
        throw new Exception('Forbidden');
    }

    // Retourne une valeur contenue dans le tableau
    public function offsetGet($offset) {
        return $this->mormons[$this->mormon_keys[$offset]];
    }

    // Test si une valeur existe dans le tableau
    public function offsetExists($offset){
        return isset($this->mormons[$this->mormon_keys[$offset]]);
    }



    /**
     * DATA PROVIDERS 
     */

    public function toObj($linked_objs = array())
    {
        if(!$this->_executed)
            $this->execute();
        $obj = new Data();
        foreach($this->mormons as $obj_id => $morm)
        {
            $obj->$obj_id = $morm->toObj($linked_objs);
        }
        return $obj;
    }

    public function toJSON($opts=array())
    {
        return json_encode($this->toObj($opts));
    }

}

