<?php

    /** @var \phs\plugins\emails\PHS_Plugin_Emails $emails_plugin */
    if( ($emails_plugin = phs\PHS::load_plugin( 'emails' )) )
    {
        $emails_plugin->check_installation();
    }
