<?php
    include_once( 'main.inc.php' );

    /** @var PHS_Model_Accounts $accounts_obj */
    $accounts_obj = PHS::load_model( 'accounts', 'accounts' );
    //$plugins_model = PHS::load_model( 'plugins' );

    var_dump( $accounts_obj->install() );
    var_dump( $accounts_obj->instance_details() );

    //$fields_arr = array();
    //$fields_arr['email'] = 'gica@email.com';
    //
    //$insert_arr = array();
    //$insert_arr['fields'] = $fields_arr;
    //
    //var_dump( $accounts_obj->insert( $insert_arr ) );

    //var_dump( $accounts_obj );

    //PHS_session::_s( 'bubu', 12 );
    //var_dump( PHS_session::_g() );

