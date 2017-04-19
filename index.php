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
    use \phs\libraries\PHS_Hooks;

    // if( !($accounts_plugin = PHS::load_plugin( 'accounts' )) )
    // {
    //    echo 'accounts inactive';
    //    exit;
    // }
    //
    // $hook_args = array();
    // $hook_args['template'] = $accounts_plugin->email_template_resource_from_file( 'registration' );
    // $hook_args['to'] = 'andrei@smart2pay.com';
    // $hook_args['to_name'] = 'Andrei Orghici';
    // $hook_args['subject'] = 'Account Registration';
    // $hook_args['email_vars'] = array(
    //    'nick' => 'vasile',
    //    'contact_us_link' => PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'nick' => 'vasile' ) ),
    //    'obfuscated_pass' => 'vasile3',
    //    'activation_link' => PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'nick' => 'vasile' ) ),
    // );
    //
    // var_dump( PHS_Hooks::trigger_email( $hook_args ) );
    // var_dump( PHS::st_get_error() );
    // exit;

    PHS::execute_route();
