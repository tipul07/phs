<?php

    define( 'PHS_TESTS_DIR', __DIR__.'/' );
    define( 'PHS_TESTS_PHS_DIR', PHS_TESTS_DIR.'phs/' );

    define( 'PHS_PREVENT_SESSION', true );
    define( 'PHS_SCRIPT_SCOPE', 'test' );

    include_once( PHS_TESTS_DIR.'../main.php' );

    include_once( PHS_CORE_DIR.'phs_cli.php' );
    include_once( PHS_TESTS_PHS_DIR.'phs_tests.php' );

    use phs\PHS_Scope;

    PHS_Scope::current_scope( PHS_Scope::SCOPE_TESTS );
