<?php
    /** @var \phs\system\core\views\PHS_View $this */

use \phs\PHS;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_Hooks;

    /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
    if( !($admin_plugin = PHS::load_plugin( 'admin' )) )
        return $this->_pt( 'Error loading required resources.' );

    $cuser_arr = PHS::current_user();

    $can_list_plugins = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_LIST_PLUGINS );
    $can_manage_plugins = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_MANAGE_PLUGINS );
    $can_list_agent_jobs = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_LIST_AGENT_JOBS );
    $can_manage_agent_jobs = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS );
    $can_list_api_keys = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_LIST_API_KEYS );
    $can_manage_api_keys = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_MANAGE_API_KEYS );
    $can_list_roles = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_LIST_ROLES );
    $can_manage_roles = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_MANAGE_ROLES );
    $can_list_accounts = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_LIST_ACCOUNTS );
    $can_manage_accounts = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_MANAGE_ACCOUNTS );
    $can_view_logs = PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_VIEW_LOGS );

    if( !$can_list_plugins && !$can_manage_plugins
     && !$can_list_api_keys && !$can_manage_api_keys
     && !$can_list_agent_jobs && !$can_manage_agent_jobs
     && !$can_list_roles && !$can_manage_roles
     && !$can_list_accounts && !$can_manage_accounts )
        return '';

if( $can_list_accounts || $can_manage_accounts )
{
    ?>
    <li><?php echo $this::_t( 'Users Management' ) ?>
        <ul>
            <?php
            if( $can_manage_roles )
            {
                ?>
                <li><a href="<?php echo PHS::url( [ 'a' => 'user_add', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'Add User' ) ?></a></li>
                <?php
            }
            ?>
            <li><a href="<?php echo PHS::url( [ 'a' => 'users_list', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'Manage Users' ) ?></a></li>
        </ul>
    </li>
    <?php
}

    if( ($hook_args = PHS::trigger_hooks( $admin_plugin::H_ADMIN_LEFT_MENU_ADMIN_AFTER_USERS, PHS_Hooks::default_buffer_hook_args() ))
     && is_array( $hook_args )
     && !empty( $hook_args['buffer'] ) )
    {
        echo $hook_args['buffer'];
    }

if( $can_list_roles || $can_manage_roles )
{
    ?>
    <li><?php echo $this::_t( 'Roles Management' ) ?>
        <ul>
            <?php
            if( $can_manage_roles )
            {
                ?>
                <li><a href="<?php echo PHS::url( [ 'a' => 'role_add', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'Add Role' ) ?></a></li>
                <?php
            }
            ?>
            <li><a href="<?php echo PHS::url( [ 'a' => 'roles_list', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'Manage Roles' ) ?></a></li>
        </ul>
    </li>
    <?php
}
if( $can_list_plugins || $can_manage_plugins )
{
    ?>
    <li><?php echo $this::_t( 'Plugins Management' ) ?>
        <ul>
            <li><a href="<?php echo PHS::url( [ 'a' => 'plugins_list', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'List Plugins' ) ?></a></li>
            <li><a href="<?php echo PHS::url( [ 'a' => 'plugins_integrity', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'Plugins\' Integrity' ) ?></a></li>
        </ul>
    </li>
    <?php
}
if( $can_list_agent_jobs || $can_manage_agent_jobs )
{
    ?>
    <li><?php echo $this::_t( 'Agent Script' ) ?>
        <ul>
            <li><a href="<?php echo PHS::url( [ 'a' => 'agent_jobs_list', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'List agent jobs' ) ?></a></li>
        </ul>
    </li>
    <?php
}
if( $can_list_api_keys || $can_manage_api_keys )
{
    ?>
    <li><?php echo $this::_t( 'API Keys' ) ?>
        <ul>
            <?php
            if( $can_manage_api_keys )
            {
                ?>
                <li><a href="<?php echo PHS::url( [ 'a' => 'api_key_add', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'Add API key' ) ?></a></li>
                <?php
            }
            ?>
            <li><a href="<?php echo PHS::url( [ 'a' => 'api_keys_list', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'List API keys' ) ?></a></li>
        </ul>
    </li>
    <?php
}
if( $can_view_logs )
{
    ?>
    <li><?php echo $this::_t( 'System Logs' ) ?>
        <ul>
            <li><a href="<?php echo PHS::url( [ 'a' => 'system_logs', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'View logs' ) ?></a></li>
        </ul>
    </li>
    <?php
}

/** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
if( ($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
 && $accounts_model->acc_is_developer( $cuser_arr ) )
{
    ?>
    <li><?php echo $this::_t( 'Framework Updates' ) ?>
        <ul>
            <li><a href="<?php echo PHS::url( [ 'a' => 'framework_updates', 'p' => 'admin' ] ) ?>"><?php echo $this::_t( 'Update PHS structure' ) ?></a></li>
        </ul>
    </li>
    <?php
}
