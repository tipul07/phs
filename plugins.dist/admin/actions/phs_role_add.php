<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_Role_add extends PHS_Action
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
        PHS::page_settings( 'page_title', $this->_pt( 'Add Role' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ROLES ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to manage roles.' ) );
            return self::default_action_result();
        }

        /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
        if( !($roles_model = PHS::load_model( 'roles' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load roles model.' ) );
            return self::default_action_result();
        }

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if( !($plugins_model = PHS::load_model( 'plugins' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load plugins model.' ) );
            return self::default_action_result();
        }

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $name = PHS_Params::_p( 'name', PHS_Params::T_NOHTML );
        $slug = PHS_Params::_p( 'slug', PHS_Params::T_NOHTML );
        $description = PHS_Params::_p( 'description', PHS_Params::T_NOHTML );
        $ru_slugs = PHS_Params::_p( 'ru_slugs', PHS_Params::T_ARRAY, array( 'type' => PHS_Params::T_NOHTML, 'trim_before' => true ) );

        $do_submit = PHS_Params::_p( 'do_submit' );

        if( empty( $ru_slugs ) or !is_array( $ru_slugs ) )
            $ru_slugs = array();

        if( !empty( $do_submit ) )
        {
            $insert_arr = array();
            $insert_arr['name'] = $name;
            $insert_arr['description'] = $description;
            $insert_arr['slug'] = $slug;
            $insert_arr['predefined'] = 0;

            $insert_params_arr = array();
            $insert_params_arr['fields'] = $insert_arr;
            $insert_params_arr['{role_units}'] = $ru_slugs;
            $insert_params_arr['{role_units_params}'] = array( 'append_role_units' => false );

            if( ($new_role = $roles_model->insert( $insert_params_arr )) )
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'Role details saved...' ) );

                $action_result = self::default_action_result();

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'roles_list' ), array( 'role_added' => 1 ) );

                return $action_result;
            } else
            {
                if( $roles_model->has_error() )
                    PHS_Notifications::add_error_notice( $roles_model->get_error_message() );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error saving details to database. Please try again.' ) );
            }
        }

        $data = array(
            'name' => $name,
            'description' => $description,

            'ru_slugs' => $ru_slugs,
            'role_units_by_slug' => $roles_model->get_all_role_units_by_slug(),
            'plugins_model' => $plugins_model,
        );

        return $this->quick_render_template( 'role_add', $data );
    }
}
