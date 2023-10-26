<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\system\core\views\PHS_View;

if (!@function_exists('phs_base_ractive_theme_bootstrap')) {
    function phs_base_ractive_theme_bootstrap(PHS_View $fthis) : void
    {
        static $theme_bootstraped = false;

        if ($theme_bootstraped) {
            return;
        }
        ?>
        <link href="<?php echo $fthis->get_resource_url('ractive/css/ractive_style.css'); ?>" rel="stylesheet" type="text/css" />
        <script src="<?php echo $fthis->get_resource_url('ractive/js/'.(PHS::st_debugging_mode() ? 'ractive.js' : 'ractive.min.js')); ?>"></script>
        <script src="<?php echo $fthis->get_resource_url('ractive/js/ractive_base.js.php'); ?>"></script>
        <?php
        echo $fthis->sub_view('ractive/phs_ractive_main');

        $theme_bootstraped = true;
    }
}

phs_base_ractive_theme_bootstrap($this);
