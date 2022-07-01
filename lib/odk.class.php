<?php
class OdkException extends Exception
{
}

interface Database
{
  function setConnection(PDO $connection);
  function setData(array $data);
  function getTreatedNumber(): int;
  function setDebug(bool $modeDebug = false);
}

class Odk
{
  public array $raw;
  public array $param;
  public array $structuredData;
  public array $mainfile = array();
  public array $dataIndex = array();
  private $tempPath, $source, $dest;
  private $dc; # Database class

  /**
   * Constructor
   *
   * @param array $param: content of param.ini
   */
  function __construct(array $param)
  {
    $this->param = $param;
    $this->param["basedir"] = str_replace("..", "", $this->param["basedir"]);
    $this->tempPath = $this->sanitizePath($this->param["temp"]);
    if (!$this->verifyFolder($this->tempPath, true)) {
      throw new OdkException("Unable to create the temp folder");
    }
  }
  /**
   * Verify if the folder exists, and create it if necessary
   *
   * @param string $path
   * @param boolean $withCreation
   * @return boolean
   */
  function verifyFolder(string $path, bool $withCreation = false):bool
  {
    $ok = true;
    if (!file_exists($path)) {
      if ($withCreation) {
        if (!mkdir($this->tempPath, 0777, true)) {
          $ok = false;
        }
      } else {
        $ok = false;
      }
    }
    return $ok;
  }

  function sanitizePath($p)
  {
    return ($this->param["basedir"]. "/". str_replace("../", "", $p));
  }

  /**
   * Get the list of files in an folder corresponding to an extension
   *
   * @param string $foldername
   * @param string $extension
   * @return array
   */
  function getListFromFolder(string $foldername, string $extension): array
  {
    $folder = opendir($foldername);
    if (!$folder) {
      throw new OdkException("The folder $foldername don't exists");
    }
    $files = array();
    while (false !== ($filename = readdir($folder))) {
      /**
       * Extract the extension
       */

      $fileext = (false === $pos = strrpos($filename, '.')) ? '' : strtolower(substr($filename, $pos + 1));
      if ($extension == $fileext) {
        $files[] = $filename;
      }
    }
    closedir($folder);
    return $files;
  }

  /**
   * Purge a folder of all contents files
   *
   * @param string $foldername
   * @param string $extension
   * @return void
   */
  function tempPurge()
  {
    $extension = $this->param["csvextension"];
    empty($extension) ? $withExtension = false : $withExtension = true;
    $folder = opendir($this->tempPath);
    if ($folder) {
      while (false !== ($filename = readdir($folder))) {
        if (is_file($this->tempPath . "/" . $filename)) {
          if ($withExtension) {
            $fileext = (false === $pos = strrpos($filename, '.')) ? '' : strtolower(substr($filename, $pos + 1));
          }
          if (!$withExtension || $fileext == $extension) {
            unlink($this->tempPath . "/" . $filename);
          }
        }
      }
      closedir($folder);
    }
  }

  function moveFile($filename)
  {
    $from = $this->sanitizePath($this->param["source"]);
    $to = $this->sanitizePath($this->param["dest"]);
    rename($from . "/" . $filename, $to . "/" . $filename);
  }

  function readCsvContent(string $csvfile, array $data)
  {
    $this->raw[$csvfile]["data"] = $data;
    /**
     * Extract the name of the object (tablename probably)
     */
    $name = substr($csvfile, 0, (strlen($this->param["csvextension"]) + 1) * -1);
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

  function writeDataDB(string $classpath, string $className, PDO $connection): int
  {
    $path = $this->sanitizePath($classpath);
    if (!file_exists($path)) {
      throw new OdkException("The file $classpath not exists or is not readable");
    }
    include_once $path;
    $this->dc = new $className();
    /**
     * Verify the implements
     */
    $ok = false;
    foreach (class_implements($this->dc) as $implementName) {
      if ($implementName == "Database") {
        $ok = true;
        break;
      }
    }
    if (!$ok) {
      throw new OdkException("The class $className not implements Database");
    }
    $this->dc->setDebug($this->param["debug"]);
    $this->dc->setConnection($connection);
    $this->dc->setData($this->structuredData);
    return $this->dc->getTreatedNumber();
  }
}
