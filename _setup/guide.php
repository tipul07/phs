<?php

if (@file_exists(__DIR__.'/../main.php')) {
    echo 'You should use CLI application to manage the framework...';
    exit;
}

include 'main.php';

use phs\setup\libraries\PHS_Setup_layout;

echo PHS_Setup_layout::get_instance()->render('guide');
