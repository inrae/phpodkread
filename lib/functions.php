<?php

/**
 * Function to reorganize data from downloaded files,
 * for direct use in array
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
 * Generate a line return with <br> or PHP_EOL
 *
 * @return void
 */
function phpeol()
{
    if (PHP_SAPI == "cli") {
        return PHP_EOL;
    } else {
        return "<br>";
    }
}

/**
 * display the term "test" with a number and a content, if required
 * Debug function
 *
 * @param string $content
 * @return void
 */
function test($content = "")
{
    global $testOccurrence;
    echo "test $testOccurrence : $content" . PHP_EOL;
    $testOccurrence++;
}
/**
 * Display the content of a variable
 * Debug function
 *
 * @param any $tableau
 * @param integer $mode_dump
 * @param bool $force
 * @return void
 */
function printr($tableau, $mode_dump = false)
{

    if ($mode_dump) {
        var_dump($tableau);
    } else {
        if (is_array($tableau)) {
            print_r($tableau);
        } else {
            echo $tableau;
        }
    }
    echo phpeol();
}
