<?php
/**
 * Morm Utils
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class MormUtils
{
    /**
     * isEmpty 
     *
     * tries to reproduce php's "empty()" function's behaviour in a "not stupid" 
     * way
     *
     * @fixme strlen may be replaced by isset($str[0]) for better performance if 
     * we are sure it has the same effect
     * 
     *
     * @access private
     * @param mixed 
     * @return boolean
     */
    public function isEmpty($val)
    {
        if(is_string($val) && strlen($val) == 0)
            return true;
        if(is_numeric($val) && intval($val) == 0)
            return false;
        return empty($val);
    }  
}
