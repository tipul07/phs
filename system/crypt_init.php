<?php

if( !defined( 'PHS_VERSION' ) )
    exit;

use \phs\PHS_crypt;

if( defined( 'PHS_CRYPT_KEY' ) )
    PHS_crypt::crypting_key( PHS_CRYPT_KEY );

global $PHS_CRYPT_INTERNAL_KEYS_ARR;
global $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;
if( !empty( $PHS_CRYPT_INTERNAL_KEYS_ARR ) and is_array( $PHS_CRYPT_INTERNAL_KEYS_ARR ) )
    PHS_crypt::set_internal_keys( $PHS_CRYPT_INTERNAL_KEYS_ARR );
elseif( !empty( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR ) and is_array( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR ) )
    PHS_crypt::set_internal_keys( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR );
