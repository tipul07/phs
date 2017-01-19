<?php

// Once everything is configured, ask any plugin to define new languages
// and check user session for selected language
if( !defined( 'PHS_VERSION' ) )
    exit;

use \phs\PHS;
use \phs\PHS_session;
use \phs\libraries\PHS_Language;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_params;

$hook_args = PHS_Hooks::default_language_definition_hook_args();
$hook_args['languages_arr'] = PHS_Language::get_defined_languages();

if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_LANGUAGE_DEFINITION, $hook_args ))
and !empty( $hook_args['languages_arr'] ) and is_array( $hook_args['languages_arr'] ) )
{
    foreach( $hook_args['languages_arr'] as $lang_key => $lang_details )
    {
        if( !PHS_Language::define_language( $lang_key, $lang_details ) )
        {
            // Do something if we cannot initialize English language
            PHS_Language::st_throw_error();
        }
    }
}

if( ($session_lang = PHS_session::_g( PHS_Language::LANG_SESSION_KEY ))
and PHS_Language::valid_language( $session_lang ) )
    PHS_Language::set_current_language( $session_lang );

if( ($url_lang = PHS_params::_gp( PHS_Language::LANG_URL_PARAMETER ))
and PHS_Language::valid_language( $url_lang ) )
{
    PHS_Language::set_current_language( $url_lang );

    if( empty( $session_lang )
     or $session_lang != $url_lang )
        PHS_session::_s( PHS_Language::LANG_SESSION_KEY, $url_lang );
}

// Check if we should change default language as requested by plugins in hook call
if( !empty( $hook_args['default_language'] )
and PHS_Language::valid_language( $hook_args['default_language'] ) )
    PHS_Language::set_default_language( $hook_args['default_language'] );
