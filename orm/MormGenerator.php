<?php
/**
 *
 * generator for Morm class file
 *
 * @author Clément Hallet aka challet <challet@af83.com> 
 * @license BSD License (3 Clause) http://www.opensource.org/licenses/bsd-license.php)
 */
class MormGenerator {
    
    private $class_name;
    private $table;
    
    public function __construct($class_name, $table = NULL) {
        $this->class_name = $class_name;
        $this->table = $table;
    }
    
    public function run() {
        $file_name = $this->getFileName();
        $table = is_null($this->table) ? self::CamelCaseToLower($this->class_name) : $this->table;
        $this->class_name = self::LowerToCamel($this->class_name);
        if(!file_exists($file_name)) {
            $tmpl_eclass = <<<Q
<?php
    class %s extends Morm 
    {
        protected \$_table = '%s';
    }

Q;
            file_put_contents($file_name, sprintf($tmpl_eclass, $this->class_name, $this->table));
        } 
    }
    
    public function getFileName() {
        return sprintf('%s/%s.php',
            GENERATED_MODELS_PATH,
            $this->class_name
        );
    }
    
    
    public function check() {
        $file_name = $this->getFileName();
        if (file_exists($file_name)) {
            require_once $file_name;
            return class_exists($this->class_name) && in_array('Morm', class_parents($this->class_name)) ;
        } else {
            return FALSE;
        }
    }

    /**
     * Converts 'MyPrettyRabbit' into 'my_pretty_rabbit'
     *
     * @param   String  $str    String to convert
     * @return  String
     */
    public static function CamelCaseToLower($str = '') {
        if ( empty($str) ) return $str;
        return strtolower(implode('_', array_filter(preg_split('/([A-Z][a-z]*)/', $str, -1, PREG_SPLIT_DELIM_CAPTURE))));
    }

    /**
     * Convert 'my_pretty_rabbit' into 'MyPrettyRabbit'
     *
     * @param   String  $str    String to convert
     * @return  String
     */
    public static function LowerToCamel($str = '') {
        if ( empty($str) ) return $str;
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }

}
