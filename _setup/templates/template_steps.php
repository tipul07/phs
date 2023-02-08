<?php
/** @var \phs\setup\libraries\PHS_Setup_view $this */

/** @var \phs\setup\libraries\PHS_Setup $setup_obj */
if (!($setup_obj = $this->get_context('phs_setup_obj'))) {
    echo 'Couldn\'t obtain setup instance...';
    exit;
}

?>
    <h1>Step <?php echo $setup_obj->current_step().' / '.$setup_obj->max_steps(); ?></h1>
    <?php

echo ' &raquo; ';
for ($view_steps = 1; $view_steps <= $setup_obj->max_steps(); $view_steps++) {
    if (!($step_obj = $setup_obj->get_step_instance($view_steps))) {
        continue;
    }

    if (!($step_details = $step_obj->step_details())) {
        $step_details = [];
    }

    if (!($step_passed = $step_obj->step_config_passed())) {
        $step_passed = false;
    }

    if ($view_steps > 1) {
        echo ' ... ';
    }

    if ($step_passed) {
        ?><a href="index.php?forced_step=<?php echo $view_steps; ?>"><?php
    }

    if (!empty($step_details)) {
        ?><span title="<?php echo form_str($step_details['title']); ?>"><?php
    }

    echo 'Step '.$view_steps;

    if (!empty($step_details)) {
        ?></span><?php
    }

    if ($step_passed) {
        ?></a><?php
    }
}

if ($setup_obj->all_steps_passed()) {
    ?>
        ... <a href="index.php">Completed!</a>
        <?php
}

echo '<br/>';

/** @var \phs\setup\libraries\PHS_Step $step_obj */
if (($step_obj = $this->get_context('step_instance'))
 && ($step_details = $step_obj->step_details())) {
    ?>
        <h3><?php echo $step_details['title']; ?></h3>
        <small><?php echo $step_details['description']; ?></small>
        <?php
}

echo $this->get_context('step_interface_buf');
