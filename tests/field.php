<?php
require_once('prepend.php');

class TestMormFieldSqlFunction extends MormTestCaseWithTableAuthors
{
    public function testConstructMormFieldSqlFunction()
    {
        $field = new MormFieldSqlFunction('MYSQLFUCNTION()');
        $this->assertEqual('MYSQLFUCNTION()', $field);
    }

    public function testformatSqlValue()
    {
        $field = new MormFieldSqlFunction('MYSQLFUCNTION()');
        $this->assertEqual('MYSQLFUCNTION()', SqlTools::formatSqlValue($field));
    }
  
}
