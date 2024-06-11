<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\plugins\accounts\models\PHS_Model_Accounts;

/** @var PHS_Plugin_Admin $admin_plugin */
if (!($admin_plugin = PHS_Plugin_Admin::get_instance())) {
    return $this->_pt('Error loading required resources.');
}

$is_multi_tenant = PHS::is_multi_tenant();

$can_list_plugins = $admin_plugin->can_admin_list_plugins();
$can_manage_plugins = $admin_plugin->can_admin_manage_plugins();
$can_list_agent_jobs = $admin_plugin->can_admin_list_agent_jobs();
$can_manage_agent_jobs = $admin_plugin->can_admin_manage_agent_jobs();
$can_list_api_keys = $admin_plugin->can_admin_list_api_keys();
$can_manage_api_keys = $admin_plugin->can_admin_manage_api_keys();
$can_view_api_monitoring_report = $admin_plugin->can_admin_view_api_monitoring_report();
$can_list_roles = $admin_plugin->can_admin_list_roles();
$can_manage_roles = $admin_plugin->can_admin_manage_roles();
$can_list_accounts = $admin_plugin->can_admin_list_accounts();
$can_manage_accounts = $admin_plugin->can_admin_manage_accounts();
$can_view_logs = $admin_plugin->can_admin_view_logs();
$can_import_accounts = $admin_plugin->can_admin_import_accounts();
$can_list_tenants = $admin_plugin->can_admin_list_tenants();
$can_manage_tenants = $admin_plugin->can_admin_manage_tenants();
$can_list_migrations = $admin_plugin->can_admin_list_migrations();
$can_manage_migrations = $admin_plugin->can_admin_manage_migrations();
$can_list_data_retention = $admin_plugin->can_admin_list_data_retention();
$can_manage_data_retention = $admin_plugin->can_admin_manage_data_retention();

if (!$can_list_plugins && !$can_manage_plugins
 && !$can_list_api_keys && !$can_manage_api_keys && !$can_view_api_monitoring_report
 && !$can_list_agent_jobs && !$can_manage_agent_jobs
 && !$can_list_roles && !$can_manage_roles
 && !$can_list_accounts && !$can_manage_accounts
 && !$can_list_tenants && !$can_manage_tenants
 && !$can_list_migrations && !$can_manage_migrations
 && !$can_list_data_retention && !$can_manage_data_retention
 && !$can_import_accounts) {
    return '';
}

if ($is_multi_tenant
    && ($can_list_tenants || $can_manage_tenants)) {
    ?><li><?php echo $this::_t('Platform Tenants'); ?>
        <ul>
            <?php
            if ($can_manage_tenants) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'add', 'ad' => 'tenants', 'p' => 'admin']); ?>"
                    ><?php echo $this::_t('Add Tenant'); ?></a></li>
                <?php
            }
    if ($can_list_tenants) {
        ?>
                <li><a href="<?php echo PHS::url(['a' => 'list', 'ad' => 'tenants', 'p' => 'admin']); ?>"
                    ><?php echo $this::_t('List Tenants'); ?></a></li>
                <?php
    }
    ?>
        </ul>
    </li><?php
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
            <li><a href="<?php echo PHS::url(['a' => 'list', 'ad' => 'plugins', 'p' => 'admin']); ?>"><?php echo $this::_t('List Plugins'); ?></a></li>
            <?php
            if (!$is_multi_tenant || $can_list_tenants) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'plugins', 'ad' => 'tenants', 'p' => 'admin']); ?>"><?php echo $this::_t('Tenant Plugin Management'); ?></a></li>
                <?php
            }
    ?>
            <li><a href="<?php echo PHS::url(['a' => 'plugins_integrity', 'p' => 'admin']); ?>"><?php echo $this::_t('Plugins\' Integrity'); ?></a></li>
        </ul>
    </li>
    <?php
    if ($can_list_migrations || $can_manage_migrations) {
        ?>
        <li><?php echo $this::_t('Plugins Migrations'); ?>
            <ul>
                <li><a href="<?php echo PHS::url(['a' => 'list', 'ad' => 'migrations', 'p' => 'admin']); ?>"><?php echo $this::_t('List Migrations'); ?></a></li>
            </ul>
        </li>
        <?php
    }
}
if ($can_list_agent_jobs || $can_manage_agent_jobs) {
    ?>
    <li><?php echo $this::_t('Agent Jobs'); ?>
        <ul>
            <li><a href="<?php echo PHS::url(['a' => 'list', 'ad' => 'agent', 'p' => 'admin']); ?>"><?php echo $this::_t('List Agent Jobs'); ?></a></li>
            <?php
            if ($admin_plugin->monitor_agent_jobs()) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'report', 'ad' => 'agent', 'p' => 'admin']); ?>"><?php echo $this::_t('Agent Jobs Report'); ?></a></li>
                <?php
            }
    ?>
        </ul>
    </li>
    <?php
}
if ($can_list_api_keys || $can_manage_api_keys || $can_view_api_monitoring_report) {
    ?>
    <li><?php echo $this::_t('API Settings'); ?>
        <ul>
            <?php
            if ($can_manage_api_keys) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'add', 'ad' => 'apikeys', 'p' => 'admin']); ?>"><?php echo $this::_t('Add API Key'); ?></a></li>
                <?php
            }
    if ($can_list_api_keys || $can_manage_api_keys) {
        ?>
                <li><a href="<?php echo PHS::url(['a' => 'list', 'ad' => 'apikeys', 'p' => 'admin']); ?>"><?php echo $this::_t('List API Keys'); ?></a></li>
                <?php
    }
    if ($can_view_api_monitoring_report) {
        ?>
                <li><a href="<?php echo PHS::url(['a' => 'api_report', 'p' => 'admin']); ?>"><?php echo $this::_t('API Monitoring Report'); ?></a></li>
                <?php
    }
    ?>
        </ul>
    </li>
    <?php
}
if ($can_view_logs) {
    ?>
    <li><?php echo $this::_t('System Logs'); ?>
        <ul>
            <li><a href="<?php echo PHS::url(['a' => 'system_logs', 'p' => 'admin']); ?>"><?php echo $this::_t('View Logs'); ?></a></li>
        </ul>
    </li>
    <?php
}
if ($can_list_data_retention || $can_manage_data_retention) {
    ?>
    <li><?php echo $this::_t('Data Retention'); ?>
        <ul>
            <?php
            if ($can_manage_api_keys) {
                ?>
                <li><a href="<?php echo PHS::url(['a' => 'add', 'ad' => 'retention', 'p' => 'admin']); ?>"
                    ><?php echo $this::_t('Add Data Retention Policy'); ?></a></li>
                <?php
            }
    ?>
            <li><a href="<?php echo PHS::url(['a' => 'list', 'ad' => 'retention', 'p' => 'admin']); ?>"
                ><?php echo $this::_t('List Data Retention Policies'); ?></a></li>
        </ul>
    </li>
    <?php
}

/** @var PHS_Model_Accounts $accounts_model */
if (($accounts_model = PHS_Model_Accounts::get_instance())
    && ($cuser_arr = PHS::user_logged_in())
    && $accounts_model->acc_is_developer($cuser_arr)) {
    ?>
    <li><?php echo $this::_t('Framework Updates'); ?>
        <ul>
            <li><a href="<?php echo PHS::url(['a' => 'framework_updates', 'p' => 'admin']); ?>"><?php echo $this::_t('Update PHS Structure'); ?></a></li>
        </ul>
    </li>
    <?php
}
