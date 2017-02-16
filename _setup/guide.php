<?php

    include( 'main.php' );

    use \phs\setup\libraries\PHS_Setup_layout;

    echo PHS_Setup_layout::get_instance()->render( 'guide' );
