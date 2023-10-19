<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\plugins\accounts\models\PHS_Model_Accounts;

/** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
if (!($admin_plugin = PHS_Plugin_Admin::get_instance())) {
    return $this->_pt('Error loading required resources.');
}

$cuser_arr = PHS::current_user();

$can_list_plugins = $admin_plugin->can_admin_list_plugins();
$can_manage_plugins = $admin_plugin->can_admin_manage_plugins();
$can_list_agent_jobs = $admin_plugin->can_admin_list_agent_jobs();
$can_manage_agent_jobs = $admin_plugin->can_admin_manage_agent_jobs();
$can_list_api_keys = $admin_plugin->can_admin_list_api_keys();
$can_manage_api_keys = $admin_plugin->can_admin_manage_api_keys();
$can_list_roles = $admin_plugin->can_admin_list_roles();
$can_manage_roles = $admin_plugin->can_admin_manage_roles();
$can_list_accounts = $admin_plugin->can_admin_list_accounts();
$can_manage_accounts = $admin_plugin->can_admin_manage_accounts();
$can_view_logs = $admin_plugin->can_admin_view_logs();
$can_import_accounts = $admin_plugin->can_admin_import_accounts();

if (!$can_list_plugins && !$can_manage_plugins
 && !$can_list_api_keys && !$can_manage_api_keys
 && !$can_list_agent_jobs && !$can_manage_agent_jobs
 && !$can_list_roles && !$can_manage_roles
 && !$can_list_accounts && !$can_manage_accounts
 && !$can_import_accounts) {
    return '';
}

if ($can_list_accounts || $can_manage_accounts || $can_import_accounts) {
    ?>
    <li><?php echo $this::_t('Accounts Management'); ?>
        <ul>
            <?php
            if ($can_manage_accounts) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'add', 'ad' => 'users', 'p' => 'admin']); ?>"
                    ><?php echo $this::_t('Add Account'); ?></a></li>
                <?php
            }
            if ($can_list_accounts) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'list', 'ad' => 'users', 'p' => 'admin']); ?>"
                    ><?php echo $this::_t('Manage Accounts'); ?></a></li>
                <?php
            }
            if ($can_import_accounts) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'import', 'ad' => 'users', 'p' => 'admin']); ?>"
                    ><?php echo $this::_t('Import Accounts'); ?></a></li>
                <?php
            }
    ?>
        </ul>
    </li>
    <?php
}

if (($hook_args = PHS::trigger_hooks($admin_plugin::H_ADMIN_LEFT_MENU_ADMIN_AFTER_USERS, PHS_Hooks::default_buffer_hook_args()))
 && is_array($hook_args)
 && !empty($hook_args['buffer'])) {
    echo $hook_args['buffer'];
}

if ($can_list_roles || $can_manage_roles) {
    ?>
    <li><?php echo $this::_t('Roles Management'); ?>
        <ul>
            <?php
            if ($can_manage_roles) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'role_add', 'p' => 'admin']); ?>"><?php echo $this::_t('Add Role'); ?></a></li>
                <?php
            }
    ?>
            <li><a href="<?php echo PHS::url(['a' => 'roles_list', 'p' => 'admin']); ?>"><?php echo $this::_t('Manage Roles'); ?></a></li>
        </ul>
    </li>
    <?php
}
if ($can_list_plugins || $can_manage_plugins) {
    ?>
    <li><?php echo $this::_t('Plugins Management'); ?>
        <ul>
            <li><a href="<?php echo PHS::url(['a' => 'plugins_list', 'p' => 'admin']); ?>"><?php echo $this::_t('List Plugins'); ?></a></li>
            <li><a href="<?php echo PHS::url(['a' => 'plugins_integrity', 'p' => 'admin']); ?>"><?php echo $this::_t('Plugins\' Integrity'); ?></a></li>
        </ul>
    </li>
    <?php
}
if ($can_list_agent_jobs || $can_manage_agent_jobs) {
    ?>
    <li><?php echo $this::_t('Agent Jobs'); ?>
        <ul>
            <li><a href="<?php echo PHS::url(['a' => 'agent_jobs_list', 'p' => 'admin']); ?>"><?php echo $this::_t('List agent jobs'); ?></a></li>
            <?php
            if ($admin_plugin->monitor_agent_jobs()) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'report', 'ad' => 'agent', 'p' => 'admin']); ?>"><?php echo $this::_t('Agent jobs report'); ?></a></li>
                <?php
            }
    ?>
        </ul>
    </li>
    <?php
}
if ($can_list_api_keys || $can_manage_api_keys) {
    ?>
    <li><?php echo $this::_t('API Keys'); ?>
        <ul>
            <?php
            if ($can_manage_api_keys) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'api_key_add', 'p' => 'admin']); ?>"><?php echo $this::_t('Add API key'); ?></a></li>
                <?php
            }
    ?>
            <li><a href="<?php echo PHS::url(['a' => 'api_keys_list', 'p' => 'admin']); ?>"><?php echo $this::_t('List API keys'); ?></a></li>
        </ul>
    </li>
    <?php
}
if ($can_view_logs) {
    ?>
    <li><?php echo $this::_t('System Logs'); ?>
        <ul>
            <li><a href="<?php echo PHS::url(['a' => 'system_logs', 'p' => 'admin']); ?>"><?php echo $this::_t('View logs'); ?></a></li>
        </ul>
    </li>
    <?php
}

/** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
if (($accounts_model = PHS_Model_Accounts::get_instance())
 && $accounts_model->acc_is_developer($cuser_arr)) {
    ?>
    <li><?php echo $this::_t('Framework Updates'); ?>
        <ul>
            <li><a href="<?php echo PHS::url(['a' => 'framework_updates', 'p' => 'admin']); ?>"><?php echo $this::_t('Update PHS structure'); ?></a></li>
        </ul>
    </li>
    <?php
}
