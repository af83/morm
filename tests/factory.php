<?php

require_once('prepend.php');
/**
 * Todo remove duplication with sti tests
 */
class MyUser extends Morm
{
    protected $_table = "myuser" ;

    public static function createTable()
    {
        return 'CREATE TABLE `myuser` (
                                       `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                                       `name` VARCHAR( 255 ) NOT NULL,
                                       `sex` ENUM(\'f\',\'m\') NOT NULL,
                                       `groups` SET(\'admin\',\'user\',\'other\') NOT NULL,
                                       `location` VARCHAR( 255 ) NULL,
                                       `oneint` INT NOT NULL
                                        ) ENGINE = InnoDB;';
    }

    public static function dropTable()
    {
        return 'DROP TABLE `myuser`;';
    }
}

class TestMormFactory extends MormUnitTestCase
{
    public function mormSetUp()
    {
        $this->sql->queryDB(MyUser::createTable());
        $this->factory = new MormFactory('MyUser');
        $this->myuser = $this->factory->build();
    }

    public function mormTearDown()
    {
        $this->sql->queryDB(MyUser::dropTable());
    }

    public function testBuildFactoryDontFillAutoIncrementField()
    {
        $this->assertNull($this->myuser->id);
    }

    public function testBuildFactoryWithNullValue()
    {
        $this->assertNull($this->myuser->location);
    }

    public function testBuildFactoryEnumValue()
    {
        // loop for random issue
        for ($i = 0; $i < 10; $i++)
        {
            $myuser = $this->factory->build();
            $this->assertTrue(in_array($myuser->sex, array('f', 'm')), '$myuser->sex should be equal to f or m');
        }
    }

    public function testBuildFactoryWithSetValue()
    {
        $this->assertTrue(in_array($this->myuser->groups, array('admin', 'user', 'other')), 'set value not correct');
    }

    public function testBuildFactoryWithNumericValue()
    {
        $this->assertNotNull($this->myuser->oneint); // sometimes it's difficult to find some real field name
    }

}

