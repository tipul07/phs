<?php

    if( !@file_exists( 'main.php' ) )
    {
        if( !@is_dir( '_setup' )
         or !@file_exists( '_setup/guide.php' ) )
        {
            echo 'Guide script not found... You will have to manually setup the framework first.';
            exit;
        }

        include( '_setup/guide.php' );

        exit;
    }

    include_once( 'main.php' );

    use \phs\PHS;

    PHS::execute_route();
