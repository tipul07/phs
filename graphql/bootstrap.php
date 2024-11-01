<?php

use phs\PHS;
use phs\libraries\PHS_Instantiable;
use phs\system\core\models\PHS_Model_Plugins;

if (!defined('PHS_PATH')) {
    exit;
}

include_once PHS_GRAPHQL_LIBRARIES_DIR.'webonyx/vendor/autoload.php';
include_once PHS_LIBRARIES_DIR.'phs_graphql_type.php';

/** @var PHS_Model_Plugins $plugins_model */
if (!($plugins_model = PHS_Model_Plugins::get_instance())) {
    echo PHS::_t('ERROR Instantiating plugins model.')."\n";
    if (PHS::st_debugging_mode()) {
        echo PHS::var_dump(PHS::st_get_error(), ['max_level' => 5]);
    }
    exit;
}

if ((PHS::is_multi_tenant() && !($all_plugins = $plugins_model->get_all_plugins()))
    || (!PHS::is_multi_tenant() && !($all_plugins = $plugins_model->get_all_active_plugins()))) {
    $all_plugins = [];
}

if (!empty($all_plugins)) {
    $bootstrap_scripts = [];

    $bootstrap_scripts_numbers = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90];

    // Make sure we have the right order for keys in array

    foreach ($bootstrap_scripts_numbers as $bootstrap_scripts_number_i) {
        $bootstrap_scripts[$bootstrap_scripts_number_i] = [];
    }

    foreach ($all_plugins as $plugin_name => $plugin_db_arr) {
        foreach ($bootstrap_scripts_numbers as $bootstrap_scripts_number_i) {
            $bootstrap_script = PHS_PLUGINS_DIR.$plugin_name.'/'.PHS_Instantiable::GRAPHQL_DIR.'/phs_bootstrap_'.$bootstrap_scripts_number_i.'.php';
            if (@file_exists($bootstrap_script)) {
                $bootstrap_scripts[$bootstrap_scripts_number_i][] = $bootstrap_script;
            }
        }
    }

    foreach ($bootstrap_scripts as $bootstrap_scripts_number_i => $bootstrap_scripts_arr) {
        if (empty($bootstrap_scripts_arr)) {
            continue;
        }

        foreach ($bootstrap_scripts_arr as $bootstrap_script) {
            include_once $bootstrap_script;
        }
    }
}
