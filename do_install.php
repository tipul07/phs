<?php

    include_once( 'main.php' );

    if( @file_exists( PHS_SYSTEM_DIR.'install.php' ) )
        include_once( PHS_SYSTEM_DIR.'install.php' );

    // Walk thgrough plugins bootstrap scripts...
    foreach( array( PHS_CORE_PLUGIN_DIR, PHS_PLUGINS_DIR ) as $bstrap_dir )
    {
        if( ($bootstrap_scripts = @glob( $bstrap_dir . '*/install.php', GLOB_BRACE ))
        and is_array( $bootstrap_scripts ) )
        {
            foreach( $bootstrap_scripts as $bootstrap_script )
            {
                include_once( $bootstrap_script );
            }
        }
    }
