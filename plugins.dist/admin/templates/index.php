<?php
/** @var \phs\system\core\views\PHS_View $this */
if (!($current_user = $this->view_var('current_user'))) {
    $current_user = ['nick' => $this->_pt('N/A')];
}
?>
<div style="margin: 0 20px;">

    <div class="form_container responsive" style="margin: 20px auto;">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt('Welcome...'); ?></h3>
        </section>
        <div class="clearfix"></div>

        <fieldset>
        <p><?php echo $this->_pt('You are in admin section of %s site.', PHS_SITE_NAME); ?></p>
        <p><?php echo $this->_pt('Currently logged in %s - %s.', $current_user['nick'], $this->view_var('user_level')); ?></p>
        </fieldset>

    </div>
</div>
<div class="clearfix"></div>
