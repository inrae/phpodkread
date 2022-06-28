<?php
class ImportException extends Exception
{
}

/**
 * Classe de gestion des imports csv
 *
 * @author quinton
 *
 */
class Csv
{
    private $separator = ";";
    private $handle;
    private $header = array();

    /**
     * Init file function
     * Get the first line for header
     *
     * @param string  $filename
     * @param string  $separator
     * @param boolean $utf8_encode
     *
     * @throws OdkException
     */
    function initFile($filename, $separator = ";",  $headerLine = 1)
    {
        switch ($separator) {
            case "tab":
            case "t":
                $separator = "\t";
                break;
            case "comma":
                $separator = ",";
                break;
            case "semicolon":
                $separator = ";";
                break;
        }
        $this->separator = $separator;
        /*
         * File open
         */
        if (!$this->handle = fopen($filename, 'r')) {
            throw new OdkException($filename . " not found or not readable", $filename);
        }
        /**
         * Get the header
         */
        /**
         * Go to the line of the header
         */
        for ($i = 1; $i < $headerLine; $i++) {
            $data = $this->readLine();
        }
        $this->header = $this->readLine();
        /**
         * Get all the data
         */
        $fileContent = $this->getContentAsArray();
        $this->fileClose();
        return $fileContent;
    }

    /**
     * Read a line
     *
     * @return array|NULL
     */
    function readLine()
    {
        if ($this->handle) {
            return fgetcsv($this->handle, 0, $this->separator);
        } else {
            return false;
        }
    }

    /**
     * Read the csv file, and return an associative array
     *
     * @return mixed[][]
     */
    function getContentAsArray()
    {
        $data = array();
        $nb = count($this->header);
        while (($line = $this->readLine()) !== false) {
            $dl = array();
            for ($i = 0; $i < $nb; $i++) {
                $dl[$this->header[$i]] = $line[$i];
            }
            $data[$dl["KEY"]] = $dl;
        }
        return $data;
    }

    /**
     * Close the file
     */
    function fileClose()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }
}
