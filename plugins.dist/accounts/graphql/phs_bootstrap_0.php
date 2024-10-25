<?php

use phs\PHS_Scope;
use phs\graphql\libraries\PHS_Graphql;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\graphql\types\PHS_Graphql_Accounts;

/** @var PHS_Plugin_Accounts $accounts_plugin */
if (!defined('PHS_PATH')
    || PHS_Scope::current_scope() !== PHS_Scope::SCOPE_GRAPHQL
    || !($accounts_plugin = PHS_Plugin_Accounts::get_instance())
    || !$accounts_plugin->plugin_active()) {
    return null;
}

PHS_Graphql::register_type('account', PHS_Graphql_Accounts::class, true);
