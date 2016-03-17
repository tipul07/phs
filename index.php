<?php

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;

    if( !($accounts_plugin = PHS::load_plugin( 'accounts' )) )
    {
        echo 'accounts inactive';
        exit;
    }


    $hook_args = array();
    $hook_args['template'] = array(
        'file' => 'registration',
        'extra_paths' => array(
            PHS::relative_path( $accounts_plugin->instance_plugin_email_templates_path() ) => PHS::relative_url( $accounts_plugin->instance_plugin_email_templates_www() ),
        ),
    );
    $hook_args['to'] = 'andrei@smart2pay.com';
    $hook_args['to_name'] = 'Andrei Orghici';
    $hook_args['subject'] = 'Account Registration';
    $hook_args['email_vars'] = array(
        'nick' => 'vasile',
        'nick1' => 'vasile1',
        'nick2' => 'vasile2',
        'nick3' => 'vasile3',
        'url' => PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'nick' => 'vasile' ) ),
    );

    var_dump( PHS_Hooks::trigger_email( $hook_args ) );
    var_dump( PHS::st_get_error() );

    PHS::execute_route();
