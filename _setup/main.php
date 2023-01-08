<?php

    define( 'PHS_SETUP_FLOW', true );

    @date_default_timezone_set( 'Europe/London' );

    if( @function_exists( 'mb_internal_encoding' ) )
        @mb_internal_encoding( 'UTF-8' );

    include( 'libraries/phs_setup_utils.php' );

    use \phs\setup\libraries\PHS_Setup_utils;

    if( !($setup_path = PHS_Setup_utils::_detect_setup_path()) )
        $setup_path = './';
    if( !($phs_root_dir = @realpath( $setup_path.'/..' )) )
        $phs_root_dir = $setup_path.'../';

    $phs_root_dir = rtrim( $phs_root_dir, '/\\' ).'/';

    define( 'PHS_SETUP_PATH', $setup_path );

    define( 'PHS_SETUP_LIBRARIES_DIR', PHS_SETUP_PATH.'libraries/' );
    define( 'PHS_SETUP_CONFIG_DIR', PHS_SETUP_PATH.'config/' );
    define( 'PHS_SETUP_TEMPLATES_DIR', PHS_SETUP_PATH.'templates/' );

    define( 'PHS_SETUP_PHS_PATH', $phs_root_dir );

    define( 'PHS_SETUP_PHS_CONFIG_DIR', PHS_SETUP_PHS_PATH.'config/' );
    define( 'PHS_SETUP_PHS_SYSTEM_DIR', PHS_SETUP_PHS_PATH.'system/' );

    define( 'PHS_SETUP_PHS_CORE_DIR', PHS_SETUP_PHS_SYSTEM_DIR.'core/' );
    define( 'PHS_SETUP_PHS_LIBRARIES_DIR', PHS_SETUP_PHS_SYSTEM_DIR.'libraries/' );

    define( 'PHS_SETUP_PHS_CORE_MODEL_DIR', PHS_SETUP_PHS_CORE_DIR.'models/' );
    define( 'PHS_SETUP_PHS_CORE_CONTROLLER_DIR', PHS_SETUP_PHS_CORE_DIR.'controllers/' );
    define( 'PHS_SETUP_PHS_CORE_VIEW_DIR', PHS_SETUP_PHS_CORE_DIR.'views/' );
    define( 'PHS_SETUP_PHS_CORE_ACTION_DIR', PHS_SETUP_PHS_CORE_DIR.'actions/' );
    define( 'PHS_SETUP_PHS_CORE_PLUGIN_DIR', PHS_SETUP_PHS_CORE_DIR.'plugins/' );
    define( 'PHS_SETUP_PHS_CORE_SCOPE_DIR', PHS_SETUP_PHS_CORE_DIR.'scopes/' );

    // These paths will need a www pair, but after bootstrap
    define( 'PHS_THEMES_DIR', PHS_SETUP_PHS_PATH.'themes/' );
    define( 'PHS_LANGUAGES_DIR', PHS_SETUP_PHS_PATH.'languages/' );

    if( !@file_exists( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_error.php' )
     || !@file_exists( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_language.php' )
     || !@file_exists( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_registry.php' ) )
    {
        // TODO: Give option to manually create a file...
        ?>
        <h1>Paths detection failure...</h1>
        <p>Couldn't locate phs_error.php, phs_language.php and phs_registry.php files from PHS framework file structure. You should setup the framework manually.</p>
        <?php

        exit;
    }

    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_error.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_language_container.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_language.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_registry.php' );
    // Make sure we can use maintenance things anytime
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_maintenance.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_library.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_roles.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_instantiable.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_undefined_instantiable.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_has_db_settings.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_has_db_registry.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_plugin.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_model_base.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_model_mysqli.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_model_mongo.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_model.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_model_trait_statuses.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_model_trait_record_types.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_controller.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_controller_index.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_controller_api.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_controller_remote.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_controller_admin.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_controller_background.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_action.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_api_action.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_remote_action.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_contract.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_contract_list.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_event.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_encdec.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_db_interface.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_db_class.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_params.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_line_params.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_hooks.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_logger.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_notifications.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_utils.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_file_upload.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_paginator.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_paginator_action.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_paginator_exporter_library.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_autocomplete_action.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_db.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_session.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_crypt.php' );
    include_once( PHS_SETUP_PHS_CORE_VIEW_DIR.'phs_view.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_scope.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_bg_jobs.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_agent.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_ajax.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_api_base.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_api.php' );
    include_once( PHS_SETUP_PHS_CORE_DIR.'phs_api_remote.php' );

    include_once( PHS_SETUP_PHS_SYSTEM_DIR.'functions.php' );

    include( PHS_SETUP_LIBRARIES_DIR.'phs_setup.php' );
    include( PHS_SETUP_LIBRARIES_DIR.'phs_step.php' );
    include( PHS_SETUP_LIBRARIES_DIR.'phs_setup_view.php' );
    include( PHS_SETUP_LIBRARIES_DIR.'phs_setup_layout.php' );
