<?php
/** @var phs\system\core\views\PHS_View $this */
$account_details_arr = $this->view_var('account_details_data') ?: [];
?>

<section class="heading-bordered">
    <h3><?php echo $this->_pt('Internal Messages'); ?></h3>
</section>

<div class="form-group row">
    <label class="col-sm-3 col-form-label"><?php echo $this->_pt('Message handler'); ?></label>
    <div class="col-sm-9"><?php echo $account_details_arr['msg_handler'] ?? 'N/A'; ?></div>
</div>
