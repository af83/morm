<?php
/**
 *
 * Various conf for Morm, especially autoload methods
 *
 * @author Luc-pascal Ceccaldi aka moa3 <luc-pascal@ceccaldi.eu> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class MormConf
{
    /**
     * cache for the morm_conf.ini file
     */
    static private $_morm_conf;

    /**
     * relativ path to the morm_conf.ini file 
     */
    const INI_CONF_FILE = 'morm_conf.ini';
    
    /**
     *  separator used for the generated SQL aliases
     *  @todo move sowhere else (probably in SQLTools)
     */
    const MORM_SEPARATOR = '|';
    const MORM_PREFIX    = '';
    
    /**
     * @deprecated : use getClassName instead
     */
    public static function generateMormClass($class_name) {
        return self::getClassName($class_name);
    }
    
    /**
     * getClassName
     *
     * more like a model autoloader than a generator
     * tries to bind the given class_name on an existing class either by looking 
     * in the morm_conf.ini file or in the __autoloader.
     * If no class is found, tries to generate a class extending Morm.
     * 
     * @todo refactor this a bit
     * @param string $class_name 
     * @access public
     * @return found or generated class name
     */
    public static function getClassName($class_name, $class_parent = 'Morm')
    {
        $class_name = self::isInConf($class_name, $class_parent) ? self::getFromConf($class_name, $class_parent) : $class_name;
        $table = $class_name;
        if(class_exists($class_name)) {
            if(in_array('Morm', class_parents($class_name)))
                return $class_name;
            $class_name = 'm_'.$class_name;
        }
        
        $generator = new MormGenerator($class_name, $table);
        $generator->run();

        if($generator->check()) {
            return $class_name;
        } else {
            throw new MormSqlException('class '.$class_name.' is not a Morm');
        }
       
    }

    /**
     * getIniConf 
     *
     * cache and return the parsed morm_conf.ini file
     * 
     * @return array parsed ini file
     */
    public static function loadIniConf()
    {
        if(!isset(self::$_morm_conf))
        {
            self::$_morm_conf = parse_ini_file(MORM_CONF_PATH.self::INI_CONF_FILE, TRUE);
        }
        return self::$_morm_conf;
    }

    /**
     * isInConf 
     *
     * looks for the given class name in the morm_conf.ini file
     * 
     * @param string $alias_name 
     * @param string $class_parent
     * @return boolean
     */
    public static function isInConf($alias_name, $class_parent = 'Morm')
    {
        self::loadIniConf();
        return isset(self::$_morm_conf[$class_parent][$alias_name]);
    }
    
    
    public static function getFromConf($alias_name, $class_parent = 'Morm') {
        self::loadIniConf();
        return self::$_morm_conf[$class_parent][$alias_name];
    } 

    public static function getAliasForClass($class_name, $class_parent = 'Morm') {
        self::loadIniConf();
    	$alias_for_class = array_flip(self::$_morm_conf[$class_parent]);
    	return $alias_for_class[$class_name];
    }

}
