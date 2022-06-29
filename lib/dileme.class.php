<?php
include_once "lib/db.class.php";
class Dileme extends Db implements Database
{

  private int $treatedNb = 0;

  function setConnection(PDO $connection)
  {
    $this->connection = $connection;
  }
  function setData(array $data)
  {
    foreach ($data as $sampling) {
      $dsampling = array(
        "sampling_id" => 0,
        "campaign_number" => $sampling["sampling-campaign_number"],
        "sampling_date" => $sampling["sampling-fishing_date"],
        "site_id" => $sampling["sampling-site_id"],
        "project_id" => 1,
        "operators" => $sampling["sampling-operators"],
        "depth_id" => $sampling["sampling-depth_id"]
      );
      /**
       * search for sampling_id
       */
      $sql = "select sampling_id from dileme.sampling
            where campaign_number = :campaign_number
            and sampling_date = :sampling_date
            and site_id = :site_id
            and project_id = :project_id
            and depth_id = :depth_id";
      $res = $this->execute($sql, $dsampling);
      if ($res[0]["sampling_id"] > 0) {
        $dsampling["sampling_id"] = $res[0]["sampling_id"];
      }
    }
  }
  function getTreatedNumber(): int
  {
    return $this->treatedNb;
  }
  /**
   * Generic functions
   */
}
