<?php

/**
 * Created by Eric Quinton - June 2022
 * Copyright © INRAE
 * MIT License
 */

error_reporting(E_ERROR);
require_once 'lib/Message.php';
require_once 'lib/fonctions.php';
require_once 'lib/csv.class.php';
require_once 'lib/vue.class.php';
require_once 'lib/ObjetBDD_functions.php';
require_once 'lib/ObjetBDD.php';
require_once 'lib/station.class.php';
require_once 'lib/coef.class.php';
$message = new Message();
/**
 * End of Treatment
 */
$eot = false;

/**
 * Default options
 */
$message->set("ODKread: read the contents of the files downloaded from the ODK-Collect server");
$message->set("Licence : MIT. Copyright © 2022 - Éric Quinton, INRAE - EABX - FR33 Cestas");
/**
 * Traitement des options de la ligne de commande
 */
if ($argv[1] == "-h" || $argv[1] == "--help") {

    $message->set("Options :");
    $message->set("-h ou --help : ce message d'aide");
    $message->set("--station=stationName : nom de la station (obligatoire). Le nom doit correspondre à une section dans le fichier ini");
    $message->set("--dsn=pgsql:host=server;dbname=database;sslmode=require : PDO dsn (adresse de connexion au serveur selon la nomenclature PHP-PDO)");
    $message->set("--login= : nom du login de connexion");
    $message->set("--password= : mot de passe associé");
    $message->set("--schema=public : nom du schéma contenant les tables");
    $message->set("--source=source : nom du dossier contenant les fichiers source");
    $message->set("--treated=treated : nom du dossier où les fichiers sont déplacés après traitement");
    $message->set("--param=param.ini : nom du fichier de paramètres (ne pas modifier sans bonne raison)");
    $message->set("--filetype=csv : extension des fichiers à traiter");
    $message->set("--noMove=1 : pas de déplacement des fichiers une fois traités");
    $message->set("Les fichiers à traiter doivent être déposés dans le dossier import");
    $message->set("Une fois traités, les fichiers sont déplacés dans le dossier treated");
    $eot = true;
} else {
    /**
     * Processing args
     */
    $moveFile = true;
    $numBac = "";
    $params = array();
    for ($i = 1; $i <= count($argv); $i++) {
        $arg = explode("=", $argv[$i]);
        $params[$arg[0]] = $arg[1];
    }
}
if (!$eot) {
    if (!isset($params["param"])) {
        $params["param"] = "./param.ini";
    }
    /**
     * Get the parameters from param.ini file
     *
     */
    if (!file_exists($params["param"])) {
        $message->set("Le fichier de paramètres " . $params["param"] . " n'existe pas");
        $eot = true;
    } else {
        $param = parse_ini_file($params["param"], true);
        foreach ($params as $key => $value) {
            $param["general"][substr($key, 2)] = $value;
        }
    }

    /**
     * Connexion à la base de données
     */
    try {
        $pdo = connect($param["general"]["dsn"], $param["general"]["user"], $param["general"]["password"], $param["general"]["schema"]);
        $station = new Station($pdo);
        $coef = new Coef($pdo);
    } catch (Exception $e) {
        $message->set("Erreur de connexion à la base de données :");
        $message->set($e->getMessage());
        $eot = true;
    }
}
if (!$eot) {

    try {
        /**
         * Verify if the temp folder exists and open it
         */
        $temp = opendir($param["general"]["temp"]);
        if (!file_exists($param["general"]["temp"])) {
            if (mkdir($param["general"]["temp"]) === false) {
                throw new OdkException("Impossible to create the temp folder");
            }
        }
        $temp = opendir($param["general"]["temp"]);
        if (!$temp) {
            throw new OdkException("Impossible to open the temp folder");
        }
        /**
         * Get the list of files to treat
         */
        $sourcefolder = $param["general"]["source"];
        $tempfolder = $param["general"]["temp"];
        $files = getListFromFolder($sourcefolder, $param["general"]["zipextension"]);
        if (count($files) == 0) {
            throw new OdkException("No files to treat in $sourcefolder");
        }
        $csv = new Csv();
        $zip = new ZipArchive();
        foreach ($files as $file) {
            /**
             * Treatment of each zip file
             */
            if ($zip->open($sourcefolder . "/" . $file) !== true) {
                throw new OdkException("Impossible to open the zip file $file");
            }
            $raw = array();
            $dataIndex = array();
            $mainfile = "";
            /**
             * Extract all csv files
             */
            if (!$zip->extractTo($tempfolder)) {
                throw new OdkException("Impossible to extract the files from the zip file $file");
            }

            $csvfiles = getListFromFolder($tempfolder, $param["general"]["csvextension"]);
            foreach ($csvfiles as $csvfile) {
                $raw[$csvfile]["data"] = $csv->initFile($tempfolder . "/" . $csvfile);
                /**
                 * Extract the name of the object (tablename probably)
                 */
                $name = substr($csvfile, (strlen($param["general"]["csvextension"] + 1) * -1));
                $postiret = strpos($name, "-");
                !$postiret ? $raw[$csvfile]["name"] = $name : $raw[$csvfile]["name"] = substr($name, $postiret + 1);
                /**
                 * Generate the index
                 */
                if (empty($raw[$csvfile]["data"][0]["PARENT_KEY"])) {
                    $mainfile = $csvfile;
                }
                foreach ($raw[$csvfile]["data"] as $k => $line) {
                    if (!empty($line["PARENT_KEY"])) {
                        $dataIndex[$line["PARENT_KEY"]][] = array(
                            "KEY" => $k,
                            "filename" => $csvfile
                        );
                    }
                }
            }
            /**
             * Generate the JSON file
             */
            /**
             * Create the entries for the main table
             */
            if (empty($mainfile)) {
                throw new OdkException("The main file could not be defined");
            }
            $datajs = array($raw[$mainfile][$name] => $raw[$mainfile["data"]]);
            foreach ($datajs[$raw[$mainfile][$name]] as $k => $v) {
                /**
                 * Generate the uuid of the line
                 */
                $datajs[$raw[$mainfile][$name]][$k]["uuid"] = substr($v["KEY"], 5);
                /**
                 * Search if exists a child from the index array
                 */
                if (count($dataIndex[$v[$k]]) > 0) {
                    foreach ($dataIndex[$v[$k]] as $dv) {
                        $datajs[$raw[$mainfile][$name]][$k]["CHILDREN"][$raw[$dv["filename"]["name"]]][] = $raw[$dv["filename"]]["KEY"];
                    }
                }
            }
            $jsonContent = json_encode($datajs);
            $jsonfilename = $raw[$mainfile][$name].".js";
            $jsonfile = fopen($jsonfilename, 'w');
            fwrite($jsonfile, $jsonContent);
            fclose($jsonfile);

            /**
             * End of treatment of the zip file
             */
            /**
             * Purge the temp folder
             */
            folderPurge($tempfolder);
            if ($param["general"]["noMove"] != 1) {
                rename($param["general"]["source"] . "/" . $file, $param["general"]["treated"] . "/" . $file);
            }
            $message->set("File $file treated - File $jsonfilename generated");
        }
    } catch (Exception $e) {
        $message->set($e->getMessage());
    }
}

/**
 * Display messages
 */
if (!stripos(PHP_OS, "WIN")) {
    $windows = false;
} else {
    $windows = true;
}
foreach ($message->get() as $line) {
    if ($windows) {
        utf8_decode($line);
    }
    echo ($line . PHP_EOL);
}
echo (PHP_EOL);
