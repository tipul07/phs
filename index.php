<?php

    include_once( 'main.inc.php' );

    /** @var PHS_Model_Accounts $accounts_obj */
    $accounts_obj = PHS::load_model( 'accounts' );

    $accounts_obj->install_tables();


    //PHS_session::_s( 'bubu', 12 );
    //var_dump( PHS_session::_g() );

