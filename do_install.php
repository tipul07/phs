<?php

    include_once( 'main.php' );

    use \phs\PHS;

    if( @file_exists( PHS_SYSTEM_DIR.'install.php' ) )
    {
        $system_install_result = include_once(PHS_SYSTEM_DIR . 'install.php');

        if( $system_install_result !== true )
        {
            echo '<pre>';
            echo PHS::_t( 'ERROR while running system install script [%s]:', 'CORE INSTALL' );
            var_dump( $system_install_result );
            echo '</pre>';
            exit;
        }
    }

    // Walk thgrough plugins bootstrap scripts...
    foreach( array( PHS_CORE_PLUGIN_DIR, PHS_PLUGINS_DIR ) as $bstrap_dir )
    {
        if( ($install_scripts = @glob( $bstrap_dir . '*/install.php', GLOB_BRACE ))
        and is_array( $install_scripts ) )
        {
            foreach( $install_scripts as $install_script )
            {
                $install_result = include_once( $install_script );

                if( $install_result === false )
                {
                    echo '<pre>';
                    echo PHS::_t( 'ERROR while running system install script [%s]:', $install_script );
                    var_dump( $system_install_result );
                    echo '</pre>';
                }
            }
        }
    }
