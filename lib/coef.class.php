<?php

class Coef extends ObjetBDD
{
    private $month = array(
        "Janvier" => "01",
        "Février" => "02",
        "Mars" => "03",
        "Avril" => "04",
        "Mai" => "05",
        "Juin" => "06",
        "Juillet" => "07",
        "Août" => "08",
        "Septembre" => "09",
        "Octobre" => "10",
        "Novembre" => "11",
        "Décembre" => "12"
    );
    function __construct($pdo, $param = array())
    {
        $this->table = "coef";
        $this->colonnes = array(
            "coef_id" => array("key" => 1, "type" => 1),
            "coef_type_id" => array("type" => 1),
            "station_id" => array("type" => 1),
            "daydate" => array("type" => 0),
            "hour" => array("type" => 0),
            "coef" => array("type" => 1)
        );
        parent::__construct($pdo, $param);
    }

    /**
     * Ajout des donnees dans la base
     *
     * @param array $data: ligne d'infos a traiter
     * @param array $param: parametres lies a la station
     * @return void
     */
    function setValue($data, $param)
    {
        $my = explode(" ", utf8_encode($data[0]));
        $dd = explode(" ", $data[1]);
        $row = array();
        $row["station_id"] = $data["station_id"];
        $row["daydate"] = $my[1] . "-" . $this->month[$my[0]] . "-" . $dd[1];
        /**
         * Traitement des marees
         */
        for ($type = 1; $type < 3; $type++) {
            for ($occurrence = 1; $occurrence < 3; $occurrence++) {
                $this->calculHoraire($row, $data, $type, $occurrence, $param);
            }
        }
    }
    /**
     * Traitement de chaque cas (pleine mer, basse mer, premières ou secondes valeurs)
     *
     * @param array $row : donnees preparees
     * @param array $data : donnees brutes
     * @param int $type: 1 : pleine mer, 2 : basse mer
     * @param int $occurrence : numero d'occurrence dans la ligne de donnees
     * @param [type] $param : parametres liés à la station
     * @return void
     */
    function calculHoraire($row, $data, $type, $occurrence, $param)
    {
        if ($type == 1) {
            $occurrence == 1 ? $field = 3 : $field = 7;
        } else {
            $occurrence == 1 ? $field = 4 : $field = 8;
        }
        $occurrence == 1 ? $coef = $data[2] : $coef = $data[6];
        /**
         * Recuperation du coefficient manquant a partir du premier de la journee 
         * point a faire vérifier par le SHOM
         */
        if (strlen($coef) == 0) {
            $coef = $data[2];
        }
        if (strlen($data[$field]) > 0 && $coef > 0) {
            $realDate = date_create_from_format("Y-m-d H:i",$row["daydate"] . " " . $data[$field] );
            /**
             * Ajout du décalage
             */
            $decalage = $this->calculDecalage($coef, $type, $param);
            $realDate->add(new DateInterval("PT" . $decalage . "M"));
            $row["daydate"] = $realDate->format("Y-m-d");
            $row["hour"] = $realDate->format("H:i:s");
            $row["coef_type_id"] = $type;
            $row["coef"] = $coef;
            $row["coef_id"] = 0;
            $this->ecrire($row);
        }
    }
    /**
     * Calcul du décalage
     *
     * @param int $coef : coefficent de la marée
     * @param int $type : 1 : pleine mer, 2 : basse mer
     * @param array $param
     * @return int
     */
    function calculDecalage($coef, $type, $param)
    {
        $type == 1 ? $racine = "pm" : $racine = "bm";
        return intval((($param[$racine . "heure95"] - $param[$racine . "heure45"]) * (($coef - 45) / 50)) + $param[$racine . "heure45"]);
    }
}
