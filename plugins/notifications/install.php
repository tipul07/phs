<?php

    /** @var \phs\plugins\notifications\PHS_Plugin_Notifications $accounts_plugin */
    if( ($notifications_plugin = phs\PHS::load_plugin( 'notifications' )) )
    {
        $notifications_plugin->check_installation();
    }
