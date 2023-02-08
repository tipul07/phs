<?php
namespace phs\libraries;

use phs\PHS_Scope;
use phs\PHS_Api_remote;

abstract class PHS_Remote_action extends PHS_Api_action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_REMOTE];
    }

    /**
     * @return null|\phs\PHS_Api_base
     */
    public function get_action_api_instance() : ?\phs\PHS_Api_base
    {
        if (!$this->api_obj
         && PHS_Scope::current_scope() === PHS_Scope::SCOPE_REMOTE) {
            $this->api_obj = PHS_Api_remote::api_factory();
        }

        return $this->api_obj;
    }

    /**
     * @return null|array
     */
    public function get_action_remote_domain() : ?array
    {
        if (!($api_obj = $this->get_action_api_instance())
         || !($domain_arr = $api_obj->api_flow_value('remote_domain'))) {
            return null;
        }

        return $domain_arr;
    }

    public function get_request_body() : ?array
    {
        static $json_request = null;

        if ($json_request === null) {
            if (!($api_obj = $this->get_action_api_instance())
                || !($message_arr = $api_obj->api_flow_value('remote_domain_message'))
                || !is_array($message_arr)
                || empty($message_arr['request_arr']) || !is_array($message_arr['request_arr'])) {
                $json_request = [];
            } else {
                $json_request = $message_arr['request_arr'];
            }
        }

        return $json_request;
    }
}
