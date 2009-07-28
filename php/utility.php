<?php

require_once dirname(__FILE__) . '/AccountManager.php';

/**
 * Print debug information into a file (.debug) into data folder.
 *
 * @param $mess The debug message.
 */
function debug($mess)
{
    $mess = '['.date('d/m/Y H:i:s').'] by '
            .AccountManager::getInstance()->cvsLogin.' : '.$mess."\n";

    $fp = fopen(DOC_EDITOR_CVS_PATH.'../.debug', 'a+');
    fwrite($fp, $mess);
    fclose($fp);
}

?>