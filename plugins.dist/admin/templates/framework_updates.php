<?php
    /** @var \phs\system\core\views\PHS_View $this */

use \phs\PHS;
use \phs\libraries\PHS_Utils;

    if( !($current_user = $this->view_var( 'current_user' )) )
        $current_user = array( 'nick' => $this->_pt( 'N/A' ) );
?>
<div style="width:100%;">

    <div class="form_container responsive" style="margin: 20px auto;">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'Framework Updates' )?></h3>
        </section>

        <fieldset>
            <p><?php
            echo $this->_pt( 'Update URL vailable for %s.',
                             '<strong>'.PHS_Utils::parse_period( PHS::UPDATE_TOKEN_LIFETIME ).'</strong>'
            )?></p>

            <p><?php echo $this->_pt( 'NOTE: Provided URL is forced to use HTTPS, if you don\'t have HTTPS enabled, change the link to use HTTP protocol.' )?></p>

            <?php
            $update_link = PHS::get_framework_update_url_with_token();
            ?>
            <a href="<?php echo $update_link?>" target="update_win"><?php echo $update_link?></a>
        </fieldset>

    </div>
</div>
