<?php

    echo "<pre> = array(\n";
    for( $i = 0; $i < 34; $i++ )
    {
        echo "\t'".md5( rand( 0, 100000000 ).time().rand( 0, 100000000 ) )."',\n";
    }
    echo ");</pre>";
