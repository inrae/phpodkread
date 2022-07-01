<?php
include_once "lib/db.class.php";
class Dileme extends Db implements Database
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
  function setData(array $data)
  {
    foreach ($data["saisie_labo_1"] as $sampling) {
      $dsampling = array(
        "sampling_id" => 0,
        "campaign_number" => $sampling["sampling-campaign_number"],
        "sampling_date" => $sampling["sampling-fishing_date"],
        "site_id" => $sampling["sampling-site_id"],
        "project_id" => 1,
        "operators" => $sampling["sampling-operators"],
        "depth_id" => $sampling["sampling-depth_id"],
        "protocol_id" => $sampling["sampling-sorttype"]
      );
      /**
       * search for sampling_id
       */
      $dsearch = $dsampling;
      $sql = "select sampling_id from dileme.sampling
            where campaign_number = :campaign_number
            and sampling_date = :sampling_date
            and site_id = :site_id
            and project_id = :project_id
            and protocol_id = :protocol_id";
      if (empty($dsearch["depth_id"])) {
        $sql .= " and depth_id is null";
        unset($dsearch["depth_id"]);
        unset ($dsampling["depth_id"]);
      } else {
        $sql .= " and depth_id = :depth_id";
      }
      unset($dsearch["sampling_id"]);
      unset($dsearch["operators"]);
      $res = $this->execute($sql, $dsearch);
      if ($res[0]["sampling_id"] > 0) {
        $dsampling["sampling_id"] = $res[0]["sampling_id"];
      } else {
        $dsampling["sampling_id"] = $this->writeData("dileme", "sampling", $dsampling, "sampling_id");
      }
      /**
       * Search for fishing
       */
      $dfishing = array(
        "fishing_id" => 0,
        "fishing_number" => 1,
        "sampling_id" => $dsampling["sampling_id"],
        "fishing_date" => $sampling["sampling-fishing_date"],
        "engine_position_id" => $sampling["sampling-engine_position_id"],
        "engine_id" => 1,
        "sort_date" => $sampling["sampling-sort_date"]
      );
      $sql = "select fishing_id from dileme.fishing
              where sampling_id = :sampling_id
              and fishing_date = :fishing_date
              and engine_position_id = :engine_position_id";
      $res = $this->execute(
        $sql,
        array(
          "sampling_id" => $dfishing["sampling_id"],
          "fishing_date" => $dfishing["fishing_date"],
          "engine_position_id" => $dfishing["engine_position_id"]
        )
      );
      if ($res[0]["fishing_id"] > 0) {
        $dfishing["fishing_id"] = $res[0]["fishing_id"];
      } else {
        $dfishing["fishing_id"] = $this->writeData("dileme", "fishing", $dfishing, "fishing_id");
      }
      /**
       * Search for sample
       */
      $dsample = array(
        "sample_id" => 0,
        "fishing_id" => $dfishing["fishing_id"],
        "comment" => $sampling["sampling-comment"],
        "uuid" => $sampling["uuid"],
        "shrimp" => $sampling["other_catch-shrimp"],
        "gamare" => $sampling["other_catch-gamare"],
        "mysidaceae" => $sampling["other_catch-mysidaceae"],
        "crab" => $sampling["other_catch-crab"],
        "gelatinous" => $sampling["other_catch-gelatinous"],
        "others" => $sampling["other_catch-others"]
      );
      $sql = "select sample_id from dileme.sample
              where uuid = :uuid";
      $res = $this->execute($sql, array("uuid" => $dsample["uuid"]));
      if ($res[0]["sample_id"] > 0) {
        $dsample["sample_id"] = $res[0]["sample_id"];
      } else {
        $dsample["sample_id"] = $this->writeData("dileme", "sample", $dsample, "sample_id");
      }
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
}
