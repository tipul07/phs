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

    if( !@file_exists( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_error.php' )
     or !@file_exists( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_language.php' )
     or !@file_exists( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_registry.php' ) )
    {
        // TODO: Give option to manually create a file...
        ?>
        <h1>Paths detection failure...</h1>
        <p>Couldn't locate phs_error.php, phs_language.php and phs_registry.php files from . You should manually setup the framework.</p>
        <?php

        exit;
    }

    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_error.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_language.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_registry.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_instantiable.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_signal_and_slot.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_has_db_settings.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_has_db_registry.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_params.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_line_params.php' );
    include_once( PHS_SETUP_PHS_LIBRARIES_DIR.'phs_utils.php' );

    include_once( PHS_SETUP_PHS_SYSTEM_DIR.'functions.php' );

    include( PHS_SETUP_LIBRARIES_DIR.'phs_setup.php' );
    include( PHS_SETUP_LIBRARIES_DIR.'phs_step.php' );
    include( PHS_SETUP_LIBRARIES_DIR.'phs_setup_view.php' );
    include( PHS_SETUP_LIBRARIES_DIR.'phs_setup_layout.php' );
