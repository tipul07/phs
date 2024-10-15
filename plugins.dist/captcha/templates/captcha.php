<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

$hook_args = $this->view_var('hook_args') ?: [];
$settings_arr = $this->view_var('settings_arr') ?: [];

$img_width = $hook_args['default_width'] ?? $settings_arr['default_width'] ?? 200;
$img_height = $hook_args['default_height'] ?? $settings_arr['default_height'] ?? 50;

$url_params = [];
if ((int)$img_width !== (int)$settings_arr['default_width']) {
    $url_params['w'] = $img_width;
}
if ((int)$img_height !== (int)$settings_arr['default_height']) {
    $url_params['h'] = $img_height;
}

?><img src="<?php echo PHS::url(['p' => 'captcha'], $url_params); ?>" style="width: <?php echo $img_width; ?>px;height: <?php echo $img_height; ?>px;<?php echo !empty($hook_args['extra_img_style']) ? $hook_args['extra_img_style'] : ''; ?>" <?php echo !empty($hook_args['extra_img_attrs']) ? $hook_args['extra_img_attrs'] : ''; ?> class="captcha-img" />
