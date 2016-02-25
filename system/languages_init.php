<?php

if( !defined( 'PHS_VERSION' ) )
    exit;

use \phs\libraries\PHS_Language;

// Define any platform used languages here...
define( 'LANG_EN', 'en' );
define( 'LANG_EN_DIR', PHS_LANGUAGES_DIR.'en/' );
define( 'LANG_EN_WWW', PHS_LANGUAGES_WWW.'en/' );

if(
    !PHS_Language::define_language( LANG_EN, array(
        'title' => 'English',
        'dir' => LANG_EN_DIR,
        'www' => LANG_EN_WWW,
        'files' => array( LANG_EN_DIR.'en.csv' ),
        'browser_lang' => 'en',
        'browser_charset' => 'utf-8',
    ) )
)
{
    // Do something if we cannot initialize English language
    PHS_Language::st_throw_error();
} else
{
    PHS_Language::set_current_language( LANG_EN );
    PHS_Language::set_default_language( LANG_EN );
}

