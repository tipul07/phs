<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Hooks;

?>
<div style="max-width:800px;margin: 0 auto;">

    <div class="form_container responsive">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt('Logout'); ?></h3>
        </section>

        <fieldset>
            <a href="<?php echo PHS::url(['p' => 'accounts', 'a' => 'login'], ['nick' => $this->view_var('nick')]); ?>"><?php echo $this->_pt('Go to login page'); ?></a>
        </fieldset>

    </div>
</div>
<div class="clearfix"></div>
