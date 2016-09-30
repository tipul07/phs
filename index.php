<?php

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\PHS_crypt;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Model;
    use \phs\libraries\PHS_Logger;

    // /** @var \phs\plugins\s2p_companies\models\PHS_Model_Companies $companies_model */
    // $companies_model = PHS::load_model( 'companies', 's2p_companies' );
    //
    //
    // var_dump( $companies_model->request_activation( 1, 1 ) );
    // var_dump( $companies_model->seconds_till_activation_request( 1 ) );
    // var_dump( $companies_model->get_error() );
    // exit;

    //if( !($accounts_plugin = PHS::load_plugin( 'accounts' )) )
    //{
    //    echo 'accounts inactive';
    //    exit;
    //}
    //
    //$hook_args = array();
    //$hook_args['template'] = $accounts_plugin->email_template_resource_from_file( 'registration' );
    //$hook_args['to'] = 'andrei@smart2pay.com';
    //$hook_args['to_name'] = 'Andrei Orghici';
    //$hook_args['subject'] = 'Account Registration';
    //$hook_args['email_vars'] = array(
    //    'nick' => 'vasile',
    //    'nick1' => 'vasile1',
    //    'nick2' => 'vasile2',
    //    'nick3' => 'vasile3',
    //    'url' => PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'nick' => 'vasile' ) ),
    //);
    //
    //var_dump( PHS_Hooks::trigger_email( $hook_args ) );
    //var_dump( PHS::st_get_error() );
    //exit;

    PHS::execute_route();
