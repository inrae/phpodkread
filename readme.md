# PHPODKread - an utility to process data entered with ODK Collect

## Objectives

ODK Collect allows to create forms easily, for all types of uses. The data entered in the forms then need to be recovered, to feed databases in particular.

PHPODKread makes it possible to read the zip files containing the data of the seized forms, to recreate the tree structure of the data, then either:

- to save the data in a json file
- to directly feed a database, by writing a class dedicated to this use.

The application can be launched in command line, or be integrated in a larger application.

## Launching the application from the command line

### Prerequisites

You must have php7.4 or later installed on your computer, with at least the following additional components

- php7.4-cli
- php7.4-json
- php7.4-pgsql (not tested with mysql)
- php7.4-readline
- php7.4-zip

### Usage

Rename the _param.inc.dist_ file to _param.inc.php_, then modify in particular:

- basedir : the folder where the software is installed
- displayfirstline=1 : for tests, this allows you to see the data as they are organized once the treatment is done
- noMove=1 : removes the movement of the processed files (to be restored in production)
- writedataindb=0 : to be activated once you have written the appropriate class to feed your database
- formname= : indicate the name of the form of odk. It is necessary to import data into database

Put the file to be processed (several files can also be processed at once) in the _import_ folder.

Then run the program :

```
php odkread.php
```

## Writing data to the database

### Principle

You must write a class adapted to your needs, to manage correctly the writing of information in the database.

Before writing the class, it is strongly advised to display the structure of the table containing the data, by executing the program with the option _\--displayfirstline=1_, to visualize the way the information is organized.

### Content of the class

The class must be declared as follows:

```
class Myclass [extends XXX] implements Database
```

Here is the beginning of the example class that is provided (file _lib/dileme.class.php_):

```
class Myclass extends Db implements Database
{

  private int $treatedNb = 0;
  private string $formname;

  function setConnection(PDO $connection)
  {
    $this->connection = $connection;
  }
  function setDebug(bool $modeDebug = false)
  {
    $this->modeDebug = $modeDebug;
  }
    function getTreatedNumber(): int
  {
    return $this->treatedNb;
  }
  function setFormName (string $formname) {
    $this->formname = $formname;
  }
  function setData(array $data)
  {
    [...]
  }
  function getMessage() :array
  {
    return $this->message;
  }
  function setOptionalParameters($op)
  {
    $this->optionalParameters = $op;
  }
}
```

It is in the _setData_ function that the database writing operations must be triggered.

The _$this->message_ array contains all the messages that will be displayed after processing. It can be used to indicate errors encountered during processing.

### Parameterization

To call the class, edit the _param.ini_ file, then :

- create a section corresponding to your database, for example \[dileme\]
- fill in the following information:
  - dsn : uri of connection to the database, according to the nomenclature used by PHP
  - login : login to use
  - password : corresponding password
  - classpath : path to the file containing the class
  - className : name of the class as written in the file

## Copyright

The application is published under MIT license.

Copyright © INRAE, 2022-2023

Translated with www.DeepL.com/Translator (free version)
