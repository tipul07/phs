<?php

    @header( 'Content-type: text/javascript' );

    $dont_relog_user = 1;
    $dont_track_path = 1;
    define( 'PAGE_SKIP_BAN_CHECK', true );
    include( '../../main.inc.php' );

    $act = PHS_params::_g( 'act', PHS_PARAMS_NOHTML );
    $term = PHS_params::_g( 'term', PHS_PARAMS_REMSQL_CHARS );

    switch( $act )
    {
        case 'brands':

            include_once( $APP_CFG['libdir'].'am_brands.inc.php' );

            if( empty( $CUSER_ARR['id'] )
            or !account_class::can_list_brands( $CUSER_ARR ) )
                exit;

            if( !($lang = PHS_params::_g( 'lang', PHS_PARAMS_INTEGER ))
             or !PHS_lang::valid_language( $lang ) )
                $lang = PHS_lang::get_current_language();

            $list_arr = array();
            $list_arr['return_qid'] = false;
            $list_arr['order_by'] = 'brands.name ASC';
            $list_arr['{linkage_func}'] = 'AND';

            $list_arr['fields']['status'] = am_brands_class::STATUS_ACTIVE;
            if( !is_null( $term ) and !empty( $term ) )
                $list_arr['fields']['{linkage}'] = array(
                                'fields' => array(
                                                '{linkage_func}' => 'or',
                                                'name' => array( 'check' => 'LIKE', 'value' => '%'.prepare_data( $term ).'%' ),
                                                'code' => array( 'check' => 'LIKE', 'value' => '%'.prepare_data( $term ).'%' ),
                                                )
                                );

            $return_arr = array();
            if( ($brands_arr = am_brands_class::get_list( $list_arr ))
            and is_array( $brands_arr ) )
            {
                foreach( $brands_arr as $brand_id => $brand_arr )
                {
                    $return_arr[] = array(
                        'id' => $brand_arr['code'],
                        'label' => $brand_arr['name'],
                        'value' => $brand_arr['name'],
                    );
                }
            }

            echo @json_encode( $return_arr );

        break;

        case 'assets':

            include_once( $APP_CFG['libdir'].'am_assets.inc.php' );

            if( empty( $CUSER_ARR['id'] )
            or !account_class::can_list_assets( $CUSER_ARR ) )
                exit;

            $fcompany = PHS_params::_gp( 'fcompany', PHS_params::T_INT );

            $list_arr = array();
            $list_arr['return_qid'] = false;
            $list_arr['order_by'] = 'assets.serial ASC, assets.type ASC';
            $list_arr['{linkage_func}'] = 'AND';

            $list_arr['fields']['status'] = am_assets_class::STATUS_ACTIVE;

            if( !empty( $fcompany ) )
                $list_arr['fields']['company_id'] = $fcompany;

            if( !is_null( $term ) and !empty( $term ) )
                $list_arr['fields']['{linkage}'] = array(
                                'fields' => array(
                                                '{linkage_func}' => 'or',
                                                'type' => array( 'check' => 'LIKE', 'value' => '%'.prepare_data( $term ).'%' ),
                                                'serial' => array( 'check' => 'LIKE', 'value' => '%'.prepare_data( $term ).'%' ),
                                                'engine_sn' => array( 'check' => 'LIKE', 'value' => '%'.prepare_data( $term ).'%' ),
                                                'catarg_sn' => array( 'check' => 'LIKE', 'value' => '%'.prepare_data( $term ).'%' ),
                                                )
                                );

            $list_arr['flags'] = array( 'include_brand_details' );

            $return_arr = array();
            if( ($assets_arr = am_assets_class::get_list( $list_arr ))
            and is_array( $assets_arr ) )
            {
                foreach( $assets_arr as $asset_id => $asset_arr )
                {
                    $return_arr[] = array(
                        'id' => $asset_arr['id'],
                        'label' => $asset_arr['type'].' ('.$asset_arr['brand_name'].', #'.$asset_arr['serial'].')',
                        'value' => $asset_arr['type'].' ('.$asset_arr['brand_name'].', #'.$asset_arr['serial'].')',
                    );
                }
            }

            echo @json_encode( $return_arr );

        break;

        case 'companies':

            include_once( $APP_CFG['libdir'].'am_companies.inc.php' );

            if( empty( $CUSER_ARR['id'] )
            or !account_class::can_list_companies( $CUSER_ARR ) )
                exit;

            $list_arr = array();
            $list_arr['return_qid'] = false;
            $list_arr['order_by'] = 'companies.name ASC';
            $list_arr['{linkage_func}'] = 'AND';

            $list_arr['fields']['status'] = am_companies_class::STATUS_ACTIVE;

            if( !is_null( $term ) and !empty( $term ) )
                $list_arr['fields']['name'] = array( 'check' => 'LIKE', 'value' => '%'.prepare_data( $term ).'%' );

            $return_arr = array();
            if( ($companies_arr = am_companies_class::get_list( $list_arr ))
            and is_array( $companies_arr ) )
            {
                foreach( $companies_arr as $company_id => $company_arr )
                {
                    $return_arr[] = array(
                        'id' => $company_arr['id'],
                        'label' => $company_arr['name'],
                        'value' => $company_arr['name'],
                    );
                }
            }

            echo @json_encode( $return_arr );

        break;
    }
