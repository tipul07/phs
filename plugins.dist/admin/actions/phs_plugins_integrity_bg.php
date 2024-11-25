<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;

class PHS_Action_Plugins_integrity_bg extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute()
    {
        $action_result = self::default_action_result();

        $action_result['ajax_result'] = [
            'has_error'        => false,
            'plugin_info'      => false,
            'instance_details' => false,
        ];

        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
         || empty($params['p'])) {
            $action_result['buffer'] = $this->_pt('Plugin name not provided for validity check');
            $action_result['ajax_result']['has_error'] = true;

            return $action_result;
        }

        $params['c'] = ($params['c'] ?? '') ?: '';
        $params['m'] = ($params['m'] ?? '') ?: '';
        $params['a'] = ($params['a'] ?? '') ?: '';
        $params['co'] = ($params['co'] ?? '') ?: '';
        $params['e'] = ($params['e'] ?? '') ?: '';

        if (empty($params['dir'])) {
            $params['dir'] = '';
        } else {
            // When passing directory to PHS::load_* methods, directories should have as separator _
            $params['dir'] = str_replace('/', '_', $params['dir']);
        }

        $action_result['ajax_result']['params'] = $params;

        if (empty($params['c']) && empty($params['m']) && empty($params['a']) && empty($params['co']) && empty($params['e'])) {
            if (!($plugin_obj = PHS::load_plugin($params['p']))) {
                if (self::st_has_error()) {
                    $error_msg = self::st_get_error_message();
                } else {
                    $error_msg = $this->_pt('Unknown error while instantiating plugin.');
                }

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif (($plugin_info = $plugin_obj->get_plugin_info())) {
                $action_result['ajax_result']['plugin_info'] = $plugin_info;
            }
        } elseif (!empty($params['c'])) {
            if (!($controller_obj = PHS::load_controller($params['c'], $params['p']))) {
                if (self::st_has_error()) {
                    $error_msg = self::st_get_error_message();
                } else {
                    $error_msg = $this->_pt('Unknown error while instantiating controller.');
                }

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif (($instance_details = $controller_obj->instance_details())) {
                $action_result['ajax_result']['instance_details'] = $instance_details;
            }
        } elseif (!empty($params['a'])) {
            if (!($action_obj = PHS::load_action($params['a'], $params['p'], $params['dir']))) {
                if (self::st_has_error()) {
                    $error_msg = self::st_get_error_message();
                } else {
                    $error_msg = $this->_pt('Unknown error while instantiating action.');
                }

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif (($instance_details = $action_obj->instance_details())) {
                $action_result['ajax_result']['instance_details'] = $instance_details;
            }
        } elseif (!empty($params['co'])) {
            if (!($contract_obj = PHS::load_contract($params['co'], $params['p'], $params['dir']))) {
                if (self::st_has_error()) {
                    $error_msg = self::st_get_error_message();
                } else {
                    $error_msg = $this->_pt('Unknown error while instantiating contract.');
                }

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif (($instance_details = $contract_obj->instance_details())) {
                $action_result['ajax_result']['instance_details'] = $instance_details;
            }
        } elseif (!empty($params['e'])) {
            if (!($event_obj = PHS::load_event($params['e'], $params['p'], $params['dir']))) {
                if (self::st_has_error()) {
                    $error_msg = self::st_get_error_message();
                } else {
                    $error_msg = $this->_pt('Unknown error while instantiating event.');
                }

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif (($instance_details = $event_obj->instance_details())) {
                $action_result['ajax_result']['instance_details'] = $instance_details;
            }
        } elseif (!empty($params['m'])) {
            if (!($model_obj = PHS::load_model($params['m'], $params['p']))) {
                if (self::st_has_error()) {
                    $error_msg = self::st_get_error_message();
                } else {
                    $error_msg = $this->_pt('Unknown error while instantiating model.');
                }

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif (($instance_details = $model_obj->instance_details())) {
                $action_result['ajax_result']['instance_details'] = $instance_details;
            }
        } else {
            $action_result['buffer'] = $this->_pt('Nothing to instantiate. Please check parameters.');
            $action_result['ajax_result']['has_error'] = true;
        }

        return $action_result;
    }
}
