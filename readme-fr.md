# PHPODKread - un utilitaire pour traiter les données saisies avec ODK Collect

## Objectifs

ODK Collect permet de créer des formulaires facilement, pour tous types d'usages. Les données saisies dans les formulaires ont ensuite besoin d'être récupérées, pour alimenter notamment des bases de données.

PHPODKread permet de lire les fichiers zip contenant les données des formulaires saisis, de recréer l'arborescence des données, puis soit :

- de sauvegarder les données dans un fichier json
- d'alimenter directement une base de données, moyennant l'écriture d'une classe dédiée à cet usage.

L'application peut être lancée en ligne de commande, ou être intégrée dans une application plus vaste.

## Lancement de l'application en ligne de commande

### Prérequis

Vous devez avoir installé php7.4 ou ultérieur dans votre ordinateur, avec au minimum les composants complémentaires suivants :

- php7.4-cli
- php7.4-json
- php7.4-pgsql ou php7.4-mysql selon la base de données concernée
- php7.4-readline
- php7.4-zip

### Utilisation

Renommez le fichier param.inc.dist en param.inc.php, puis modifiez notamment :

- basedir : le dossier où est implanté le logiciel
- displayfirstline=1 : pour les tests, cela vous permet de visualiser les données telles qu'elles sont organisées une fois le traitement réalisé
- noMove=1 : supprime le déplacement des fichiers traités (à rétablir en production)
- writedataindb=0 : à activer une fois que vous aurez écrit la classe adéquate pour alimenter votre base de données

Déposez le fichier à traiter (plusieurs fichiers peuvent également être traités en une seule fois) dans le dossier *import*.

Lancez ensuite le programme :

~~~
php odkread.php
~~~

## Écriture des données en base de données

### Principe

Vous devez écrire une classe adaptée à votre besoin, pour gérer correctement l'écriture des informations dans la base de données.

Avant d'écrire la classe, il est fortement conseillé d'afficher la structure du tableau contenant les données, en exécutant le programme avec l'option *--displayfirstline=1*, pour bien visualiser la manière dont les informations sont organisées.

### Contenu de la classe

La classe doit être déclarée ainsi :

~~~
class Myclass [extends XXX] implements Database
~~~

Voici le début de la classe d'exemple qui est fournie (fichier *lib/dileme.class.php*) :
~~~
class Myclass extends Db implements Database
{

  private int $treatedNb = 0;

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
  function setData(array $data)
  {
    [...]
  }
}
~~~

C'est dans la fonction *setData* que les opérations d'écriture en base de données doivent être déclenchées.

### Paramétrage

Pour appeler la classe, éditez le fichier *param.ini*, puis :

- créez une section correspondant à votre base de données, par exemple [dileme]
- renseignez les informations suivantes :
  - dsn : uri de connexion à la base de données, selon la nomenclature utilisée par PHP
  - login : login de connexion à utiliser
  - password : mot de passe correspondant
  - classpath : chemin d'accès au fichier contenant la classe
  - className : nom de la classe telle qu'écrite dans le fichier

## Copyright

L'application est publiée sous licence MIT.

Copyright © INRAE, 2022
