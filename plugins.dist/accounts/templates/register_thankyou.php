<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;

    if( !($no_nickname_only_email = $this->view_var( 'no_nickname_only_email' )) )
        $no_nickname_only_email = false;

    $nick = ($no_nickname_only_email?$this->view_var( 'email' ):$this->view_var( 'nick' ));
?>
<div style="max-width:800px;margin: 0 auto;">

    <div class="form_container responsive">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'Account registered' )?></h3>
        </section>

        <fieldset>
            <p><?php echo $this->_pt( 'Hello %s.', '<strong>'.$nick.'</strong>' );?></p>
            <p><?php echo $this->_pt( 'You successfully registered your account. However, before using your account you must activate it.' )?></p>
            <p><?php echo $this->_pt( 'We sent you an activation link at %s. Please check your email (also looking into spam folder) and click or access the activation link.',
                                      '<strong>'.$this->view_var( 'email' ).'</strong>' )?></p>
        </fieldset>

        <fieldset>
            <a href="<?php echo PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'nick' => $nick ) )?>"><?php echo $this->_pt( 'Go to login page' )?></a>
        </fieldset>

    </div>
</div>
