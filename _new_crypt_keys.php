<?php

    define( 'PHS_SETUP_FLOW', true );
    // Just define a value here, so we pass the checks...
    define( 'PHS_VERSION', '1.0.0.0' );

    if( !@file_exists( 'system/core/phs_crypt.php' ) )
    {
        echo 'Cannot generate internal keys...';
        // Signal error to output...
        exit( 254 );
    }

    // We don't do a bootstrap, as this might be called before we have a working framework...
    require_once( 'system/libraries/phs_error.php' );
    require_once( 'system/libraries/phs_language.php' );
    require_once( 'system/libraries/phs_language_container.php' );
    require_once( 'system/core/phs_crypt.php' );

    use \phs\PHS_Crypt;

    if( !($keys_arr = PHS_Crypt::generate_crypt_internal_keys())
     || !is_array( $keys_arr ) )
    {
        echo 'Error generating internal keys...';
        // Signal error to output...
        exit( 254 );
    }

    echo "[ ";
    foreach( $keys_arr as $key_str )
    {
        echo "'".$key_str."', ";
    }
    echo "];";
