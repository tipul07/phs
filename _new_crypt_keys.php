<?php

    echo "<pre> = array(\n";
    for( $i = 0; $i < 34; $i++ )
    {
        echo "\t'".md5( rand( 0, PHP_INT_MAX ).microtime().rand( 0, PHP_INT_MAX ) )."',\n";
    }
    echo ");</pre>";
