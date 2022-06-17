<?php
class OdkException extends Exception
{
}
class Odk
{
  public array $raw;
  public array $param;
  public array $structuredData;
  public array $mainfile = array();
  public array $dataIndex = array();

  function __construct(array $param)
  {
    $this->param = $param;
  }

  function readCsvContent(string $csvfile, array $data)
  {
    $this->raw[$csvfile]["data"] = $data;
    /**
     * Extract the name of the object (tablename probably)
     */
    $name = substr($csvfile, 0, (strlen($this->param["general"]["csvextension"]) + 1) * -1);
    $postiret = strpos($name, "-");
    !$postiret ? $this->raw[$csvfile]["name"] = $name : $this->raw[$csvfile]["name"] = substr($name, $postiret + 1);
    /**
     * Generate the index
     */
    foreach ($data as $line) {
      if (empty($line["PARENT_KEY"])) {
        $this->mainfile[] = $csvfile;
        break;
      }
    }
    foreach ($data as $key => $line) {
      if (!empty($line["PARENT_KEY"])) {
        $this->dataIndex[$line["PARENT_KEY"]][] = array(
          "KEY" => $key,
          "filename" => $csvfile
        );
      }
    }
  }

  function generateStructuredData()
  {
    $this->structuredData = array();
    /**
     * Create the entries for the main tables
     */
    foreach ($this->mainfile as $mainfile) {
      $name = $this->raw[$mainfile]["name"];
      $this->structuredData[$name] = $this->raw[$mainfile]["data"];
      foreach ($this->structuredData[$name] as $key => $v) {
        /**
         * Generate the uuid of the line
         */
        $this->structuredData[$name][$key]["uuid"] = substr($v["KEY"], 5);
        /**
         * Search if exists a child from the index array
         */
        if (count($this->dataIndex[$key]) > 0) {
          $this->structuredData[$name][$key]["CHILDREN"] = $this->getChildren($key);
        }
      }
    }
  }

  function getChildren($key)
  {
    $children = array();
    foreach ($this->dataIndex[$key] as $v) {
      /**
       * Get the content of the child
       */
      $filename = $v["filename"];
      $childKey = $v["KEY"];
      $name = $this->raw[$filename]["name"];
      $dataChild = $this->raw[$filename]["data"][$childKey];
      if (count($this->dataIndex[$childKey]) > 0) {
        $dataChild["CHILDREN"] = $this->getChildren($childKey);
      }
      $children[$name][] = $dataChild;
    }
    return $children;
  }
}
