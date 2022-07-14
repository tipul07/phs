<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Bg_jobs;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Instantiable;

class PHS_Action_Plugins_integrity extends PHS_Action
{
    const HOOK_LOG_ACTIONS = 'phs_system_logs_actions';

    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX ];
    }

    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Plugins\' Integrity' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result['request_login'] = true;

            return $action_result;
        }

        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_instance */
        if( !($admin_plugin = PHS::load_plugin( 'admin' ))
         || !($plugins_instance = PHS::load_model( 'plugins' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load plugins model.' ) );
            return self::default_action_result();
        }

        if( !$admin_plugin->can_admin_manage_plugins( $current_user ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to manage plugins.' ) );
            return self::default_action_result();
        }

        define( 'PLUGIN_NAME_ALL', '_all_' );

        if( !($plugin_names_arr = $plugins_instance->get_all_plugin_names_from_dir()) )
            $plugin_names_arr = [];

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        if( !($check_plugin = PHS_Params::_pg( 'check_plugin', PHS_Params::T_NOHTML )) )
            $check_plugin = '';
        $command = PHS_Params::_pg( 'command', PHS_Params::T_NOHTML );

        if( !empty( $command )
         && !in_array( $command, [ 'integrity_check', 'download_file' ], true ) )
            $command = 'integrity_check';

        if( !empty( $command ) )
        {
            $action_result = self::default_action_result();

            switch( $command )
            {
                default:
                case 'integrity_check':
                    if( empty( $check_plugin )
                     || ($check_plugin !== PLUGIN_NAME_ALL
                            && !in_array( $check_plugin, $plugin_names_arr, true )) )
                    {
                        PHS_Notifications::add_error_notice( $this->_pt( 'Please provide a valid plugin name.' ) );
                        return $action_result;
                    }

                    $action_result['buffer'] = '';
                    if( $check_plugin !== PLUGIN_NAME_ALL )
                        $action_result['buffer'] .= $this->check_plugin( $check_plugin );

                    else
                    {
                        foreach( $plugin_names_arr as $plugin_name )
                        {
                            $action_result['buffer'] .= $this->check_plugin( $plugin_name );
                        }
                    }
                break;
            }

            return $action_result;
        }

        $data = [
            'PLUGIN_NAME_ALL' => PLUGIN_NAME_ALL,
            'check_plugin' => $check_plugin,
            'plugin_names_arr' => $plugin_names_arr,
        ];

        return $this->quick_render_template( 'plugins_integrity', $data );
    }

    private function check_plugin( $plugin_name )
    {
        if( !($controllers_arr = PHS::get_plugin_scripts_from_dir( $plugin_name, PHS_Instantiable::INSTANCE_TYPE_CONTROLLER )) )
            $controllers_arr = [];
        if( !($actions_arr = PHS::get_plugin_scripts_from_dir( $plugin_name, PHS_Instantiable::INSTANCE_TYPE_ACTION )) )
            $actions_arr = [];
        if( !($contracts_arr = PHS::get_plugin_scripts_from_dir( $plugin_name, PHS_Instantiable::INSTANCE_TYPE_CONTRACT )) )
            $contracts_arr = [];
        if( !($models_arr = PHS::get_plugin_scripts_from_dir( $plugin_name, PHS_Instantiable::INSTANCE_TYPE_MODEL )) )
            $models_arr= [];

        $return_str = '<hr/><p>'.$this->_pt( 'Checking plugin %s (%s controllers, %s actions, %s contracts, %s models)...',
                                        '<strong>'.$plugin_name.'</strong>',
                                        count( $controllers_arr ), count( $actions_arr ), count( $contracts_arr ), count( $models_arr ) ).'</p>';

        $return_str .= '<p>Plugin instance... ';
        if( ($action_result = $this->check_plugin_integrity( $plugin_name ))
         && !empty( $action_result['ajax_result'] )
         && empty( $action_result['ajax_result']['has_error'] ) )
        {
            if( !empty( $action_result['ajax_result']['plugin_info'] )
             && is_array( $action_result['ajax_result']['plugin_info'] ) )
            {
                $plugin_info = $action_result['ajax_result']['plugin_info'];

                $return_str .= $plugin_info['id'].' ('.$plugin_info['name'].' v'.$plugin_info['script_version'].', '.
                               (!empty( $plugin_info['is_installed'] )?
                                   $this->_pt( 'INSTALLED' ):
                                   $this->_pt( 'NOT Installed' )).') ';
            }

            $return_str .= '<span style="color:green">OK</span>';
        } else
            $return_str .= '<span style="color:red">FAILED ('.
                           ((!empty( $action_result ) && !empty( $action_result['buffer'] ))?
                               $action_result['buffer']:
                               $this->_pt( 'N/A' )).')</span>';
        $return_str .= '</p>';

        if( !empty( $controllers_arr ) )
        {
            $return_str .= '<p>Checking controllers:<br/>';
            foreach( $controllers_arr as $controller_info )
            {
                if( empty( $controller_info['file'] ) )
                    continue;

                $controller_name = $controller_info['file'];

                $return_str .= 'Controller '.$controller_name.'... ';
                if( ($action_result = $this->check_plugin_integrity( $plugin_name, [ 'controller' => $controller_name ] ))
                 && !empty( $action_result['ajax_result'] )
                 && empty( $action_result['ajax_result']['has_error'] ) )
                {
                    if( !empty( $action_result['ajax_result']['instance_details'] )
                     && is_array( $action_result['ajax_result']['instance_details'] ) )
                    {
                        $instance_details = $action_result['ajax_result']['instance_details'];

                        $return_str .= ' '.$instance_details['instance_id'].' ';
                    }

                    $return_str .= '<span style="color:green">OK</span>';
                } else
                    $return_str .= '<span style="color:red">FAILED ('.
                                   ((!empty( $action_result ) && !empty( $action_result['buffer'] ))?
                                       $action_result['buffer']:
                                       $this->_pt( 'N/A' )).')</span>';

                $return_str .= '<br/>';
            }
            $return_str .= '</p>';
        }

        if( !empty( $actions_arr ) )
        {
            $return_str .= '<p>Checking actions:<br/>';
            foreach( $actions_arr as $action_info )
            {
                if( empty( $action_info['file'] ) )
                    continue;

                $action_name = $action_info['file'];
                $action_dir = (!empty( $action_info['dir'] )?$action_info['dir']:'');

                $check_params = [
                    'action' => $action_name,
                    'dir' => $action_dir,
                ];

                $return_str .= 'Action '.($action_dir?$action_dir.'/':'').$action_name.'... ';
                if( ($action_result = $this->check_plugin_integrity( $plugin_name, $check_params ))
                && !empty( $action_result['ajax_result'] )
                && empty( $action_result['ajax_result']['has_error'] ) )
                {
                    if( !empty( $action_result['ajax_result']['instance_details'] )
                    && is_array( $action_result['ajax_result']['instance_details'] ) )
                    {
                        $instance_details = $action_result['ajax_result']['instance_details'];

                        $return_str .= ' '.$instance_details['instance_id'].' ';
                    }

                    $return_str .= '<span style="color:green">OK</span>';
                } else
                    $return_str .= '<span style="color:red">FAILED ('.
                                   ((!empty( $action_result ) && !empty( $action_result['buffer'] ))?
                                       $action_result['buffer']:
                                       $this->_pt( 'N/A' )).')</span>';

                $return_str .= '<br/>';
            }
            $return_str .= '</p>';
        }

        if( !empty( $contracts_arr ) )
        {
            $return_str .= '<p>Checking contracts:<br/>';
            foreach( $contracts_arr as $contract_info )
            {
                if( empty( $contract_info['file'] ) )
                    continue;

                $contract_name = $contract_info['file'];
                $contract_dir = (!empty( $contract_info['dir'] )?$contract_info['dir']:'');

                $check_params = [
                    'contract' => $contract_name,
                    'dir' => $contract_dir,
                ];

                $return_str .= 'Contract '.($contract_dir?$contract_dir.'/':'').$contract_name.'... ';
                if( ($action_result = $this->check_plugin_integrity( $plugin_name, $check_params ))
                && !empty( $action_result['ajax_result'] )
                && empty( $action_result['ajax_result']['has_error'] ) )
                {
                    if( !empty( $action_result['ajax_result']['instance_details'] )
                    && is_array( $action_result['ajax_result']['instance_details'] ) )
                    {
                        $instance_details = $action_result['ajax_result']['instance_details'];

                        $return_str .= ' '.$instance_details['instance_id'].' ';
                    }

                    $return_str .= '<span style="color:green">OK</span>';
                } else
                    $return_str .= '<span style="color:red">FAILED ('.
                                   ((!empty( $action_result ) && !empty( $action_result['buffer'] ))?
                                       $action_result['buffer']:
                                       $this->_pt( 'N/A' )).')</span>';

                $return_str .= '<br/>';
            }
            $return_str .= '</p>';
        }

        if( !empty( $models_arr ) )
        {
            $return_str .= '<p>Checking models:<br/>';
            foreach( $models_arr as $model_info )
            {
                if( empty( $model_info['file'] ) )
                    continue;

                $model_name = $model_info['file'];

                $return_str .= 'Model '.$model_name.'... ';
                if( ($action_result = $this->check_plugin_integrity( $plugin_name, [ 'model' => $model_name ] ))
                 && !empty( $action_result['ajax_result'] )
                 && empty( $action_result['ajax_result']['has_error'] ) )
                {
                    if( !empty( $action_result['ajax_result']['instance_details'] )
                     && is_array( $action_result['ajax_result']['instance_details'] ) )
                    {
                        $instance_details = $action_result['ajax_result']['instance_details'];

                        $return_str .= ' '.$instance_details['instance_id'].' ';
                    }

                    $return_str .= '<span style="color:green">OK</span>';
                } else
                    $return_str .= '<span style="color:red">FAILED ('.
                                   ((!empty( $action_result ) && !empty( $action_result['buffer'] ))?
                                       $action_result['buffer']:
                                       $this->_pt( 'N/A' )).')</span>';

                $return_str .= '<br/>';
            }
            $return_str .= '</p>';
        }

        return $return_str;
    }

    /**
     * @param string $plugin_name
     * @param false|array $params
     *
     * @return array|bool
     */
    private function check_plugin_integrity( $plugin_name, $params = false )
    {
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['model'] ) )
            $params['model'] = false;
        if( empty( $params['controller'] ) )
            $params['controller'] = false;
        if( empty( $params['action'] ) )
            $params['action'] = false;
        if( empty( $params['contract'] ) )
            $params['contract'] = false;
        if( empty( $params['dir'] ) )
            $params['dir'] = false;

        $script_params = [];
        $script_params['p'] = $plugin_name;
        if( !empty( $params['controller'] ) )
            $script_params['c'] = $params['controller'];
        if( !empty( $params['model'] ) )
            $script_params['m'] = $params['model'];
        if( !empty( $params['action'] ) )
            $script_params['a'] = $params['action'];
        if( !empty( $params['contract'] ) )
            $script_params['co'] = $params['contract'];
        if( !empty( $params['dir'] ) )
            $script_params['dir'] = $params['dir'];

        if( !($bg_action_result = PHS_Bg_jobs::run( [
                                                      'plugin' => 'admin',
                                                      'controller' => 'index_bg',
                                                      'action' => 'plugins_integrity_bg',
                                                    ],
                                                    $script_params,
                                                    [
                                                      'return_buffer' => true,
                                                    ]
            )) )
            return false;

        return self::validate_array( $bg_action_result, self::default_action_result() );
    }
}
