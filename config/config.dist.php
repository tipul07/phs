<?php

    if( !defined( 'PHS_VERSION' ) )
        exit;

// Prety name of the site (will be displayed to visitors as site name) (eg. MyNiceSite.com)
define( 'PHS_SITE_NAME', 'PoweredByPHS.com' );

// Where should Contact Us send emails (this email(s) might be shown to users) this can contain comma separated emails
define( 'PHS_CONTACT_EMAIL', 'contact@email.com' );

// domain to set session cookie (could be .example.com serving all subdomains)
define( 'PHS_COOKIE_DOMAIN', '.domain.com' );
// Domain name only (eg. www.example.com)
define( 'PHS_DOMAIN', 'domain.only.com' );
// If diffrent than "normal" domain (eg. secure.example.com)
define( 'PHS_SSL_DOMAIN', PHS_DOMAIN );
// port if site is accessible with a port other than 80. If default port used (80) leave empty
define( 'PHS_PORT', 'site port' );
// https port if site is accessible with a port other than 443. If default port used (443) leave empty
define( 'PHS_SSL_PORT', 'https site port' );
// if root URL is accessible with a path appended to domain name. Leave empty if domain points to root of platform
define( 'PHS_DOMAIN_PATH', '/site/path/' ); // ending in /

// Sets uploads dir for current domain configuratrion (if you want to use domain specific location uncomment this line, otherwise PHS_FRAMEWORK_LOGS_DIR in main.php will be used)
// define( 'PHS_UPLOADS_DIR', PHS_PATH.'_uploads/' );

// Domain logging dir... (if you want to use domain specific location uncomment this line, otherwise PHS_FRAMEWORK_LOGS_DIR in main.php will be used)
// define( 'PHS_LOGS_DIR', PHS_SYSTEM_DIR.'logs/' );

// What theme should domain use (don't define anything if default theme should be used)
define( 'PHS_THEME', PHS_DEFAULT_THEME );

// Required only if you want to have different crypting parameters
// !!! BUT ONLY IF DOMAIN USES IT'S OWN DATABASE !!!
define( 'PHS_CRYPT_KEY', '@#&*(PHS_cryptencodingKeY!#@)^-=[]{};,./<>?' );
global $PHS_CRYPT_INTERNAL_KEYS_ARR;
$PHS_CRYPT_INTERNAL_KEYS_ARR = array(
    '234547ce55318ee6eee60ea83d73ec5e',
    'c9f2d14d7a0cd0bd2a3d683f50eb1aee',
    'e812e40b98bbaf5c365399ef34dccfd1',
    '93186ef5aefab964308d94107be4231e',
    '7ff85986795aedd82f0fc3f6aec0ce86',
    '55ee6eba569bafc847f89f091a22baed',
    '29dad6f8b36ddcae3d8ca836644f2441',
    '297c4c88149f5b22f7db7b6648601ab3',
    'baca40b542ad7cee9e6a0869bc808480',
    '7f8ef901ec7ea229a35f4d82815fd449',
    'c226f1f62c8c8a341aa4c1e9d0e627d2',
    'd64075eb37b433fa781f438988b99395',
    'dcc852aa1b593badeead5c84a1994f52',
    '377b375f663d6cd5d17a39c7ff3f0c06',
    'b91472b3aa2a1810e2c5d9f28751d83c',
    '6aaaf64c5021612dafef70161367b10b',
    '7f384e078f67ddeb87ffb07d17add664',
    'e8a35728c4f78b2271b8e01ad09b1a1e',
    '95fedb3edd2903996fe64f7d746cf58d',
    '7a88978c156fb4f3e8190e5313b97be1',
    'e75c2a1e9c63ab688f71b83bb6499f92',
    'b23c32d7fd670d163f0bc87e3ad693b4',
    '677bbb769ec95a4c9e67ace5d16d1b63',
    '95eb0a87f7c68496ec2d4bc15c1e8cf4',
    '34e857d221e75365bef5a0bb98a56f40',
    'ee01a5c55786605fb72181fec94e6326',
    '4d4c1342f0256e78c3e0a8ed5750926d',
    '371796e3955eb0dafe48341e6f676be9',
    'cb493e9b75d2d40363cade64edf5832c',
    '00b371d8b18a38486b0d391b5856b014',
    'fc80bc47eeeaba34f86be712245ea999',
    '646daa56be6b13eacfb3c23054d3bef9',
    '9a8cab9fefcc0668f5bb788b02ed5534',
    'de7f73a3212ecd82783cef5676f35323',
);

// Put a string here which will be the name of database connection used
// Define as many dabatase connections here
define( 'PHS_DB_CONNECTION', PHS_DB_DEFAULT_CONNECTION );

// Session settings (if not default ones)
define( 'PHS_SESSION_DIR', PHS_DEFAULT_SESSION_DIR );
define( 'PHS_SESSION_NAME', PHS_DEFAULT_SESSION_NAME );
// 0 to close session when browser closes... This is session lifetime, not how long user will be logged in
// We can save in session language or other details that should be available for a longer period
define( 'PHS_SESSION_COOKIE_LIFETIME', PHS_DEFAULT_SESSION_COOKIE_LIFETIME );
define( 'PHS_SESSION_COOKIE_PATH', '/'.trim( PHS_DEFAULT_DOMAIN_PATH, '/' ) );
// SameSite session cookie settings (can be None, Lax or Strict)
define( 'PHS_SESSION_SAMESITE', PHS_DEFAULT_SESSION_SAMESITE );
// Session starts automatically if it is required a variable.
// If system gets to the point to start displaying something and this constant is set to true, session will be started before displaying
// It is important to start the session before sending headers, as it cannot be started once headers were sent to browser
define( 'PHS_SESSION_AUTOSTART', false );

// Define domain specific languages (if required)

// Controlling database library behaviour (if different than default one)
// if( !defined( 'PHS_DB_SILENT_ERRORS' ) )
//     define( 'PHS_DB_SILENT_ERRORS', false );
// if( !defined( 'PHS_DB_DIE_ON_ERROR' ) )
//     define( 'PHS_DB_DIE_ON_ERROR', true );
// if( !defined( 'PHS_DB_CLOSE_AFTER_QUERY' ) )
//     define( 'PHS_DB_CLOSE_AFTER_QUERY', true );

// Define domain specific database settings (if required)
// $mysql_settings = array();
// $mysql_settings['driver'] = 'mysqli';
// $mysql_settings['host'] = '';
// $mysql_settings['user'] = '';
// $mysql_settings['password'] = '';
// $mysql_settings['database'] = '';
// $mysql_settings['prefix'] = '';
// $mysql_settings['port'] = '';
// $mysql_settings['timezone'] = date( 'P' );
// $mysql_settings['charset'] = 'UTF8';
// $mysql_settings['driver_settings'] = array( 'sql_mode' => '-ONLY_FULL_GROUP_BY' );
//
// define( 'PHS_DB_CONNECTION', 'db_domain_default' );
//
// if( !PHS_Db::add_db_connection( PHS_DB_DOMAIN_CONNECTION, $mysql_settings ) )
// {
//    PHS_Db::st_throw_error();
//    exit;
// }
