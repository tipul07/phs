<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\s2p_libraries\PHS_Plugin_S2p_libraries $s2p_libraries_plugin */
if( ($s2p_libraries_plugin = PHS::load_plugin( 's2p_libraries' ))
and $s2p_libraries_plugin->plugin_active() )
{
    $s2p_libraries_plugin->init_bbcode_extras();

    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_AFTER_ACTION_EXECUTE,
        // $hook_callback = null
        array( $s2p_libraries_plugin, 'trigger_after_action_execute' ),
        // $hook_extra_args = null
        PHS_Hooks::default_action_execute_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 0,
        )
    );
}
