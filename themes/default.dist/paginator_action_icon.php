<?php
use phs\system\core\views\PHS_View;

/** @var PHS_View $this */
if (!($action = $this->view_var('action'))
    || !is_array($action)) {
    echo $this::_t('Invalid action icon.');

    return '';
}

$render_params = $this->view_var('render_params') ?? [];

$record_arr = $render_params['record'] ?? [];
if (!$record_arr || !is_array($record_arr)) {
    return '';
}

$redirect_url = null;
$javascript_functionality = null;
if (($redirect_callback = $action['callbacks']['redirect_url'] ?? null)) {
    $redirect_url = $redirect_callback($record_arr);
} elseif (($javascript_callback = $action['callbacks']['javascript_callback'] ?? null)) {
    $javascript_functionality = $javascript_callback($record_arr);
}

$tooltip = $action['display_tooltip'] ?? '';
$icon = $action['display_icon'] ?? '';

if ($tooltip) {
    $tooltip = ' title="'.$tooltip.'"';
}
?>
<a <?php
if ($redirect_url) {
    ?>href="<?php echo $redirect_url; ?>" onclick="this.blur()"<?php
} elseif ($javascript_functionality) {
    ?>href="javascript:void(0)" onclick="this.blur();<?php echo $javascript_functionality; ?>"<?php
} else {
    ?>href="javascript:void(0)" onclick="this.blur();phs_paginator_default_action('<?php echo $action['action'] ?? ''; ?>', '<?php echo $record_arr['id'] ?? ''; ?>')"<?php
}
?>><?php
if ($icon) {
    ?><i class="fa <?php echo $icon; ?> action-icons"<?php echo $tooltip; ?>></i><?php
} else {
    ?><span<?php echo $tooltip; ?>><?php echo $tooltip; ?></span><?php
}
?></a>
<?php
