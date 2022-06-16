<?php
class OdkException extends Exception
{
}
class Odk
{
  private array $raw;
  public array $param;
  private array $mainfile = array();
  private array $dataIndex = array();

  function __construct(array $param)
  {
    $this->param = $param;
  }

  function setCsvContent(string $csvfile, array $data)
  {
    $this->raw[$csvfile]["data"] = $data;
    /**
     * Extract the name of the object (tablename probably)
     */
    $name = substr($csvfile, (strlen($this->param["general"]["csvextension"] + 1) * -1));
    $postiret = strpos($name, "-");
    !$postiret ? $this->raw[$csvfile]["name"] = $name : $this->raw[$csvfile]["name"] = substr($name, $postiret + 1);
    /**
     * Generate the index
     */
    if (empty($this->raw[$csvfile]["data"][0]["PARENT_KEY"])) {
      $this->mainfile[] = $csvfile;
    }
    foreach ($this->raw[$csvfile]["data"] as $key => $line) {
      if (!empty($line["PARENT_KEY"])) {
        $this->dataIndex[$line["PARENT_KEY"]][] = array(
          "KEY" => $key,
          "filename" => $csvfile
        );
      }
    }
  }

  function generateJson(): string
  {
    /**
     * Create the entries for the main tables
     */
    $datajs = array();
    foreach ($this->mainfile as $mainfile) {
      if (empty($mainfile)) {
        throw new OdkException("The main file could not be defined");
      }
      $datajs = array($this->raw[$mainfile]["name"] => $this->raw[$mainfile["data"]]);
      foreach ($datajs[$this->raw[$mainfile]["name"]] as $key => $v) {
        /**
         * Generate the uuid of the line
         */
        $datajs[$this->raw[$mainfile]["name"]][$key]["uuid"] = substr($v["KEY"], 5);
        /**
         * Search if exists a child from the index array
         */
        if (count($this->dataIndex[$key]) > 0) {
          $datajs[$this->raw[$mainfile]["name"]][$key]["CHILDREN"] = $this->getChildren($key);
        }
      }
    }
    return json_encode($datajs);
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
      $dataChild = $this->raw["filename"][$childKey];
      if (count($this->dataIndex[$childKey]) > 0) {
        $dataChild["CHILDREN"][] = $this->getChildren($childKey);
      }
      $children[$name][] = $dataChild;
    }
    return $children;
  }
}
