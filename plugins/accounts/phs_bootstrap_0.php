<?php

    /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
    if( ($accounts_plugin = phs\PHS::load_plugin( 'accounts' )) )
    {
        $accounts_plugin->check_installation();
    }
