<?php

/**
 * Classe non instanciable de base pour l'ensemble des vues
 *
 * @author quinton
 *        
 */
class Vue
{

    /**
     * Donnees a envoyer (cas hors html)
     *
     * @var array
     */
    protected $data = array();

    /**
     * Assigne une valeur
     *
     * @param $value
     * @param string $variable
     */
    function set($value, $variable = "")
    {
        if (strlen($variable) > 0) {
            $this->data[$variable] = $value;
        } else {
            $this->data = $value;
        }
    }

    /**
     * Declenche l'affichage
     *
     * @param string $param
     */
    function send($param = "")
    {
    }

    /**
     * Fonction recursive d'encodage html
     * des variables
     *
     * @param string|array $data
     * @return string
     */
    function encodehtml($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->encodehtml($value);
            }
        } else {
            $data = htmlspecialchars($data, ENT_QUOTES);
        }
        return $data;
    }
}

class VueCsv extends Vue
{

    private $filename = "";

    private $delimiter = ";";

    private $header = array();

    /**
     * Reecriture pour traiter le cas où l'info est mono-enregistrement
     *
     * @param array $value
     * @param string $variable
     * @return void
     */
    function set($value, $variable = "")
    {
        if (is_array($value[0])) {
            $this->data = $value;
        } else {
            $this->data[] = $value;
        }
    }

    function send($filename = "", $delimiter = "")
    {
        if (count($this->data) > 0) {
            if (strlen($filename) == 0) {
                $filename = $this->filename;
            }
            if (strlen($filename) == 0) {
                $filename = "export-" . date('Y-m-d-His') . ".csv";
            }
            if (strlen($delimiter) == 0) {
                $delimiter = $this->delimiter;
            }
            /*
             * Preparation du fichier
             */
            $fp = fopen($filename, 'w');
            /*
             * Traitement de l'entete
             */
            fputcsv($fp, array_keys($this->data[0]), $delimiter);
            /*
             * Traitement des lignes
             */
            foreach ($this->data as $value) {
                fputcsv($fp, $value, $delimiter);
            }
            fclose($fp);
        }
    }

    /**
     * Fonction parcourant le tableau de donnees, pour extraire l'ensemble des colonnes
     * et recreer un tableau utilisable en export csv avec toutes les colonnes possibles
     */
    function regenerateHeader()
    {
        /*
         * Recherche toutes les entetes de colonnes
         */
        foreach ($this->data as $row) {
            foreach ($row as $key => $value) {
                if (!in_array($key, $this->header)) {
                    $this->header[] = $key;
                }
            }
        }
        /*
         * Reformate le tableau pour integrer l'ensemble des colonnes disponibles
         */
        $data = $this->data;
        $this->data = array();
        foreach ($data as $row) {
            $newline = array();
            foreach ($this->header as $key) {
                $newline[$key] = $row[$key];
            }
            $this->data[] = $newline;
        }
    }

    /**
     * Affecte le nom du fichier d'export
     *
     * @param string $filename
     */
    function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Affecte le separateur de champ
     *
     * @param string $delimiter
     */
    function setDelimiter($delimiter)
    {
        if ($delimiter == "tab") {
            $delimiter = "\t";
        }
        $this->delimiter = $delimiter;
    }
}