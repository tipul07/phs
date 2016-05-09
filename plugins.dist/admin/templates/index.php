<?php
    /** @var \phs\system\core\views\PHS_View $this */

    if( !($current_user = $this->context_var( 'current_user' )) )
        $current_user = array( 'nick' => $this::_t( 'N/A' ) );
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:600px;max-width:800px;margin: 0 auto;">

    <div class="form_container responsive" style="width: 450px;">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'Welcome...' )?></h3>
        </section>
        <div class="clearfix"></div>
        
        <fieldset>
        <p><?php echo $this->_pt( 'You are in admin section of %s site.', PHS_SITE_NAME )?></p>
        <p><?php echo $this->_pt( 'Currently logged in %s - %s.', $current_user['nick'], $this->context_var( 'user_level' ) )?></p>
        </fieldset>

    </div>
</div>
<div class="clearfix"></div>
