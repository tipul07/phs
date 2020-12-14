<?php

    if( !defined( 'PHS_VERSION' )
     || !defined( 'PHS_INSTALLING_FLOW' ) || !constant( 'PHS_INSTALLING_FLOW' ) )
        exit;

    use \phs\PHS;

    /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
    if( !($plugins_model = PHS::load_model( 'plugins' )) )
    {
        if( !PHS::st_has_error() )
            PHS::st_set_error( -1, PHS::_t( 'Error instantiating plugins model.' ) );

        return PHS::st_get_error();
    }

    if( !$plugins_model->check_installation() )
    {
        if( $plugins_model->has_error() )
            return $plugins_model->get_error();

        return PHS::arr_set_error( -1, PHS::_t( 'Error while checking plugins model installation.' ) );
    }

    if( ($core_models = PHS::get_core_models())
     && is_array( $core_models ) )
    {
        foreach( $core_models as $core_model )
        {
            if( ($model_obj = PHS::load_model( $core_model )) )
                $model_obj->check_installation();

            else
            {
                if( !PHS::st_has_error() )
                    PHS::st_set_error( -1, PHS::_t( 'Error instantiating core model [%s].', $core_model ) );

                return PHS::st_get_error();
            }
        }
    }

    if( ($plugins_arr = $plugins_model->cache_all_dir_details()) === false
     || !is_array( $plugins_arr ) )
    {
        if( !$plugins_model->has_error() )
            PHS::st_set_error( -1, PHS::_t( 'Error obtaining plugins list.' ) );
        else
            PHS::st_copy_error( $plugins_model );

        return PHS::st_get_error();
    }

    $priority_plugins = [ 'emails', 'sendgrid', 'accounts', 'messages', 'notifications', 'captcha', 'admin' ];
    $installing_plugins_arr = [];
    foreach( $priority_plugins as $plugin_name )
    {
        if( !isset( $plugins_arr[$plugin_name] ) )
            continue;

        $installing_plugins_arr[$plugin_name] = $plugins_arr[$plugin_name];
    }

    // Make sure distribution plugins get updated first
    $dist_plugins = PHS::get_distribution_plugins();
    foreach( $dist_plugins as $plugin_name )
    {
        if( isset( $installing_plugins_arr[$plugin_name] )
         || !isset( $plugins_arr[$plugin_name] ) )
            continue;

        $installing_plugins_arr[$plugin_name] = $plugins_arr[$plugin_name];
    }

    foreach( $plugins_arr as $plugin_name => $plugin_obj )
    {
        if( isset( $installing_plugins_arr[$plugin_name] ) )
            continue;

        $installing_plugins_arr[$plugin_name] = $plugin_obj;
    }

    /**
     * @var string $plugin_name
     * @var \phs\libraries\PHS_Plugin $plugin_obj
     */
    foreach( $installing_plugins_arr as $plugin_name => $plugin_obj )
    {
        if( !$plugin_obj->check_installation() )
            return $plugin_obj->get_error();
    }

    return true;
