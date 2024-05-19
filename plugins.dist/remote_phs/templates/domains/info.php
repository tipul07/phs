<?php
/** @noinspection ForgottenDebugOutputInspection */
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_ajax;
use phs\PHS_Scope;

$current_user = PHS::user_logged_in();

/** @var \phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains $domains_model */
if (!($domain_arr = $this->view_var('domain_arr'))
 || !($domains_model = $this->view_var('domains_model'))) {
    return $this->_pt('Could\'t loaded required resources for this view.');
}

if (!($do_ping = $this->view_var('do_ping'))) {
    $do_ping = false;
}
if (!($ping_result = $this->view_var('ping_result'))) {
    $ping_result = false;
}

$current_scope = PHS_Scope::current_scope();
?>

<div class="form_container clearfix" style="width: 750px;padding:10px;">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Domain details'); ?></h3>
    </section>

    <fieldset class="form-group">
        <label><?php echo $this->_pt('Remote Domain'); ?></label>
        <div class="lineform_line">
        <?php
            echo '<strong>'.$domain_arr['title'].'</strong> (#'.$domain_arr['id'].')<br/>'
                 .$this->_pt('Created').': '.date('Y-m-d H:i', parse_db_date($domain_arr['cdate']));

echo '<br/>'.$this->_pt('Last incoming').': ';
if (empty($domain_arr['last_incoming']) || empty_db_date($domain_arr['last_incoming'])) {
    echo $this->_pt('N/A');
} else {
    echo date('Y-m-d H:i', parse_db_date($domain_arr['last_incoming']));
}

echo '<br/>'.$this->_pt('Last outgoing').': ';
if (empty($domain_arr['last_outgoing']) || empty_db_date($domain_arr['last_outgoing'])) {
    echo $this->_pt('N/A');
} else {
    echo date('Y-m-d H:i', parse_db_date($domain_arr['last_outgoing']));
}

if (($status_title = $domains_model->valid_status($domain_arr['status']))) {
    echo '<br/>'.$this->_pt('Status').' - '.$status_title['title'].': '.date('Y-m-d H:i', parse_db_date($domain_arr['status_date']));
}
?>
        </div>
    </fieldset>

    <fieldset class="form-group">
        <label><?php echo $this->_pt('Last error'); ?></label>
        <div class="lineform_line"><?php
    echo !empty($domain_arr['error_log']) ? $domain_arr['error_log'] : $this->_pt('N/A');
?></div>
    </fieldset>

    <?php
if ($domains_model->is_connected($domain_arr)) {
    ?>
        <fieldset class="form-group">
            <button id="do_ping" name="do_ping"
                    onclick="phs_remote_domain_do_ping( '<?php echo $domain_arr['id']; ?>' )"
                    class="btn btn-primary submit-protection ignore_hidden_required">
                <i class="fa fa-volume-control-phone"></i>
                <?php echo $this->_pte('Ping'); ?>
            </button>
        </fieldset>
        <?php
}

if (!empty($do_ping)
 && !empty($ping_result) && is_array($ping_result)) {
    ?>
        <fieldset class="form-group">
            <label><?php echo $this->_pt('Ping Result'); ?></label>
            <div class="lineform_line"><?php
        if ($ping_result['timezone'] !== false) {
            echo $this->_pt('Remote domain timezone').': '.$ping_result['timezone'].'<br/>';
        }

    if (!empty($ping_result['has_error'])) {
        echo $this->_pt('Remote domain error').': '.(!empty($ping_result['error_msg']) ? $ping_result['error_msg'] : $this->_pt('N/A')).'<br/>';
    } else {
        ?>
                <pre><?php var_dump($ping_result['result']); ?></pre>
                <?php
    }
    ?></div>
        </fieldset>
        <?php
}
?>

</div>

<script type="text/javascript">
$(document).ready(function(){
    hide_submit_protection();
    phs_refresh_input_skins();
});

function phs_remote_domain_do_ping( id )
{
    show_submit_protection( "<?php echo $this::_e($this->_pt('Pinging domain...')); ?>" );

    PHS_JSEN.createAjaxDialog( {
        width: 800,
        height: 600,
        suffix: "phs_info_remote_domain",
        resizable: true,
        close_outside_click: false,

        title: "<?php echo $this::_e($this->_pt('Remote Domain Details')); ?>",
        method: "GET",
        url: "<?php echo PHS_ajax::url(['p' => 'remote_phs', 'a' => 'info_ajax', 'ad' => 'domains']); ?>",
        url_data: { domain_id: id, do_ping: 1 },

        onsuccess: function() {
            hide_submit_protection();
        },
        onfailed: function() {
            hide_submit_protection();
        }
    });
}
</script>
