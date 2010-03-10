<?php
/**
 * SqlBuilder 
 * this is most probably the file you'd like to change in order to be able to 
 * use another database than mysql
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class SqlBuilder
{
    static function select($select)
    {
        $statement = array();
        foreach($select as $table => $val)
        {
            if(is_numeric($table))
            {
                $table = $val;
                $val = '*';
            }
            if(class_exists($table))
                $table_desc = TableDesc::getTable(MormDummy::get($table)->getTable()); 
            else
                $table_desc = TableDesc::getTable($table); 
            if(is_array($val))
            {
                $alias = $table;
                if(isset($val['morm_table_alias']))
                {
                    $alias = $val['morm_table_alias'];
                    unset($val['morm_table_alias']);
                }
                foreach($val as $field)
                {
                    if($table_desc->isField($field))
                        $statement[] = self::singleSelect($alias, $field);
                    else
                        $statement[] = $field;
                }
            }
            else
            {
                if(empty($val) || $val == '*')
                {
                    foreach($table_desc as $field => $field_desc)
                    {
                        $statement[] = self::singleSelect($table, $field);
                    }
                }
                else
                    $statement[] = self::singleSelect($table, $val);
            }
        }
        return implode(',', $statement);
    }

    static function select_with_aliases($select)
    {
        $statement = array();
        foreach($select as $alias => $table)
        {
            if(is_numeric($alias))
                $alias = $table;

            $table_desc = TableDesc::getTable($table); 
            foreach($table_desc as $field => $field_desc)
            {
                $statement[] = self::singleSelect($alias, $field);
            }
        }
        return implode(',', $statement);
    }

    static function singleSelect($table, $field, $prefix = MormConf::MORM_PREFIX)
    {
        return '`'.$table.'`.`'.$field.'` as '.self::selectAlias($table, $field, $prefix);
    }

    static function selectAlias($table, $field, $prefix = MormConf::MORM_PREFIX)
    {
        $prefix = is_string($prefix) ? $prefix : MormConf::MORM_PREFIX;
        return '`'.$prefix.MormConf::MORM_SEPARATOR.$table.MormConf::MORM_SEPARATOR.$field.'`';
    }

    static function from($tables)
    {
        $tables = !is_array($tables) ? array($tables) : $tables ;
        $from = array();
        foreach($tables as $alias => $table)
        {
            if(is_numeric($alias))
            $from []= sprintf("`%s` ", $table);
            else
            $from []= sprintf("`%s` AS `%s`", $table, $alias);
        }
        return ' '.implode(',', $from).' ';
    }

    static function joins($joins)
    {
        $ret = array();
        foreach($joins as $join)
        {
            $ret[] = self::singleJoin($join);
        }
        return implode(' ', $ret);
    }

    static function singleJoin($join)
    {
        return sprintf(" %s JOIN `%s` AS `%s` ON `%s`.`%s`=`%s`.`%s` ", 
                       $join->getDirection(),
                       $join->getSecondObj()->getTable(),
                       $join->getSecondTableAlias(),
                       $join->getFirstTableAlias(),
                       $join->getFirstKey(),
                       $join->getSecondTableAlias(),
                       $join->getSecondKey());
    }

    static function where($conditions, $sql_where='')
    {
        if(empty($conditions) && empty($sql_where))
            return '';
        if(empty($conditions))
            $conditions = array();
        $where = array();
        foreach($conditions as $table => $condition)
        {
            if(is_array($condition))
            {
                foreach($condition as $k => $cond)
                    $where[] = self::singleWhere($table, array($k => $cond));
            }
            else
                $where[] = self::singleWhere($table, $condition);
        }
        if(empty($where))
            return empty($sql_where) ? '' : 'WHERE '.$sql_where;
        return empty($sql_where) ? 'WHERE '.implode(' AND ', $where) : 'WHERE '.implode(' AND ', $where).' AND '.$sql_where;
    }

    static function singleWhere($table, $condition)
    {
        $field = array_keys($condition);
        $field = $field[0];
        $operator = '=';
        if(is_array($condition[$field]) && isset($condition[$field]['operator']))
        {
            $operator = $condition[$field]['operator'];
            $condition[$field] = $condition[$field][0];
        }
        //$table_desc = TableDesc::getTable($table);
        if(is_array($condition[$field]))
        {
        //    foreach($condition[$field] as $key => $value)
        //        settype($condition[$field][$key], $table_desc->$field->php_type);
            $operator = 'IN';
            return '`'.$table.'`.`'.$field.'` '.$operator.' ('.SqlTools::formatSqlValue($condition[$field]).')';

        }
        //settype($condition[$field], $table_desc->$field->php_type);
        return '`'.$table.'`.`'.$field.'` '.$operator.' '.SqlTools::formatSqlValue($condition[$field]);
    }

    static function group_by($group_by)
    {
        if(is_null($group_by))
            return '';
        $alias = key($group_by);
        return sprintf(" GROUP BY `%s`.`%s` ", $alias, $group_by[$alias]);
    }

    static function order_by($orders, $dir)
    {
        if(empty($orders))
            return '';
        $order_by = array();
        foreach($orders as $table => $order)
        {
            if(is_array($order))
            {
                if(isset($order['sql']))
                {
                    $order_by[] = $order['sql'];
                    unset($order['sql']);
                }
                foreach($order as $k => $ord)
                    $order_by[] = self::singleOrder_By($table, $ord);
            }
            else
                $order_by[] = self::singleOrder_By($table, $order);
        }
        return empty($order_by) ? '' : ' ORDER BY '.implode(',', $order_by).' '.$dir;
    }

    static function singleOrder_By($table, $order)
    {
        return '`'.$table.'`.`'.$order.'`';
    }

    static function limit($offset, $limit) 
    {
        if(!is_null($limit))
        {
            return is_null($offset) ? sprintf(" limit %d", $limit) : sprintf(" limit %d,%d", $offset, $limit) ;
        }
        return '';
    }

    static function set ($values)
    {
        $set = array();
        foreach($values as $field => $value)
            $set[] = '`'.$field.'`='.SqlTools::formatSqlValue($value);
        return 'set ' . implode(' , ', $set);
    }

}
