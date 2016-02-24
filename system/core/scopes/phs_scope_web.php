<?php

namespace phs\system\core\scopes;

use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;

class PHS_Scope_Web extends PHS_Scope
{
    public function get_scope_type()
    {
        return self::SCOPE_WEB;
    }

    public function process_action_result( $action_result )
    {
        $action_result = self::validate_array( $action_result, PHS_Action::default_action_result() );
    }
}
