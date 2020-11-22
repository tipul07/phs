<?php

// Once everything is configured, ask any plugin to define new languages
// and check user session for selected language
if( !defined( 'PHS_VERSION' ) )
    exit;

use \phs\PHS;
use \phs\PHS_Session;
use \phs\libraries\PHS_Language;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Params;

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

$request_lang_set = false;
if( ($session_lang = PHS_Session::_g( PHS_Language::LANG_SESSION_KEY ))
and ($session_lang = PHS_Language::valid_language( $session_lang )) )
{
    $request_lang_set = $session_lang;
    PHS_Language::set_current_language( $session_lang );
}

if( ($url_lang = PHS_Params::_gp( PHS_Language::LANG_URL_PARAMETER ))
and ($url_lang = PHS_Language::valid_language( $url_lang )) )
{
    $request_lang_set = $url_lang;
    PHS_Language::set_current_language( $url_lang );

    if( empty( $session_lang )
     or $session_lang !== $url_lang )
        PHS_Session::_s( PHS_Language::LANG_SESSION_KEY, $url_lang );
}

// Checking current user's selected language only for session scopes
/** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
if( !PHS::prevent_session()
and ($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
and ($current_user = PHS::user_logged_in()) )
{
    if( !($account_language = $accounts_model->get_account_language( $current_user )) )
        $account_language = false;

    // Update account language if we have another language set in session or in request
    if( empty( $request_lang_set ) )
    {
        if( !empty( $account_language ) )
        {
            if( empty( $session_lang )
             or $session_lang !== $account_language )
                PHS_Session::_s( PHS_Language::LANG_SESSION_KEY, $account_language );

            $request_lang_set = $account_language;
            PHS_Language::set_current_language( $account_language );
        }
    } elseif( empty( $account_language ) or $account_language !== $request_lang_set )
    {
        // If we have an error when saving language in profile, don't throw an error as we have a cookie set with the language
        $accounts_model->set_account_language( $current_user, $request_lang_set );
    }
}

// Check if we should change default language as requested by plugins in hook call
if( !empty( $hook_args['default_language'] )
and PHS_Language::valid_language( $hook_args['default_language'] ) )
{
    PHS_Language::set_default_language( $hook_args['default_language'] );

    if( empty( $request_lang_set ) )
        PHS_Language::set_current_language( $hook_args['default_language'] );
}
