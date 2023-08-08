<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Utils;

/** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
/** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
/** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
if (!($libs_plugin = $this->view_var('libs_plugin'))
    || !($accounts_plugin = $this->view_var('accounts_plugin'))
    || !($tfa_model = $this->view_var('tfa_model'))
    || !($tfa_arr = $this->view_var('tfa_data'))
    || !$tfa_model->is_setup_completed($tfa_arr)) {
    return $this->_pt('Error loading required resources.');
}

if (!($back_page = $this->view_var('back_page'))) {
    $back_page = '';
}
if (!($device_session_length = $this->view_var('device_session_length'))) {
    $device_session_length = 0;
}
?>
<div class="form_container">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Two Factor Authentication Verification'); ?></h3>
    </section>

    <div id="code_verification_section">
        <form id="tfa_check" name="tfa_check" method="post"
              action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'verify', 'ad' => 'tfa']); ?>">
            <input type="hidden" name="foobar" value="1" />
            <?php
            if (!empty($back_page)) {
                ?><input type="hidden" name="back_page" value="<?php echo form_str(safe_url($back_page)); ?>" /><?php
            }
            ?>

            <div class="form-group row">
                <div class="col-sm-12">
                    <p><?php echo $this->_pt('In order to access your account, please open Google Authenticator on you device and provide the code in the input below.'); ?></p>
                </div>
            </div>

            <div class="form-group row">
                <label for="tfa_code" class="col-sm-2 col-form-label"><?php echo $this->_pt('Verification Code'); ?></label>
                <div class="col-sm-10">
                    <input type="text" id="tfa_code" name="tfa_code" autocomplete="tfa_code" class="form-control"
                           required="required" value="" />
                </div>
            </div>

            <?php
            if( $device_session_length ) {
                ?>
                <div class="form-group row">
                    <div class="col-sm-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember_device" name="remember_device"
                                <?php echo $this->view_var('remember_device')?'checked="checked"':''?>
                                   value="1">
                            <label class="form-check-label" for="remember_device">
                                <?php echo $this->_pt( 'Remember this device. Ask for a two factor authentication code only after %s of inactivity.',
                                    PHS_Utils::parse_period($device_session_length * 3600, ['only_big_part' => true]) )?>
                            </label>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>

            <div class="form-group row">
                <div class="col-sm-12">
                    <input type="submit" id="do_submit" name="do_submit"
                           class="btn btn-primary submit-protection ignore_hidden_required"
                           value="<?php echo $this->_pte('Check Code'); ?>" />
                </div>
            </div>
        </form>
    </div>

    <div id="recovery_button_section">
        <div class="form-group row">
            <div class="col-sm-12">
                <p><?php echo $this->_pt('In case you lost your device or you don\'t have access to Google Authenticator, you can use one of your recovery codes below.'); ?></p>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-sm-12">
                <input type="button" id="do_download_codes" name="do_download_codes"
                       onclick="start_recovery_check()" class="btn btn-primary"
                       value="<?php echo $this->_pte('Use Recovery Code'); ?>" />
            </div>
        </div>
    </div>

    <div id="recovery_section" style="display:none;">
        <form id="tfa_recovery_check" name="tfa_recovery_check" method="post"
              action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'verify', 'ad' => 'tfa']); ?>">
            <input type="hidden" name="foobar" value="1" />
            <?php
            if (!empty($back_page)) {
                ?><input type="hidden" name="back_page" value="<?php echo form_str(safe_url($back_page)); ?>" /><?php
            }
            ?>

            <div class="form-group row">
                <div class="col-sm-12">
                    <p><?php echo $this->_pt('In order to download your recovery codes, please provide a two factor authentication verification code first.'); ?></p>
                </div>
            </div>

            <div class="form-group row">
                <label for="tfa_recovery_code" class="col-sm-2 col-form-label"><?php echo $this->_pt('Recovery Code'); ?></label>
                <div class="col-sm-10">
                    <input type="text" id="tfa_recovery_code" name="tfa_code" autocomplete="tfa_recovery_code" class="form-control"
                           required="required" value="" />
                </div>
            </div>

            <?php
            if( $device_session_length ) {
                ?>
                <div class="form-group row">
                    <div class="col-sm-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember_device" name="remember_device"
                                <?php echo $this->view_var('remember_device')?'checked="checked"':''?>
                                   value="1">
                            <label class="form-check-label" for="remember_device">
                                <?php echo $this->_pt( 'Remember this device. Ask for a two factor authentication code only after %s of inactivity.',
                                    PHS_Utils::parse_period($device_session_length * 3600, ['only_big_part' => true]) )?>
                            </label>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>

            <div class="form-group row">
                <div class="col-sm-12">
                    <input type="submit" id="do_check_recovery" name="do_check_recovery"
                           class="btn btn-primary submit-protection ignore_hidden_required"
                           value="<?php echo $this->_pte('Check Recovery Code'); ?>" />
                </div>
            </div>

            <div class="form-group row">
                <div class="col-sm-12">
                    <input type="button" id="do_cancel_recovery" name="do_cancel_recovery"
                           onclick="cancel_recovery_check()" class="btn btn-warning"
                           value="<?php echo $this->_pte('Cancel Recovery Check'); ?>" />
                </div>
            </div>
        </form>
    </div>

</div>
<script>
    function start_recovery_check()
    {
        $("#code_verification_section").hide();
        $("#recovery_button_section").hide();
        $("#recovery_section").show();
    }
    function cancel_recovery_check()
    {
        $("#code_verification_section").show();
        $("#recovery_button_section").show();
        $("#recovery_section").hide();
    }
</script>
