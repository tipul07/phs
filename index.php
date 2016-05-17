<?php

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;
    use \phs\PHS_crypt;
    use \phs\libraries\PHS_Model;

    // /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_details $accounts_model */
    // if( !($accounts_model = PHS::load_model( 'accounts_details', 'accounts' )) )
    // {
    //     echo 'Error instantiating accounts model.';
    //     exit;
    // }
    //
    // if( !($result = $accounts_model->check_field_exists( 'id', array( 'table_name' => 'users_details' ) )) )
    // {
    //     var_dump( $accounts_model->get_error() );
    //
    //     echo 'Users table structure is unknown. Id field not found.';
    //     exit;
    // }
    //
    // var_dump( $result );
    //
    // exit;
    //
    // // // ALTER TABLE Employees CHANGE COLUMN empName empName VARCHAR(50) AFTER department;
    // if( ($qid = db_query( 'SHOW FULL COLUMNS FROM `users`' )) )
    // {
    //     while( ($row = mysqli_fetch_assoc( $qid )) )
    //         var_dump( $row );
    // }
    //
    // exit;

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
