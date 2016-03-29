<?php

    /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
    if( ($admin_plugin = phs\PHS::load_plugin( 'admin' )) )
    {
        $admin_plugin->check_installation();
    }
