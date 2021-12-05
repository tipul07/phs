<?php

    echo "<pre> = [\n";
    for( $i = 0; $i < 34; $i++ )
    {
        echo "'".md5( rand( 0, PHP_INT_MAX ).microtime().rand( 0, PHP_INT_MAX ) )."', ";
    }
    echo "];</pre>";
