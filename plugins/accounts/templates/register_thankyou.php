<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;
?>
<!-- BEGIN: main -->
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:600px;max-width:800px;margin: 0 auto;">

    <div class="form_container responsive" style="width: 450px;">

        <section class="heading-bordered">
            <h3><?php echo $this::_t( 'Account registered' )?></h3>
        </section>

        <fieldset>
            <p><?php echo $this::_t( 'Hello <strong>%s</strong>.', $this->context_var( 'nick' ) );?></p>
            <p><?php echo $this::_t( 'You successfully registered your account. Before using your account however you have to activate it.' )?></p>
            <p><?php echo $this::_t( 'We sent you an activation link at <strong>%s</strong>. Please check your email (also looking in spam folder) and click or access the activation link.', $this->context_var( 'email' ) )?></p>
        </fieldset>

        <fieldset>
            <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'nick' => $this->context_var( 'nick' ) ) )?>"><?php echo $this::_t( 'Go to login page' )?></a>
        </fieldset>

    </div>
</div>

<div class="clearfix"></div>
<p>&nbsp;</p>
<!-- END: main -->
