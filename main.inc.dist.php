<?php

@date_default_timezone_set( 'Europe/London' );

if( @function_exists( 'mb_internal_encoding' ) )
    @mb_internal_encoding( 'UTF-8' );

// Platform full absolute path
define( 'PHS_PATH', '/absolute/path/to/root/' );

// If no domain is defined for current request in config directory system will use PHS_DEFAULT_* values
define( 'PHS_DEFAULT_DOMAIN', 'domain.com' ); // only domain name (used to set cookies)
define( 'PHS_DEFAULT_PORT', '' ); // port (if applicable) if using default port don't put anything here
define( 'PHS_DEFAULT_DOMAIN_PATH', 'url/path/to/root/' ); // tells the path from domain to get to root URL of the platform

// Default database settings (these settings will be used when creating default database connection)
define( 'PHS_DB_DRIVER', 'mysqli' );
define( 'PHS_DB_HOSTNAME', 'localhost' );
define( 'PHS_DB_USERNAME', 'testdb' );
define( 'PHS_DB_PASSWORD', 'Fj4EAdeGuSyXJ737' );
define( 'PHS_DB_DATABASE', 'testdb' );
define( 'PHS_DB_PREFIX', '' );
define( 'PHS_DB_PORT', '3306' );
define( 'PHS_DB_TIMEZONE', date( 'P' ) );
define( 'PHS_DB_CHARSET', 'UTF8' );

// Default session settings
define( 'PHS_DEFAULT_SESSION_DIR', PHS_PATH.'sess/' );
define( 'PHS_DEFAULT_SESSION_NAME', 'PHS_SESS' );
// 0 to close session when browser closes...
define( 'PHS_DEFAULT_SESSION_COOKIE_LIFETIME', 0 );
define( 'PHS_DEFAULT_SESSION_COOKIE_PATH', '/' );
// Session starts automatically if it is required a variable.
// If system gets to the point to start displaying something and this constant is set to true, session will be started before displaying
// It is important to start the session before sending headers, as it cannot be started once headers were sent to browser
define( 'PHS_DEFAULT_SESSION_AUTOSTART', false );
// END Default session settings

define( 'PHS_DEFAULT_THEME', 'default' );

define( 'PHS_DEFAULT_CRYPT_KEY', '@#&*(PHS_cryptencodingKeY!#@)^-=[]{};,./<>?' );
global $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;
$PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR = array(
    '57c6a0ec27434eddfb32d43736c6bfb1',
    'f8aa72d92f2581270072bdf43515a161',
    '509bbfa822ed3ba8ab80522c319ae4f5',
    'f8c52bdf5da03743524be39a17fdad07',
    '13f7b07bca6d6f049c0c535f6b53e306',
    '9dfb76e5bdb6e69950bf1c39712a8713',
    '07de9cda8fe9c21917f0847682d9f6ef',
    'f8ef7cf7037e35388daa726f1532075f',
    '55dbb095f7644816dc8bf4f743550b84',
    '7a968af30bf9b44b89ab165ca282c149',
    '497cbe01dca0a6c9caf6324bc37f864e',
    'aae775f225864cf619cbb2693d340f8f',
    '08b1a9be6eac7c97fcc13dd02c6d7426',
    'a8957894d91d46f64c196b0389d54093',
    'ec6c58ad9d4a5bfdcd8fe6fd9af18b55',
    'dbd6e8354ee7eeb226f591b65974777b',
    '33064cf038f5f87c90a5cb8a073babe9',
    '881c197be630eb8816b37c960992051c',
    '75c714ac95a693b64db50b1f8ae561b4',
    'bd31733b637eb7b42c06547409fa1c5e',
    'eddc457fee64b9c44d977dadfc3bc1c8',
    '613f4e4a5b37cb8c4298191bc5ae424b',
    '56fb4c50bb30cd60ee909a81c0d85c23',
    '411999a188d60ece38f4192c3169168d',
    '4fd32c2be67d3cd4a69dd7760740fd4e',
    '70c50c97660655f0ed62ebc6e0575171',
    '6ba1839006eae5836a3db329a8650f95',
    'ee9b27049bf77c1c12c99b96a49a3104',
    '13eab91822f11eadad147a7479bbd070',
    'e80c16a2b1078fada999a08d36848153',
    'a3a10fde40c06ded23b57664772db9aa',
    '11caea09b524c2e46249851a7acc0f61',
    '9d656a3242963e930cb8fdfa5b324ea8',
    'fa68be558cb01ca6e2d641ecdde31848',
);

// php binary executable full path (point this to your CLI executable)
define( 'PHP_EXEC', '/usr/bin/php' );

// Debugging mode?
define( 'PHS_DEBUG_MODE', true );
define( 'PHS_DEBUG_THROW_ERRORS', true );

include_once( PHS_PATH.'bootstrap.inc.php' );
