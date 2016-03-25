<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
?>
<!-- BEGIN: main -->
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:600px;max-width:800px;margin: 0 auto;">

    <div class="form_container responsive" style="width: 450px;">

        <section class="heading-bordered">
            <h3><?php echo $this::_t( 'Confirmation action failed' )?></h3>
        </section>

        <fieldset>
            <?php
            if( !($nick = $this->context_var( 'nick' )) )
                $nick = $this::_t( 'there' );
            ?>
            <p><?php echo $this::_t( 'Hello <strong>%s</strong>.', $nick );?></p>
            <p><?php echo $this::_t( 'Confirmation action failed.' )?></p>
        </fieldset>

        <fieldset>
            <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'nick' => $this->context_var( 'nick' ) ) )?>"><?php echo $this::_t( 'Go to login page' )?></a>
        </fieldset>

    </div>
</div>

<div class="clearfix"></div>
<p>&nbsp;</p>
<!-- END: main -->
