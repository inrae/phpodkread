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
require_once 'lib/odk.class.php';
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
}
if (!$eot) {

    try {
        /**
         * Get the list of files to treat
         */
        $odk = new Odk($param["general"]);
        $csv = new Csv();
        $zip = new ZipArchive();
        $sourcefolder = $odk->sanitizePath($param["general"]["source"]);
        $tempfolder = $odk->sanitizePath($param["general"]["temp"]);
        $files = $odk->getListFromFolder($sourcefolder, $param["general"]["zipextension"]);
        if (count($files) == 0) {
            throw new OdkException("No files to treat in $sourcefolder");
        }

        foreach ($files as $file) {
            /**
             * Treatment of each zip file
             */
            if ($zip->open($sourcefolder . "/" . $file) !== true) {
                throw new OdkException("Unable to open the zip file $file");
            }
            /**
             * Extract all csv files
             */
            if (!$zip->extractTo($tempfolder)) {
                throw new OdkException("Unable to extract the files from the zip file $file");
            }

            $csvfiles = $odk->getListFromFolder($tempfolder, $param["general"]["csvextension"]);
            foreach ($csvfiles as $csvfile) {
                $odk->readCsvContent($csvfile, $csv->initFile($tempfolder . "/" . $csvfile, $param["general"]["separator"]));
            }
            /**
             * Generate the JSON file
             */

            $odk->generateStructuredData();
            if ($param["general"]["exportjson"] == 1) {
                $exportfolder = $odk->sanitizePath($param["general"]["export"]);
                printr($exportfolder);
                if (!$odk->verifyFolder($exportfolder)) {
                    throw new OdkException("Unable to open or create the export folder");
                }
                $jsonfilename = "odkread-" . date("YmdHis") . ".js";
                $jsonfile = fopen($exportfolder."/".$jsonfilename, 'w');
                fwrite($jsonfile, json_encode($odk->structuredData));
                fclose($jsonfile);
                $message->set("File $jsonfilename generated");
            }
            if ($param["general"]["displayfirstline"] == 1) {
                foreach ($odk->structuredData as $key => $row) {
                    echo "$key" . PHP_EOL;
                    foreach ($row as $r1) {
                        print_r($r1);
                        break;
                    }
                    break;
                }
            }
            if ($param["general"]["writedataindb"] == 1) {
                $dbmodel = $param["general"]["dbmodel"];
                $pdo = new PDO(
                    $param[$dbmodel]["dsn"],
                    $param[$dbmodel]["login"],
                    $param[$dbmodel]["password"]
                );
                $pdo->beginTransaction();
                $nbTreated = $odk->writeDataDB($param[$dbmodel]["classpath"], $param[$dbmodel]["className"], $pdo);
                $message->set("Number of forms treated: $nbTreated");
                $pdo->commit();
            }
            /**
             * End of treatment of the zip file
             */
            /**
             * Purge the temp folder
             */
            if ($param["general"]["tempfolderpurge"] == 1) {
                $odk->tempPurge();
            }
            if ($param["general"]["noMove"] != 1) {
                $odk->moveFile($file);
            }
            $message->set("File $file treated");
        }
    } catch (Exception $e) {
        $message->set($e->getMessage());
        if (isset($pdo)) {
            $pdo->rollBack();
        }
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
