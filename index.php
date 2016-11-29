<?php

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\PHS_crypt;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Model;
    use \phs\libraries\PHS_Logger;

    // /** @var \phs\plugins\s2p_companies\models\PHS_Model_Companies $model_obj */
    // $model_obj = PHS::load_model( 'companies', 's2p_companies' );
    //
    // var_dump( $model_obj->new_company_setup_bg( 7, array( 'force' => true ) ) );
    // var_dump( $model_obj->get_error() );
    // exit;

    // /** @var \phs\plugins\s2p_companies\models\PHS_Model_Flags $flags_model */
    // /** @var \phs\plugins\s2p_mirakl\PHS_Plugin_S2p_mirakl $mirakl_plugin */
    //
    // $flags_model = PHS::load_model( 'flags', 's2p_companies' );
    // $mirakl_plugin = PHS::load_plugin( 's2p_mirakl' );
    //
    // $company_flags = array();
    // $company_flags[$mirakl_plugin::F_MIRAKL_SHOP_ID] = 2007;
    // $company_flags[$flags_model::F_CONTRACT_SIGNED] = 1;
    // $company_flags[$flags_model::F_COMPANY_APPROVED] = 1;
    //
    // var_dump( $flags_model->update_company_flags( 7, $company_flags ) );
    // var_dump( $flags_model->get_error() );
    // exit;


    // /** @var \phs\plugins\s2p_ibs\PHS_Plugin_S2p_ibs $plugin_obj */
    // /** @var \phs\plugins\s2p_ibs\libraries\S2P_Ibs $ibs_library */
    // $plugin_obj = PHS::load_plugin( 's2p_ibs' );
    // $ibs_library = $plugin_obj->get_ibs_instance();
    //
    // var_dump( $ibs_library->sync_company( 10, array( 'gp_site_id' => '32094' ) ) );
    // var_dump( $ibs_library->get_error() );
    // exit;

    // /** @var \phs\plugins\s2p_gp\PHS_Plugin_S2p_gp $plugin_obj */
    // /** @var \phs\plugins\s2p_gp\libraries\S2P_Gp $gp_library */
    // $plugin_obj = PHS::load_plugin( 's2p_gp' );
    // $gp_library = $plugin_obj->get_gp_instance();
    //
    // var_dump( $gp_library->create_site_for_company( 10, array( 'force_sync' => true ) ) );
    // var_dump( $gp_library->get_error() );
    // exit;

    // /** @var \phs\plugins\s2p_mirakl\PHS_Plugin_S2p_mirakl $plugin_obj */
    // /** @var \phs\plugins\s2p_mirakl\libraries\S2P_Mirakl $mirakl_library */
    // $plugin_obj = PHS::load_plugin( 's2p_mirakl' );
    // $mirakl_library = $plugin_obj->get_mirakl_instance();
    //
    // var_dump( $mirakl_library->import_mirakl_shop( 8 ) );
    // var_dump( $mirakl_library->get_error() );
    // exit;

    // /** @var \phs\plugins\s2p_libraries\PHS_Plugin_S2p_libraries $library_obj */
    // $library_obj = PHS::load_plugin( 's2p_libraries' );
    // /** @var \phs\plugins\s2p_companies\models\PHS_Model_Companies $companies_obj */
    // $companies_obj = PHS::load_model( 'companies', 's2p_companies' );
    //
    // $reg_details = $library_obj::default_registry_details_arr();
    // $reg_details['signed_document'] = true;
    // $reg_details['doc_type'] = 1; // original
    // $reg_details['doc_document_type'] = 2; // contract
    // $reg_details['doc_document_file_type'] = 12; // TYPE_SIGNED_CONTRACT
    //
    // $library_obj::set_bb_code_registry( $library_obj::BB_CODE_REGISTRY_DETAILS_KEY, $reg_details );
    // $library_obj::set_bb_code_registry( $library_obj::BB_CODE_REGISTRY_COMPANY_KEY, $companies_obj->get_details( 4 ) );
    //
    // echo $library_obj->render_package_methods_fees_table();
    // var_dump( $library_obj->get_error() );
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
