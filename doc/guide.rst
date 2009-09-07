==============
Morm - PHP ORM
==============

.. contents:: Table of Contents

Introduction
============

Morm is a PHP_ Orm. For now Morm only support Mysql_.

Features:

* Create/Read/Update/Delete records in database
* Relations

 - OneToOne
 - OneToMany
 - ManyToMany

* STI Fields
* Polymorphism

CRUD
====

The First step before using Morm is to create PHP_ class.

Let's start with a very simple table *authors*:

.. code-block:: sql
   :linenos:

   CREATE TABLE `authors` (
         `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
         `name` VARCHAR( 255 ) NOT NULL,
         `email` VARCHAR( 255 ) NOT NULL
   ) ENGINE = InnoDB;

.. code-block:: php
   :linenos:

   <?php
   class Authors extends Morm
   {
       protected $_table = 'authors';
   }
   ?>

Create
------

.. code-block:: php
   :linenos:

   <?php
   $author = new Authors();
   $author->name = 'Foo';
   $author->email = 'foo@example.org';
   $author->save()
   ?>

Read
----

For looping over all authors:

.. code-block:: php
   :linenos:

   <?php
   $authors = new Mormons('authors');
   foreach ($authors as $author)
   {
        echo $authors->name;
   }
   ?>

Update
------

.. code-block:: php
   :linenos:

   <?php
   $author = new Authors(1);
   $author->name = 'Plop';
   $author->update(); // you can also use save()
   ?>

Delete
------

Just use the *delete()* method.

.. code-block:: php
   :linenos:

   <?php
   $author = new Authors(1);
   $author->delete();
   ?>

Relations
=========

One to many
-----------

Now we have a new table named *books*. The table books have one foreign key to *authors*.

.. code-block:: sql
   :linenos:

   CREATE TABLE `books` (
         `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
         `author_id` INT NOT NULL,
         `name` VARCHAR( 255 ) NOT NULL
   ) ENGINE = InnoDB;

Morm class declaration:

.. code-block:: php
   :linenos:

   <?php
   class Books extends Morm
   {
       protected $_table = 'books';

       protected $_foreign_keys = array('author_id' => array('table' => 'authors'));
   }

   // we can simply access to author record
   $book = new Books(1);
   echo $book->authors->name;
   ?>

STI Fields
==========

If you specify a type field in your table, Morm will instanciate a special class depend of the content.

.. code-block:: sql
   :linenos:

   CREATE TABLE `users` (
         `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
         `type` VARCHAR( 255 ) NOT NULL ,
         `name` VARCHAR( 255 ) NOT NULL
   ) ENGINE = InnoDB;

.. code-block:: php
   :linenos:

   <?php
   class Users extends Morm
   {
       protected $_table = 'users';
   }
   class Admin extends Users 
   {
       public function isAdmin()
       {
          return true;
       }
       // implement your specifique stuff here
   }
   class User extends Users
   {
       public function isAdmin()
       {
          return false;
       }
       // implement your specific stuff here
   }

   $newuser = new Users();
   $newuser->type = 'user';
   $newuser->name = 'Foo Bar';
   $newuser->save();

   $users = new Mormons('users');
   $user = $users->first();
   if ($user->isAdmin()) // return false in this case
   {
       echo "Welcome Admin";
   }
   else
   {
       echo "Welcome User";
   }
   ?>
   
If you are working in a legacy database you can also configure morm for using another field name.

.. code-block:: php
   :linenos:

   <?php
   class Users extends Morm
   {
       protected $_table = 'users';

       protected $sti_field = 'myfieldtype'; // specify you own STI field
   }
   ?>


Polymorphism
============

.. code-block:: sql
   :linenos:

   CREATE TABLE `comments` (
         `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
         `user_id` INT NOT NULL,
         `` VARCHAR( 255 ) NOT NULL
   ) ENGINE = InnoDB;'



Credits
=======

Morm is copyright (C) 2008-2009 AF83_ and Luc-Pascal Ceccaldi.

Contribute
==========

Morm is released under BSD.

..  _AF83: http://af83.com/
.. _Mysql: http://mysql.com/
.. _PHP: http://php.net/

