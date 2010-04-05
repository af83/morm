<?php

class MorphinxIndexer
{

    const CONF_TPL_FILE = 'sphinx.conf.tpl.php'; 

    private $models = array();

    private $conf = array();

    public $global_conf = array();

    public $sources = array();

    public $sources_count = 0;

    public function __construct($args)
    {
        $this->setModelsToIndex($args['models']);
        if(isset($args['conf']))
            $this->setConf($args['conf']);
        $this->getGlobalConf();
    }

    private function getSourceFromModel($model_name, $index = null)
    {
        return new MorphinxSource($model_name, $index);
    }

    public function generateConf()
    {
        foreach($this->models as $model_name => $index)
        {
            if(is_array($index)){
                foreach($index as $i)
                {
                    $this->sources []= $this->getSourceFromModel($model_name, $i);
                    $this->sources_count++;
                }
            }
            else{
                $this->sources []= $this->getSourceFromModel($model_name);
                $this->sources_count++;
            }
        }
        ob_start();
        require self::CONF_TPL_FILE;
        $conf = ob_get_contents();
        ob_clean();
        return $conf;
    }

    private function setModelsToIndex($models)
    {
        $this->models = $models;
    }

    private function setConf($conf)
    {
        $this->conf = $conf;
    }

    public function getGlobalConf()
    {
        $global_conf_file = 'conf.ini';
        if(!file_exists($global_conf_file)) 
            die("The global conf file should exist. If it does not, you should create it by doing: \ncp /tools/sphinx/conf_sample.ini ".$global_conf_file."\n and edit the conf.ini file for your needs\n");
        $this->global_conf = parse_ini_file($global_conf_file);
    }
}
