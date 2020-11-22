<?php
    /** @var \phs\system\core\views\PHS_View $this */

    if( !($default_route = $this->view_var( 'default_route' )) )
        $default_route = array();
    /** @var \phs\plugins\emails\libraries\PHS_Smtp $smtp_library */
    if( !($smtp_library = $this->view_var( 'smtp_library' )) )
        $smtp_library = false;
    /** @var \phs\plugins\emails\PHS_Plugin_Emails $emails_plugin */
    if( !($emails_plugin = $this->parent_plugin()) )
        $emails_plugin = false;

    if( !($test_email_sending_error = $this->view_var( 'test_email_sending_error' )) )
        $test_email_sending_error = '';
    if( !($test_email_sending_success = $this->view_var( 'test_email_sending_success' )) )
        $test_email_sending_success = false;

    $route_safe_name = str_replace( array( ' ', '[', ']' ), '', $emails_plugin::DEFAULT_ROUTE );

    ?>
    <div class="clearfix" style="margin-bottom:10px;"><?php echo $this->_pt( 'Test will be done using route %s. Be sure to save settings before testing.', $route_safe_name )?></div>
    <?php
    if( !empty( $test_email_sending_success ) )
    {
        ?>
        <div class="success-box"><?php echo $this->_pt( 'Successfully sent test email.' )?></div>
        <?php
    } elseif( !empty( $test_email_sending_error ) )
    {
        ?>
        <div class="error-box"><?php echo $test_email_sending_error?></div>
        <?php
    }
    ?>
    <div style="margin-bottom:10px;">
        <label for="test_email_sending_email" style="width:150px !important;">
            <?php echo $this->_pt( 'To email' )?>
            <i class="fa fa-question-circle" title="<?php echo $this->_pt( 'To what email should we send test email' )?>"></i>
        </label>
        <input type="text" class="form-control" style="width:250px;"
               name="test_email_sending_email"
               id="test_email_sending_email"
               value="<?php echo form_str( $this->view_var( 'test_email_sending_email' ) )?>" />
    </div>

    <div style="margin-bottom:10px;">
        <input type="submit" id="do_test_email_sending_submit" name="do_test_email_sending_submit" class="btn btn-success submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Send test email' )?>" />
    </div>
    <?php

