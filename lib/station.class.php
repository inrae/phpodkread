<?php
class Station extends ObjetBDD
{
    function __construct($pdo, $param = array())
    {
        $this->table = "station";
        $this->colonnes = array(
            "station_id" => array("key" => 1, "type" => 1),
            "station_name" => array("type" => 0)
        );
        parent::__construct($pdo, $param);
    }
    /**
     * Get station_id. If necessary, create station
     *
     * @param [type] $name
     * @return void
     */
    function getIdFromNameOrCreate($name)
    {
        $sql = "select station_id from station where station_name = :name";
        $row = $this->lireParamAsPrepared($sql, array("name" => $name));
        if ($row["station_id"] > 0) {
            $id = $row["station_id"];
        } else {
            $row = array("station_id" => 0, "station_name" => $name);
            $id = $this->ecrire($row);
        }
        return ($id);
    }
}
