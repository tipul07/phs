<?php

    echo "<pre> = array(\n";
    for( $i = 0; $i < 34; $i++ )
    {
        echo "\t'".md5( random_int( 0, PHP_INT_MAX ).microtime().random_int( 0, PHP_INT_MAX ) )."',\n";
    }
    echo ");</pre>";
