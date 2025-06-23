<?php
/** @var phs\system\core\views\PHS_View $this */

use phs\libraries\PHS_Utils;
use phs\system\core\events\accounts\PHS_Event_Accounts_info_template;

/** @var phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
if (!($accounts_model = $this->view_var('accounts_model'))) {
    return $this->_pt('Error loading required resources.');
}

$account_arr = $this->view_var('account_data') ?: [];
$account_levels = $this->view_var('account_levels') ?: [];
$account_statuses = $this->view_var('account_statuses') ?: [];
?>
<div class="">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Account Details'); ?></h3>
    </section>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('ID'); ?></label>
        <div class="col-sm-9"><?php echo $account_arr['id'] ?? 'N/A'; ?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Username'); ?></label>
        <div class="col-sm-9"><?php echo $account_arr['nick'] ?? 'N/A'; ?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Created'); ?></label>
        <div class="col-sm-9"><?php
            echo !empty($account_arr['cdate']) ? PHS_Utils::pretty_date_html($account_arr['cdate']) : $this->_pt('N/A');
?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Created by'); ?></label>
        <div class="col-sm-9"><?php
if (empty($account_arr['added_by'])) {
    echo $this->_pt('System');
} else {
    echo '#'.$account_arr['added_by'];

    if (($added_by_account = $accounts_model->get_details($account_arr['added_by'], ['table_name' => 'users']))) {
        echo ' - '.$added_by_account['nick'] ?? $this->_pt('N/A');
    }
}
?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Email'); ?></label>
        <div class="col-sm-9"><?php echo $account_arr['email'] ?? 'N/A'; ?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Level'); ?></label>
        <div class="col-sm-9"><?php echo $account_levels[$account_arr['level']] ?? 'N/A'; ?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Status'); ?></label>
        <div class="col-sm-9"><?php
echo ($account_statuses[$account_arr['status']] ?? 'N/A')
     .(!empty($account_arr['status_date']) ? ' - '.PHS_Utils::pretty_date_html($account_arr['status_date']) : '');
?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Last login'); ?></label>
        <div class="col-sm-9"><?php
    echo (!empty($account_arr['lastlog']) ? PHS_Utils::pretty_date_html($account_arr['lastlog']) : $this->_pt('N/A'))
    .(!empty($account_arr['lastip']) ? ' - '.$this->_pt('from IP %s', $account_arr['lastip']) : '');
?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Password changed'); ?></label>
        <div class="col-sm-9"><?php
    echo !empty($account_arr['last_pass_change']) ? PHS_Utils::pretty_date_html($account_arr['last_pass_change']) : $this->_pt('N/A');
?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Selected language'); ?></label>
        <div class="col-sm-9"><?php
    echo !empty($account_arr['language']) ? ($this::get_defined_language($account_arr['language'])['title'] ?? $this->_pt('N/A')) : $this->_pt('N/A');
?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Email verified'); ?></label>
        <div class="col-sm-9"><?php
    echo !empty($account_arr['email_verified']) ? $this->_pt('Yes') : $this->_pt('No');
?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Password generated'); ?></label>
        <div class="col-sm-9"><?php
    echo !empty($account_arr['pass_generated']) ? $this->_pt('Yes') : $this->_pt('No');
?></div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Locked'); ?></label>
        <div class="col-sm-9"><?php
    echo (empty($account_arr['locked_date'])
        ? $this->_pt('No')
        : ($accounts_model->is_locked($account_arr)
            ? '<span class="text-danger font-weight-bold">'.$this->_pt('YES').'</span>'
            : $this->_pt('No')
        ).(!empty($account_arr['locked_date']) ? ' - '.PHS_Utils::pretty_date_html($account_arr['locked_date']) : ''))
          .' ('.$this->_pt('%s failures', $account_arr['failed_logins'] ?? 0).')'; ?></div>
    </div>

<?php
echo PHS_Event_Accounts_info_template::buffer($this, $this->get_all_view_vars());
?>
</div>
<script>
phs_refresh_input_skins();
</script>
