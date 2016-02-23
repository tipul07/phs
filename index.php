<?php

    include_once( 'main.php' );

    //include_once( 'plugins/accounts/controllers/phs_index.php' );
    //include_once( 'system/core/controllers/phs_index.php' );
    //
    var_dump( phs\PHS::execute_route() );
    //var_dump( phs\plugins\accounts\controllers\PHS_Controller_Index::get_instance() );
    //var_dump( phs\system\core\controllers\PHS_Controller_Index::get_instance() );
    //var_dump( phs\PHS::load_controller( 'index', 'accounts' ) );
    //var_dump( phs\PHS::load_controller( 'index' ) );
    //var_dump( phs\PHS::st_get_error() );

    exit;

    /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
    if( !($plugins_model = phs\PHS::load_model( 'plugins' )) )
    {
        var_dump( phs\PHS::st_get_error() );
        exit;
    }

    $plugins_model->add_connection( 'PHS_Model_Accounts', 'accounts', $plugins_model::INSTANCE_TYPE_MODEL );

    var_dump( $plugins_model->force_install() );
    var_dump( $plugins_model->get_error() );

    /** @var PHS_Model_Accounts $accounts_obj */
    //if( !($accounts_obj = PHS::load_model( 'accounts', 'accounts' ))
    // or !($plugins_model = PHS::load_model( 'plugins' )) )
    //{
    //    var_dump( PHS::st_get_error() );
    //    exit;
    //}
    //
    //$plugins_model->install();
    //
    //var_dump( $accounts_obj->install() );
    //var_dump( $accounts_obj->instance_details() );

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

