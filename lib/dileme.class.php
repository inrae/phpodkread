<?php
include_once "lib/db.class.php";
class Dileme extends Db implements Database
{

  private int $treatedNb = 0;
  public $message = array();

  function setConnection(PDO $connection)
  {
    $this->connection = $connection;
  }
  function setDebug(bool $modeDebug = false)
  {
    $this->modeDebug = $modeDebug;
  }
  function setData(array $data)
  {
    foreach ($data["saisie_labo_1"] as $sampling) {
      /**
       * Search for fishing
       */

      $sql = "select fishing_id
              from dileme.fishing
              join dileme.sampling using (sampling_id)
              where fishing_date::date = :fishing_date
              and fishing_number = :fishing_number
              and site_id = :site_id
              and engine_position_id = :engine_position_id";

      $res = $this->execute(
        $sql,
        array(
          "site_id" => $sampling["sampling-site_id"],
          "fishing_number" => $sampling["sampling-sample_number"],
          "fishing_date" => $sampling["sampling-fishing_date"],
          "engine_position_id" => $sampling["sampling-engine_position_id"]
        )
      );
      if ($res[0]["fishing_id"] > 0) {
        $fishing_id = $res[0]["fishing_id"];

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
              where uuid = :uuid";
        $res = $this->execute($sql, array("uuid" => $dsample["uuid"]));

        if ($res[0]["sample_id"] > 0) {
          $dsample["sample_id"] = $res[0]["sample_id"];
        }
        $dsample["sample_id"] = $this->writeData("dileme", "sample", $dsample, "sample_id");
        /**
         * Remove pre-existent records in sample_taxon and individuals
         */
        $sql = "delete from dileme.individual i
              using dileme.sample_taxon st
              where i.sample_taxon_id = st.sample_taxon_id
              and st.sample_id = :sample_id";
        $sqlData = array("sample_id" => $dsample["sample_id"]);
        $this->execute($sql, $sqlData);
        $sql = "delete from dileme.sample_taxon where sample_id = :sample_id";
        $this->execute($sql, $sqlData);
        /**
         * Treatment of each sample_taxon
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
          ", engine_position_id: " . $sampling["sampling-engine_position_id"];
      }
      $this->treatedNb++;
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
