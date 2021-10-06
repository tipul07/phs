<?php

namespace phs\system\core\scopes;

use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Logger;

class PHS_Scope_Test extends PHS_Scope
{
    public function get_scope_type()
    {
        return self::SCOPE_TESTS;
    }

    /**
     * @param false|array $action_result
     * @param false|array $static_error_arr
     *
     * @return array
     */
    public function process_action_result( $action_result, $static_error_arr = false )
    {
        $action_result = self::validate_array( $action_result, PHS_Action::default_action_result() );

        $notifications_list_arr = [
            'success' => PHS_Notifications::notifications_success(),
            'warnings' => PHS_Notifications::notifications_warnings(),
            'errors' => PHS_Notifications::notifications_errors(),
        ];

        foreach( $notifications_list_arr as $notification_type => $notifications_arr )
        {
            if( empty( $notifications_arr ) || !is_array( $notifications_arr ) )
                continue;

            PHS_Logger::logf( ucfirst( $notification_type ).' notifications:'."\n".implode( "\n", $notifications_arr ), PHS_Logger::TYPE_TESTS );
        }

        if( !empty( $action_result['request_login'] ) )
            PHS_Logger::logf( 'Script required login action, but we are in a test script...', PHS_Logger::TYPE_TESTS );

        if( !empty( $action_result['redirect_to_url'] ) )
            PHS_Logger::logf( 'We are told to redirect to an URL ('.$action_result['redirect_to_url'].'), but we are in a test script...', PHS_Logger::TYPE_TESTS );

        return $action_result;
    }
}
