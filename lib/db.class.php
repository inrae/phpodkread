<?php

/**
 * Generic functions for execute sql requests
 */

class Db
{
  public PDO $connection;
  private string $lastSql = "";
  private PDOStatement $stmt;
  public bool $lastResultExec = false;
  public bool $modeDebug = false;
  public $quote = '"';
  public array $structure = array();

  function writeData(string $schemaName, string $tableName, array $data, string $keyName): ?int
  {
    if (!$data) {
      throw new ODKException("data are empty for $tableName");
    }
    $newKey = null;
    $dataSql = array();
    $comma = "";
    $mode = "insert";
    /**
     * Initialize or verify the structure of the table
     */
    $this->getStructure($schemaName, $tableName);
    if ($data[$keyName] > 0) {
      /**
       * Search if the record exists
       */
      $sql = "select  $this->quote$keyName$this->quote as key "
        . "from  $this->quote$schemaName$this->quote.$this->quote$tableName$this->quote "
        . " where  $this->quote$keyName$this->quote"
        . " = :key";
      $result = $this->execute($sql, array("key" => $data[$keyName]));
      if (!empty($result[0]["key"])) {
        $mode = "update";
      }
    }
    $table = $schemaName . "." . $tableName;
    /**
     * update
     */
    if ($mode == "update") {
      $sql = "update $this->quote$table$this->quote set ";
      foreach ($data as $field => $value) {
        /**
         * Verifiy if the column exists in the table
         */
        if (!empty($this->structure[$schemaName][$tableName][$field])) {
          if ($this->structure[$schemaName][$tableName][$field]["type"] == "boolean" && !$value) {
            $value = "false";
          }
          if ($field != $keyName) {
            $sql .= "$comma$this->quote$field$this->quote = :$field";
            $comma = ", ";
            $dataSql[$field] = $value;
          }
        }
      }
      $sql .= " where $this->quote$keyName$this->quote = :$keyName";
      $dataSql[$keyName] = $data[$keyName];
    } else {
      /**
       * insert
       */
      $mode = "insert";
      $cols = "(";
      $values = "(";
      foreach ($data as $field => $value) {
        if ($field != $keyName && isset($this->structure[$schemaName][$tableName][$field])) {
          if ($this->structure[$schemaName][$tableName][$field]["type"] == "boolean" && !$value) {
            $value = "false";
          }
          $cols .= $comma . $this->quote . $field . $this->quote;
          $values .= $comma . ":$field";
          $dataSql[$field] = $value;
          $comma = ", ";
        }
      }
      $cols .= ")";
      $values .= ")";
      $sql = "insert into $this->quote$schemaName$this->quote.$this->quote$tableName$this->quote $cols values $values"
        . " returning $keyName";
    }
    $result = $this->execute($sql, $dataSql);
    if ($mode == "insert") {
      $newKey = $result[0][$keyName];
    } else {
      $newKey = $data[$keyName];
    }
    if ($this->modeDebug) {
      printr("newkey: " . $newKey);
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
    $result = array();
    try {
      $this->prepare($sql);
      $this->lastResultExec = $this->stmt->execute($data);
      if ($this->lastResultExec) {
        $result = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
      } else {
        if ($this->modeDebug) {
        printr($this->stmt->errorInfo());
        }
        throw new ODKException($this->stmt->errorInfo()[0]);
      }
    } catch (PDOException $e) {
      $this->lastResultExec = false;
      $sdata = "";
        foreach ($data as $key => $value) {
          $sdata .= "$key:$value" . phpeol();
        }
      throw new ODKException("Error when execute the request" . phpeol()
          . $sql . phpeol()
          . $sdata
          . $this->stmt->errorInfo()[2].phpeol()
          . $e->getMessage());
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
        throw new ODKException("This request can't be prepared:" . phpeol() . $sql);
      }
      $this->lastSql = $sql;
    }
  }

  /**
   * Prepare the list of columns of a table, with column types and keys, comments, etc.
   *
   * @param string $schemaName
   * @param string $tableName
   */
  function getStructure(string $schemaName, string $tableName)
  {
    if (count($this->structure[$schemaName][$tableName]) == 0) {
      $sql = 'SELECT pg_attribute.attname AS field,
            pg_catalog.format_type(pg_attribute.atttypid,pg_attribute.atttypmod) AS "type",
          (SELECT col_description(pg_attribute.attrelid,pg_attribute.attnum)) AS comment,
          CASE pg_attribute.attnotnull
            WHEN FALSE THEN 0
            ELSE 1
          END AS "notnull",
          pg_constraint.conname AS "key",
          pc2.conname AS ckey
        FROM pg_tables
        JOIN pg_namespace ON (pg_namespace.nspname = pg_tables.schemaname)
        JOIN pg_class
          ON (pg_class.relname = pg_tables.tablename
          AND pg_class.relnamespace = pg_namespace.oid)
        JOIN pg_attribute
        ON pg_class.oid = pg_attribute.attrelid
        AND pg_attribute.attnum > 0
        LEFT JOIN pg_constraint
            ON pg_constraint.contype = \'p\'::"char"
            AND pg_constraint.conrelid = pg_class.oid
            AND (pg_attribute.attnum = ANY (pg_constraint.conkey))
        LEFT JOIN pg_constraint AS pc2
            ON pc2.contype = \'f\'::"char"
            AND pc2.conrelid = pg_class.oid
            AND (pg_attribute.attnum = ANY (pc2.conkey))
        WHERE pg_attribute.atttypid <> 0::OID
        and schemaname = :schemaname
        and tablename = :tablename
        ORDER BY schemaname, tablename, attnum ASC';
      $result = $this->execute(
        $sql,
        array("schemaname" => $schemaName, "tablename" => $tableName)
      );
      foreach ($result as $row) {
        $this->structure[$schemaName][$tableName][$row["field"]] = $row;
      }
    }
  }
}
