<?php

if( !defined( 'PHS_VERSION' ) )
    exit;

// Define any platform used languages here...
define( 'LANG_EN', 'en' );
define( 'LANG_EN_DIR', PHS_LANGUAGES_DIR.'en/' );
define( 'LANG_EN_WWW', PHS_LANGUAGES_WWW.'en/' );

if(
    !\phs\libraries\PHS_Language::define_language( LANG_EN, array(
        'title' => 'English',
        'files' => array( LANG_EN_DIR.'en.csv' ),
    ) )
)
{
    // Do something if we cannot initialize English language
    \phs\libraries\PHS_Language::st_throw_error();
} else
{
    \phs\libraries\PHS_Language::set_current_language( LANG_EN );
}

