<?php
include_once "lib/db.class.php";
class Dileme extends Db implements Database
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
    function setFormName(string $formname)
    {
        $this->formname = $formname;
    }
    function setOptionalParameters($op)
    {
        $this->optionalParameters = $op;
    }
    function setData(array $data)
    {
        foreach ($data[$this->formname] as $sampling) {
            /**
             * Search for fishing
             */
            if ($sampling["sampling-campaign_number"] == $this->optionalParameters["campaignNumber"]) {
                $sql = "select fishing_id
              from dileme.fishing
              join dileme.sampling using (sampling_id)
              where fishing_date::date = :fishing_date
              and fishing_number = :fishing_number
              and site_id = :site_id
              and engine_position_id = :engine_position_id";
                $sqlparam = array(
                    "site_id" => $sampling["sampling-site_id"],
                    "fishing_number" => $sampling["sampling-sample_number"],
                    "fishing_date" => $sampling["sampling-fishing_date"],
                    "engine_position_id" => $sampling["sampling-engine_position_id"]
                );
                if (!empty($sampling["sampling-depth_id"])) {
                    $sql .= " and depth_id = :depth_id";
                    $sqlparam["depth_id"] = $sampling["sampling-depth_id"];
                }
                $res = $this->execute($sql, $sqlparam);
                if ($res[0]["fishing_id"] > 0) {
                    $fishing_id = $res[0]["fishing_id"];
                    if ($this->modeDebug) {
                        $this->message["Fishing_id:" . $fishing_id];
                    }
                    /**
                     * Search for sample
                     */
                    $dsample = array(
                        "sample_id" => 0,
                        "fishing_id" => $fishing_id,
                        "comment" => $sampling["sampling-comment"],
                        "uuid" => $sampling["uuid"],
                        "shrimp" => $sampling["others_catch-shrimp"],
                        "gamare" => $sampling["others_catch-gamare"],
                        "mysidaceae" => $sampling["others_catch-mysidaceae"],
                        "crab" => $sampling["others_catch-crab"],
                        "gelatinous" => $sampling["others_catch-gelatinous"],
                        "others" => $sampling["others_catch-others"]
                    );
                    $sql = "select sample_id from dileme.sample
                    where fishing_id = :fishing_id";
                    $res = $this->execute($sql, array("fishing_id" => $dsample["fishing_id"]));

                    if ($res[0]["sample_id"] > 0) {
                        $dsample["sample_id"] = $res[0]["sample_id"];
                    } else {
                        $dsample["sample_id"] = $this->writeData("dileme", "sample", $dsample, "sample_id");
                    }
                    /**
                     * Treatment of each sample_taxon
                     */
                    /**
                     * Delete all precedent records
                     */
                    /*
                     * Inactivated - double recording allowed
                    $sql = "delete from dileme.individual i
                              using dileme.sample_taxon st
                              where st.sample_taxon_id = i.sample_taxon_id
                              and st.sample_id = :sample_id";
                    $this->execute($sql, array("sample_id" => $dsample["sample_id"]));
                    $sql = "delete from dileme.sample_taxon
                              where sample_id = :sample_id";
                    $this->execute($sql, array("sample_id" => $dsample["sample_id"]));
                    */
                    foreach ($sampling["CHILDREN"]["sample_taxon"] as $dst) {
                        $dst["sample_id"] = $dsample["sample_id"];
                        $dst["sample_taxon_id"] = 0;
                        if (empty($dst["development_stage_id"])) {
                            unset($dst["development_stage_id"]);
                        }
                        $dst["sample_taxon_id"] = $this->writeData("dileme", "sample_taxon", $dst, "sample_taxon_id");
                        /**
                         * Treatment of each individual
                         */
                        foreach ($dst["CHILDREN"]["individual"] as $di) {
                            $di["sample_taxon_id"] = $dst["sample_taxon_id"];
                            $di["individual_id"] = 0;
                            $this->writeData("dileme", "individual", $di, "individual_id");
                        }
                    }
                } else {
                    $this->message[] = "line " . $this->treatedNb + 1 .
                        ":  fishing not found. Site_id: " . $sampling["sampling-site_id"] .
                        ", fishing_date: " . $sampling["sampling-fishing_date"] .
                        ", fishing_number: " . $sampling["sampling-sample_number"] .
                        ", engine_position_id: " . $sampling["sampling-engine_position_id"] .
                        ", campaign_number: " . $sampling["sampling-campaign_number"] .
                        ", sampling-sort_date: " . $sampling["sampling-sort_date"];
                }
                $this->treatedNb++;
            }
        }
    }

    /**
     * Return the number of operations treated
     *
     * @return integer
     */
    function getTreatedNumber(): int
    {
        return $this->treatedNb;
    }

    function getMessage(): array
    {
        return $this->message;
    }
}
