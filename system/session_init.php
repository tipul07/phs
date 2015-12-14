<?php

if( !defined( 'PHS_VERSION' ) )
    exit;

// Set required variables to session, but don't start it yet.
// Session will start when it will be asked first time for a variable or right after displaying the template
if( !\phs\PHS_session::init() )
{
    \phs\PHS_session::st_throw_error();
}
