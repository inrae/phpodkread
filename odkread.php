<?php

/**
 * Created by Eric Quinton - June 2022
 * Copyright © INRAE
 * MIT License
 */

error_reporting(E_ERROR);
require_once 'lib/message.php';
require_once 'lib/functions.php';
require_once 'lib/csv.class.php';
require_once 'lib/odk.class.php';
$message = new Message();
/**
 * End of Treatment
 */
$eot = false;

/**
 * Default options
 */
$message->set("PHPODKread: read the contents of the files downloaded from the ODK-Collect server");
$message->set("Licence : MIT. Copyright © 2022 - Éric Quinton, INRAE - EABX - FR33 Cestas");
/**
 * Traitement des options de la ligne de commande
 */
if ($argv[1] == "-h" || $argv[1] == "--help") {

    $message->set("Options:");
    $message->set("-h or --help: this help message");
    $message->set("All parameters in the section [general] of the file param.ini can be used");
    $message->set("in the command line, as --param=value");
    $message->set("--basedir= : default folder of the application");
    $message->set("--source=import : folder where the zip files are downloaded");
    $message->set("--dest=treated : folder where the treated files are moved");
    $message->set("--temp=temp : temp folder, used to open zip files");
    $message->set("--zipextension=zip : extension of zip files");
    $message->set("--csvextension=csv : extension of csv files");
    $message->set("--param=param.ini : name of the parameter file");
    $message->set("--nomove=0 : if 1, the files aren't moved after treatment");
    $message->set("--separator=comma : field separator used in the csv files");
    $message->set("--exportjson=1 : save the content of data in a json file");
    $message->set("--export=export : folder where the json files are recorded");
    $message->set("--displayfirstline=0 : if 1, display the first record");
    $message->set("--tempfolderpurge=1 : purge the temp folder of csv files");
    $message->set("--writedataindb=1 : call a subprogram to write data into the database");
    $message->set("--separator=comma : field separator used in the csv files");
    $message->set("--dbmodel= : name of the section of the param.ini file witch contains the description of the database recording");
    $message->set("--debug=0 : if 1, active the debug mode");
   $message->set("");
   $message->set("Content of the dbmodel section:");
   $message->set("dsn: connection chain to the database (PDO syntax)");
   $message->set("login: used login to connect the database");
   $message->set("password: associated password");
   $message->set("classpath: name of the file whitch contains the class used to write into the database");
   $message->set("className: name of the class used to write into the database. The class must implements the interface Database");

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
