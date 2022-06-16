<?php

/**
 * Fonction permettant de reorganiser les donnees des fichiers telecharges,
 * pour une utilisation directe en tableau
 * @return multitype:multitype:NULL  unknown
 */
function formatFiles($attributName = "documentName")
{
    global $_FILES;
    $files = array();
    $fdata = $_FILES[$attributName];
    if (is_array($fdata['name'])) {
        for ($i = 0; $i < count($fdata['name']); ++$i) {
            $files[] = array(
                'name'    => $fdata['name'][$i],
                'type'  => $fdata['type'][$i],
                'tmp_name' => $fdata['tmp_name'][$i],
                'error' => $fdata['error'][$i],
                'size'  => $fdata['size'][$i]
            );
        }
    } else $files[] = $fdata;
    return $files;
}
/**
 * Affiche le nom et le contenu d'une variable
 * @param array $tableau
 */
function printr($tableau, $mode_dump = 0)
{
    if ($mode_dump == 1) {
        var_dump($tableau);
    } else {
        if (is_array($tableau)) {
            print_r($tableau);
        } else {
            echo $tableau;
        }
    }
    echo PHP_EOL;
}

function test($content = "")
{
    global $testOccurrence;
    echo "test $testOccurrence : $content" . PHP_EOL;
    $testOccurrence++;
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
function folderPurge(string $foldername, string $extension = "")
{
    empty($extension) ? $withExtension = false : $withExtension = true;
    while (false !== ($filename = readdir($foldername))) {
        if (is_file($filename)) {
            if ($withExtension) {
                $fileext = (false === $pos = strrpos($filename, '.')) ? '' : strtolower(substr($filename, $pos + 1));
            }
            if (!$withExtension || $fileext == $extension) {
                unlink($foldername . "/" . $filename);
            }
        }
    }
}
