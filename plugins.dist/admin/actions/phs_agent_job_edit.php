<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Agent;
use \phs\PHS_Bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_Instantiable;
use \phs\libraries\PHS_Plugin;

class PHS_Action_Agent_job_edit extends PHS_Action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Edit Agent Job' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to manage agent jobs.' ) );
            return self::default_action_result();
        }

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($agent_jobs_model = PHS::load_model( 'agent_jobs' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load agent jobs model.' ) );
            return self::default_action_result();
        }

        $aid = PHS_Params::_gp( 'aid', PHS_Params::T_INT );
        $back_page = PHS_Params::_gp( 'back_page', PHS_Params::T_ASIS );

        if( empty( $aid )
         or !($agent_job_arr = $agent_jobs_model->get_details( $aid )) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'Invalid agent job...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'unknown_agent_job' => 1
            );

            if( empty( $back_page ) )
                $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'agent_jobs_list' ) );
            else
                $back_page = from_safe_url( $back_page );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if( PHS_Params::_g( 'changes_saved', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Agent job details saved.' ) );

        if( !($agent_routes = PHS_Agent::get_agent_routes()) )
            $agent_routes = array();

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $title = PHS_Params::_p( 'title', PHS_Params::T_NOHTML );
        $plugin = PHS_Params::_p( 'plugin', PHS_Params::T_NOHTML );
        $controller = PHS_Params::_p( 'controller', PHS_Params::T_NOHTML );
        $action = PHS_Params::_p( 'action', PHS_Params::T_NOHTML );
        $handler = PHS_Params::_p( 'handler', PHS_Params::T_NOHTML );
        $params = PHS_Params::_p( 'params', PHS_Params::T_ASIS );
        $timed_seconds = PHS_Params::_p( 'timed_seconds', PHS_Params::T_INT );
        $run_async = PHS_Params::_p( 'run_async', PHS_Params::T_BOOL );

        $do_submit = PHS_Params::_p( 'do_submit' );

        if( empty( $foobar ) )
        {
            if( ($route_parts = PHS::parse_route( $agent_job_arr['route'] )) )
            {
                $plugin = $route_parts['plugin'];
                $controller = $route_parts['controller'];
                $action = $route_parts['action'];
            }

            $title = $agent_job_arr['title'];
            $handler = $agent_job_arr['handler'];
            $params = (!empty( $agent_job_arr['params'] )?$agent_job_arr['params']:'');
            $timed_seconds = $agent_job_arr['timed_seconds'];
            $run_async = (!empty( $agent_job_arr['run_async'] )?true:false);
        }

        if( !empty( $do_submit ) )
        {
            $job_check_params = array();
            $job_check_params['handler'] = $handler;
            $job_check_params['id'] = array( 'check' => '!=', 'value' => $agent_job_arr['id'] );

            if( empty( $handler ) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Please provide a handler for this agent job.' ) );

            elseif( ($existing_job = $agent_jobs_model->get_details_fields( $job_check_params )) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Agent job handler already exists. Pick another one.' ) );

            elseif( !empty( $params )
                and !($params_arr = @json_decode( $params, true )) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Job parameters doesn\'t look like a valid JSON string or JSON is empty.' ) );

            elseif( empty( $plugin ) or empty( $controller ) or empty( $action )
             or empty( $agent_routes ) or !is_array( $agent_routes )
             or empty( $agent_routes[$plugin] )
             or empty( $agent_routes[$plugin]['controllers'] )
             or empty( $agent_routes[$plugin]['actions'] )
             or empty( $agent_routes[$plugin]['controllers'][$controller] )
             or empty( $agent_routes[$plugin]['actions'][$action] ) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Invalid plugin, controller or action selected. Please select valid values from drop down list.' ) );

            elseif( !($job_route = PHS::route_from_parts( array( 'p' => $plugin, 'c' => $controller, 'a' => $action ) )) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t compose a valid route using provided plugin, controller or action. Please select valid values from drop down list.' ) );

            elseif( !($timed_seconds = intval( $timed_seconds )) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Please provide a valid running interval in seconds.' ) );

            else
            {
                if( empty( $params_arr ) or !is_array( $params_arr ) )
                    $params_arr = array();

                $job_extra_arr = array();
                $job_extra_arr['title'] = $title;
                $job_extra_arr['plugin'] = $agent_job_arr['plugin'];
                $job_extra_arr['run_async'] = (!empty( $run_async )?1:0);

                if( ($new_agent_job = PHS_Agent::add_job( $handler, $job_route, $timed_seconds, $params_arr, $job_extra_arr )) )
                {
                    PHS_Notifications::add_success_notice( $this->_pt( 'Agent job details saved...' ) );

                    $action_result = self::default_action_result();

                    $url_params = array();
                    $url_params['changes_saved'] = 1;
                    $url_params['aid'] = $agent_job_arr['id'];
                    if( !empty( $back_page ) )
                        $url_params['back_page'] = $back_page;

                    $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'agent_job_edit' ), $url_params );

                    return $action_result;
                } else
                {
                    if( PHS::st_has_error() )
                        PHS_Notifications::add_error_notice( PHS::st_get_error_message() );
                    else
                        PHS_Notifications::add_error_notice( $this->_pt( 'Error saving details to database. Please try again.' ) );
                }
            }
        }

        $data = array(
            'back_page' => $back_page,
            'aid' => $agent_job_arr['id'],
            'foobar' => $foobar,
            'title' => $title,
            'plugin' => $plugin,
            'controller' => $controller,
            'action' => $action,
            'handler' => $handler,
            'params' => $params,
            'timed_seconds' => $timed_seconds,
            'run_async' => (!empty( $run_async )?'checked="checked"':''),

            'agent_routes' => $agent_routes,
        );

        return $this->quick_render_template( 'agent_job_edit', $data );
    }
}
