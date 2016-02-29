<?php

namespace phs\libraries;

use \phs\libraries\PHS_Registry;
use \phs\PHS;

//! This class define all core hooks (for usability)
class PHS_Hooks extends PHS_Registry
{
    const H_AFTER_BOOTSTRAP = 'after_bootstrap', H_BEFORE_ACTION_EXECUTE = 'before_action_execute', H_AFTER_ACTION_EXECUTE = 'after_action_execute',

         // Logging hooks
         H_LOG = 'phs_logger',

         // URL hooks
         H_URL_PARAMS = 'phs_url_params',

         // User account hooks
         H_USER_DB_DETAILS = 'phs_user_db_details';
}
