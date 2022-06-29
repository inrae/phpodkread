<?php

/**
 * Generic functions for execute sql requests
 */

class Db
{
  public PDO $connection;
  private string $lastSql;
  private $stmt;
  private $lastResultExec;
  public $modeDebug = false;

  function writeData(string $tableAlias, $data): ?int
  {
    if (!$data) {
      throw new ExportException("data are empty for $tableAlias");
    }
    $model = $this->model[$tableAlias];
    $tableName = $model["tableName"];
    $structure = $this->structure[$tableName];
    if (!is_array($structure) || count($structure) == 0) {
      throw new ExportException("The structure of the table $tableName is unknown");
    }
    $tkeyName = $model["technicalKey"];
    $pkeyName = $model["parentKey"];
    $bkeyName = $model["businessKey"];
    $skeyName = $model["tablenn"]["secondaryParentKey"];
    $newKey = null;
    $dataSql = array();
    $comma = "";
    $mode = "insert";
    if ($data[$tkeyName] > 0) {
      /**
       * Search if the record exists
       */
      $sql = "select " . $this->quote . $tkeyName . $this->quote
        . " as key from " . $this->quote . $tableName . $this->quote
        . " where " . $this->quote . $tkeyName . $this->quote
        . " = :key";
      $result = $this->execute($sql, array("key" => $data[$tkeyName]));
      if (!empty($result[0]["key"])) {
        $mode = "update";
      }
    }
    $model["istablenn"] == 1 ? $returning = "" : $returning = " RETURNING $tkeyName";
    /**
     * update
     */
    if ($mode == "update") {
      $sql = "update $this->quote$tableName$this->quote set ";
      foreach ($data as $field => $value) {
        if (
          is_array($structure["booleanFields"])
          && in_array($field, $structure["booleanFields"]) && !$value
        ) {
          $value = "false";
        }
        if ($field != $tkeyName) {
          $sql .= "$comma$this->quote$field$this->quote = :$field";
          $comma = ", ";
          $dataSql[$field] = $value;
        }
      }
      if (!empty($pkeyName) && !empty($skeyName)) {
        $where = " where $this->quote$pkeyName$this->quote = :$pkeyName and $this->quote$skeyName$this->quote = :$skeyName";
      } else {
        $where = " where $this->quote$tkeyName$this->quote = :$tkeyName";
        $dataSql[$tkeyName] = $data[$tkeyName];
      }
      if (!isset($where)) {
        throw new ExportException(
          "The where clause can't be construct for the table $tableName"
        );
      }
      $sql .= $where;
    } else {
      /**
       * insert
       */
      $mode = "insert";
      $cols = "(";
      $values = "(";
      foreach ($data as $field => $value) {
        if (!($field == $tkeyName && $bkeyName != $tkeyName)) {
          if (
            is_array($structure["booleanFields"])
            && in_array($field, $structure["booleanFields"]) && !$value
          ) {
            $value = "false";
          }
          if (!($model["istablenn"] == 1 && $field == $model["tablenn"]["tableAlias"])) {
            $cols .= $comma . $this->quote . $field . $this->quote;
            $values .= $comma . ":$field";
            $dataSql[$field] = $value;
            $comma = ", ";
          }
        }
      }
      $cols .= ")";
      $values .= ")";
      $sql = "insert into $this->quote$tableName$this->quote $cols values $values $returning";
    }
    $result = $this->execute($sql, $dataSql);
    if ($model["istablenn"] == 1) {
      $newKey = null;
    } else if ($mode == "insert") {
      $newKey = $result[0][$tkeyName];
    } else {
      $newKey = $data[$tkeyName];
    }
    if ($this->modeDebug) {
      printr("newkey: " . $newKey);
    }
    /**
     * Get the binary data
     */
    if (
      strlen($newKey) > 0
      && is_array($structure["binaryFields"])
      && count($structure["binaryFields"]) > 0
    ) {
      if (empty($data[$bkeyName])) {
        throw new ExportException(
          "The businessKey is empty for the table $tableName and the binary data can't be imported"
        );
      }
      if (!is_dir($this->binaryFolder)) {
        throw new ExportException(
          "The folder that contains binary files don't exists (" . $this->binaryFolder . ")"
        );
      }
      foreach ($structure["binaryFields"] as $binaryField) {
        $filename = $this->binaryFolder . "/" . $tableName . "-" . $binaryField . "-" . $data[$bkeyName] . ".bin";
        if (file_exists($filename)) {
          $fp = fopen($filename, 'rb');
          if (!$fp) {
            throw new ExportException("The file $filename can't be opened");
          }
          $sql = "update  $this->quote$tableName$this->quote set ";
          $sql .= "$this->quote$binaryField$this->quote = :binaryFile";
          $sql .= " where $this->quote$tkeyName$this->quote = :key";
          $this->prepare($sql);
          $this->stmt->bindParam(":binaryFile", $fp, PDO::PARAM_LOB);
          $this->stmt->bindParam(":key", $newKey);
          if (!$this->stmt->execute()) {
            throw new ExportException("Error when execute the request" . phpeol()
              . $sql . phpeol()
              . $this->stmt->errorInfo()[2]);
          };
        }
      }
    }
    return $newKey;
  }

  /**
   * Execute a SQL command
   *
   * @param string $sql: request to execute
   * @param array $data: data associated with the request
   * @return array|null
   */
  function execute(string $sql, array $data = array()): ?array
  {
    if ($this->modeDebug) {
      printr($sql);
      printr($data);
    }
    $result = null;
    try {
      $this->prepare($sql);
      $this->lastResultExec = $this->stmt->execute($data);
      if ($this->lastResultExec) {
        $result = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
      } else {
        $sdata = "";
        foreach ($data as $key => $value) {
          $sdata .= "$key:$value" . phpeol();
        }
        throw new ExportException("Error when execute the request" . phpeol()
          . $sql . phpeol()
          . $sdata
          . $this->stmt->errorInfo()[2]);
      }
    } catch (PDOException $e) {
      $this->lastResultExec = false;
      throw new OdkException($e->getMessage());
    }
    return $result;
  }

  /**
   * Prepare the statement of PDO connection
   * only if the sql value change
   *
   * @param string $sql
   * @return void
   */
  private function prepare(string $sql)
  {
    if ($this->lastSql != $sql) {
      $this->stmt = $this->connection->prepare($sql);
      if (!$this->stmt) {
        throw new ExportException("This request can't be prepared:" . phpeol() . $sql);
      }
      $this->lastSql = $sql;
    }
  }
}
