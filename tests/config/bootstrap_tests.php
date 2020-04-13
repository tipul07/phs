<?php

    define( 'PHS_PREVENT_SESSION', true );
    define( 'PHS_SCRIPT_SCOPE', 'test' );

    include( __DIR__.'/../../main.php' );

    use phs\PHS_Scope;

    PHS_Scope::current_scope( PHS_Scope::SCOPE_TESTS );
