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
