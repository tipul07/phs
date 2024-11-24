<?php

namespace phs\system\core\scopes;

use phs\PHS;
use Exception;
use phs\PHS_Scope;
use phs\PHS_Api_graphql;

class PHS_Scope_Graphql extends PHS_Scope
{
    public function get_scope_type() : int
    {
        return self::SCOPE_GRAPHQL;
    }

    /**
     * $action_result is processed in \phs\graphql\libraries\PHS_Graphql::resolve_request()
     * @see \phs\graphql\libraries\PHS_Graphql::resolve_request()
     * @param mixed $action_result
     * @param mixed $static_error_arr
     */
    public function process_action_result($action_result, $static_error_arr = false)
    {
        if (empty($action_result['output_arr'])) {
            return null;
        }

        if (!@headers_sent()) {
            @header('Content-Type: application/json; charset=UTF-8');
        }

        $result_success = true;

        try {
            if (!empty($action_result['output_arr']['errors'])
               && empty($action_result['output_arr']['data'])) {
                PHS_Api_graphql::generic_error();
                $result_success = false;
            }

            echo @json_encode($action_result['output_arr'], JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            echo '{"errors":[{"message":"Internal error.'
                 .(PHS::st_debugging_mode() ? ' ('.$e->getMessage().')' : '').'"}]}';
        }

        return $result_success;
    }
}
