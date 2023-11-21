<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Utils;

?>
<div class="form_container" style="margin: 20px auto;">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Framework Updates'); ?></h3>
    </section>

    <p><?php
        echo $this->_pt('Update URL vailable for %s.',
            '<strong>'.PHS_Utils::parse_period(PHS_Maintenance::UPDATE_TOKEN_LIFETIME).'</strong>'
        ); ?></p>

    <p><?php echo $this->_pt('NOTE: Provided URL is forced to use HTTPS, if you don\'t have HTTPS enabled, change the link to use HTTP protocol.'); ?></p>

    <?php
    $update_link = PHS_Maintenance::get_framework_update_url_with_token();
?>
    <a href="<?php echo $update_link; ?>" target="update_win"><?php echo $update_link; ?></a>

</div>
