<?php

if (!defined('PHS_VERSION')
    || !defined('PHS_INSTALLING_FLOW') || !constant('PHS_INSTALLING_FLOW')) {
    exit;
}

use phs\PHS;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Plugin;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\models\PHS_Model_Migrations;

/** @var PHS_Model_Plugins $plugins_model */
/** @var PHS_Model_Migrations $migrations_model */
if (!($plugins_model = PHS_Model_Plugins::get_instance())
    || !($migrations_model = PHS_Model_Migrations::get_instance())) {
    PHS::st_set_error(-1, PHS::_t('Error instantiating required core models.'));

    return PHS::st_get_error();
}

PHS_Maintenance::lock_db_structure_read();

if (!$plugins_model->check_installation()) {
    PHS_Maintenance::unlock_db_structure_read();

    return PHS::arr_set_error(-1,
        PHS::_t('Error while checking plugins model installation: %s',
            $plugins_model->get_simple_error_message(PHS::_t('Unknown error.'))));
}

if (!$migrations_model->check_installation()) {
    PHS_Maintenance::unlock_db_structure_read();

    return PHS::arr_set_error(-1,
        PHS::_t('Error while checking migrations model installation: %s',
            $migrations_model->get_simple_error_message(PHS::_t('Unknown error.'))));
}

PHS_Maintenance::unlock_db_structure_read();
$migrations_model->migration_model_is_installed(true);
PHS_Maintenance::lock_db_structure_read();

if (($core_models = PHS::get_core_models())) {
    foreach ($core_models as $core_model) {
        /** @var PHS_Model $model_obj */
        if (($model_obj = PHS::load_model($core_model))) {
            $model_obj->check_installation();
        } else {
            PHS::st_set_error_if_not_set(-1, PHS::_t('Error instantiating core model [%s].', $core_model));

            PHS_Maintenance::unlock_db_structure_read();

            return PHS::st_get_error();
        }
    }
}

if (($plugins_arr = $plugins_model->cache_all_dir_details()) === null) {
    PHS::st_copy_or_set_error($plugins_model, -1, PHS::_t('Error obtaining plugins list.'));

    PHS_Maintenance::unlock_db_structure_read();

    return PHS::st_get_error();
}

$priority_plugins = ['emails', 'sendgrid', 'accounts', 'messages', 'notifications', 'captcha', 'admin'];

$installing_plugins_arr = [];

foreach ($priority_plugins as $plugin_name) {
    if (!isset($plugins_arr[$plugin_name])) {
        continue;
    }

    $installing_plugins_arr[$plugin_name] = $plugins_arr[$plugin_name];
}

// Make sure distribution plugins get updated first

$dist_plugins = PHS::get_distribution_plugins();

foreach ($dist_plugins as $plugin_name) {
    if (isset($installing_plugins_arr[$plugin_name])
     || !isset($plugins_arr[$plugin_name])) {
        continue;
    }

    $installing_plugins_arr[$plugin_name] = $plugins_arr[$plugin_name];
}

foreach ($plugins_arr as $plugin_name => $plugin_obj) {
    if (isset($installing_plugins_arr[$plugin_name])) {
        continue;
    }

    $installing_plugins_arr[$plugin_name] = $plugin_obj;
}

if ( !($migrations_manager = migrations_manager()) ) {
    return PHS::arr_set_error(-1, PHS::_t('Error instantiating migrations manager.'));
}

if ( ($plugin_names = array_keys($installing_plugins_arr)) ) {
    if ( null === ($migrations_arr = $migrations_manager->register_migrations_for_plugins($plugin_names)) ) {
        return PHS::arr_set_error(-1,
            PHS::_t('Error registering migration scripts: %s',
                $migrations_manager->get_simple_error_message(PHS::_t('Unknown error.'))));
    }

    PHS_Maintenance::output(PHS::_t('Registered %s migration scripts from %s plugins.',
        $migrations_arr['scripts'] ?? 0, $migrations_arr['plugins'] ?? 0));
}

/**
 * @var string $plugin_name
 * @var PHS_Plugin $plugin_obj
 */
foreach ($installing_plugins_arr as $plugin_name => $plugin_obj) {
    if (!$plugin_obj->check_installation()) {
        PHS_Maintenance::unlock_db_structure_read();

        return $plugin_obj->get_error();
    }
}

PHS_Maintenance::unlock_db_structure_read();

return true;
