<?php

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;
    use \phs\PHS_crypt;
    use \phs\libraries\PHS_Model;

    // /** @var \phs\plugins\s2p_documents\PHS_Plugin_S2p_documents $s2p_documents_plugin */
    // if( !($s2p_documents_plugin = PHS::load_plugin( 's2p_documents' ))
    //  or !($ldap_obj = $s2p_documents_plugin->get_ldap_instance()) )
    // {
    //     echo 'No plugin or instance';
    //     exit;
    // }
    //
    // // var_dump( $ldap_obj->server_settings() );
    // var_dump( $ldap_obj->identifier_details( 'companies/2/passport_copy/11' ) );
    // exit;

    // $ldap_add_arr = array();
    // $ldap_add_arr['ldap_from'] = 'companies/2/company_pdf';
    // $ldap_add_arr['ldap_to'] = 'companies/3/company_pdf';
    //
    // var_dump( $ldap_obj->rename( $ldap_add_arr ) );

    // $ldap_add_arr = array();
    // $ldap_add_arr['ldap_data'] = 'companies/2/company_pdf';
    // $ldap_add_arr['file'] = '/home/andy/Downloads/smart2pay_nda.pdf';
    //
    // var_dump( $ldap_obj->add( $ldap_add_arr ) );

    // $ldap_add_arr = array();
    // $ldap_add_arr['ldap_data'] = 'companies/1/company_logo';
    // $ldap_add_arr['file'] = '/home/andy/Downloads/2015-04-22 - square logo small 400x172.png';
    //
    // var_dump( $ldap_obj->add( $ldap_add_arr ) );

    // var_dump( $ldap_obj->get_error() );
    // exit;


    // $file_ext = '';
    // if( ($file_dots_arr = explode( '.', $file_name ))
    //     and is_array( $file_dots_arr )
    //         and count( $file_dots_arr ) > 1 )
    //     $file_ext = strtolower( array_pop( $file_dots_arr ) );


    //var_dump( \phs\PHS_bg_jobs::run( array( 'plugin' => 'accounts', 'action' => 'registration_email_bg' ), array( 'uid' => 1 ) ) );
    //var_dump( PHS::st_get_error() );
    //
    //exit;
    //

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
