<?php

    /** @var \phs\plugins\captcha\PHS_Plugin_Captcha $captcha_plugin */
    if( ($captcha_plugin = phs\PHS::load_plugin( 'captcha' )) )
    {
        $captcha_plugin->check_installation();
    }
