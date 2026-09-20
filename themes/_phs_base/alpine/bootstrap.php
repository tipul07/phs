<?php
/** @var PHS_View $this */

use phs\system\core\views\PHS_View;

if (!@function_exists('phs_base_alpine_theme_bootstrap')) {
    function phs_base_alpine_theme_bootstrap(PHS_View $fthis) : void
    {
        static $theme_bootstraped = false;

        if ($theme_bootstraped) {
            return;
        }
        ?>
<script src="<?php echo $fthis->get_resource_url('alpine/js/alpine.min.js'); ?>" defer></script>
<script>
const PHS_Alpine = {
    oninit: function(data_name, callback) {
        document.addEventListener('alpine:init', () => {
            Alpine.data(data_name, callback);
        })
    }
}
</script>
<?php

        $theme_bootstraped = true;
    }
}

phs_base_alpine_theme_bootstrap($this);
