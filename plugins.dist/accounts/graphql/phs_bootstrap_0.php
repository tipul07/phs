<?php

use phs\graphql\libraries\PHS_Graphql;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\graphql\types\PHS_Graphql_Accounts;
use phs\plugins\accounts\graphql\types\PHS_Graphql_Account_details;

if (!PHS_Graphql::valid_context()
    || !($accounts_plugin = PHS_Plugin_Accounts::get_instance())
    || !$accounts_plugin->plugin_active()) {
    return;
}

PHS_Graphql::register_type(PHS_Graphql_Accounts::class, true);
PHS_Graphql::register_type(PHS_Graphql_Account_details::class, true);
