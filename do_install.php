<?php

    include_once( 'main.php' );

    if( @file_exists( PHS_SYSTEM_DIR.'install.php' ) )
        include_once( PHS_SYSTEM_DIR.'install.php' );

    // Walk thgrough plugins bootstrap scripts...
    foreach( array( PHS_CORE_PLUGIN_DIR, PHS_PLUGINS_DIR ) as $bstrap_dir )
    {
        if( ($install_scripts = @glob( $bstrap_dir . '*/install.php', GLOB_BRACE ))
        and is_array( $install_scripts ) )
        {
            foreach( $install_scripts as $install_script )
            {
                include_once( $install_script );
            }
        }
    }
