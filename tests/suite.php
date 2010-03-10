<?php
require_once('prepend.php');

class AllTests extends TestSuite {
    function AllTests() {
        $this->TestSuite('All tests');
        $testsFile = array('basics', 'fielddesc', 
                           'tabledesc', 'sqltool', 
                           'oneToMany', 'sti',
                           'factory', 'field');
        foreach ($testsFile as $file)
        {
            $this->addFile(dirname(__FILE__) .'/'. $file . '.php');
        }
    }
}


simpletest_autorun();
