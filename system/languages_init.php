<?php

if( (!defined( 'PHS_SETUP_FLOW' ) or !constant( 'PHS_SETUP_FLOW' ))
and !defined( 'PHS_VERSION' ) )
    exit;

use \phs\libraries\PHS_Language;

// Define any platform used languages here...
define( 'LANG_EN', 'en' );
define( 'LANG_EN_DIR', PHS_LANGUAGES_DIR.'en/' );
define( 'LANG_EN_WWW', PHS_LANGUAGES_WWW.'en/' );

// We define only English as built-in language
// Languages' definition should be done in a plugin which will add more languages as requested by current build (triggered in languages_start.php)
$languages_arr = array(
    LANG_EN => array(
        'title' => 'English',
        'dir' => LANG_EN_DIR,
        'www' => LANG_EN_WWW,
        'files' => array( LANG_EN_DIR.'en.csv' ),
        'browser_lang' => 'en',
        'browser_charset' => 'utf-8',
        'flag_file' => '',
    ),
);

foreach( $languages_arr as $lang_key => $lang_details )
{
    if( !PHS_Language::define_language( $lang_key, $lang_details ) )
    {
        // Do something if we cannot initialize English language
        PHS_Language::st_throw_error();
    }
}

// We set to be sure current and default language on English
// Default language can be changed when triggering H_LANGUAGE_DEFINITION hook (in a plugin)
// Current language will be read from end-user session and if not found default language will be used
PHS_Language::set_current_language( LANG_EN );
PHS_Language::set_default_language( LANG_EN );
