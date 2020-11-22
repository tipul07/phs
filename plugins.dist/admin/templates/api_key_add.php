<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_Api;

    /** @var \phs\system\core\models\PHS_Model_Api_keys $apikeys_model */
    /** @var \phs\plugins\admin\actions\PHS_Action_Users_autocomplete $users_autocomplete_action */
    if( !($apikeys_model = $this->view_var( 'apikeys_model' ))
     or !($users_autocomplete_action = $this->view_var( 'users_autocomplete_action' )) )
        return $this->_pt( 'Couldn\'t initialize view.' );

    if( !($api_methods_arr = $this->view_var( 'api_methods_arr' )) )
        $api_methods_arr = array();
    if( !($allowed_methods = $this->view_var( 'allowed_methods' )) )
        $allowed_methods = array();
    if( !($denied_methods = $this->view_var( 'denied_methods' )) )
        $denied_methods = array();
?>
<div style="min-width:100%;max-width:1000px;margin: 0 auto;">
    <form id="add_apikey_form" name="add_apikey_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'api_key_add' ) )?>" method="post">
        <input type="hidden" name="foobar" value="1" />

        <div class="form_container responsive" style="width: 100%;">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Add API key' )?></h3>
            </section>

            <fieldset class="form-group">
                <label for="api_key"><?php echo $this->_pt( 'User account' )?>:</label>
                <div class="lineform_line">
                <?php echo $users_autocomplete_action->autocomplete_inputs( $this->get_all_view_vars() );?>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="title"><?php echo $this->_pt( 'Title' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="title" name="title" class="form-control" value="<?php echo form_str( $this->view_var( 'title' ) )?>" autocomplete="off" />
                <br/><small><?php echo $this->_pt( 'Short description for this API key' )?></small>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="api_key"><?php echo $this->_pt( 'API Key' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="api_key" name="api_key" class="form-control" value="<?php echo form_str( $this->view_var( 'api_key' ) )?>" autocomplete="off" />
                <br/><small><?php echo $this->_pt( 'Leave empty to autogenerate.' )?></small>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="api_secret"><?php echo $this->_pt( 'API Secret' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="api_secret" name="api_secret" class="form-control" value="<?php echo form_str( $this->view_var( 'api_secret' ) )?>" autocomplete="off" />
                <br/><small><?php echo $this->_pt( 'Leave empty to autogenerate.' )?></small>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="email"><?php echo $this->_pt( 'Allowed HTTP methods' )?>:</label>
                <div class="lineform_line">
                <?php
                $old_plugin = false;
                foreach( $api_methods_arr as $api_method )
                {
                    ?>
                    <div style="float:left;margin-top:5px;"><input type="checkbox" id="allowed_methods_<?php echo $api_method ?>"
                                                    name="allowed_methods[]" value="<?php echo form_str( $api_method )?>" rel="skin_checkbox"
                                                    <?php echo (in_array( $api_method, $allowed_methods ) ? 'checked="checked"' : '')?> /></div>
                    <label style="margin: 5px 10px 5px 0;width: auto !important;max-width: none !important;float:left;" for="allowed_methods_<?php echo $api_method ?>">
                    <?php echo $api_method?>
                    </label>
                    <?php
                }
                ?>
                <div class="clearfix"></div>
                <br/><small><?php echo $this->_pt( 'Don\'t tick any method to allow all.' )?></small>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="email"><?php echo $this->_pt( 'Denied HTTP methods' )?>:</label>
                <div class="lineform_line">
                <?php
                $old_plugin = false;
                foreach( $api_methods_arr as $api_method )
                {
                    ?>
                    <div style="float:left;margin-top:5px;"><input type="checkbox" id="denied_methods_<?php echo $api_method ?>"
                                                    name="denied_methods[]" value="<?php echo form_str( $api_method )?>" rel="skin_checkbox"
                                                    <?php echo (in_array( $api_method, $denied_methods ) ? 'checked="checked"' : '')?> /></div>
                    <label style="margin: 5px 10px 5px 0;width: auto !important;max-width: none !important;float:left;" for="denied_methods_<?php echo $api_method ?>">
                    <?php echo $api_method?>
                    </label>
                    <?php
                }
                ?>
                <div class="clearfix"></div>
                <br/><small><?php echo $this->_pt( 'Tick only methods which you want to deny access to.' )?></small>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="allow_sw"><?php echo $this->_pt( 'Allow Simulating Web' )?>:</label>
                <div class="lineform_line">
                    <input type="checkbox" id="allow_sw" name="allow_sw" value="1" rel="skin_checkbox" <?php echo ($this->view_var( 'allow_sw' )?'checked="checked"':'')?> />
                    <br/><small><?php echo $this->_pt( 'If ticked, API key will be allowed to access actions which are normally available in web scope (by sending %s=1 in GET).', PHS_Api::PARAM_WEB_SIMULATION )?></small>
                </div>
            </fieldset>

            <fieldset>
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Add API key' )?>" />
            </fieldset>

        </div>
    </form>
</div>
<div class="clearfix"></div>
<?php

    echo $users_autocomplete_action->js_all_functionality( $this->get_all_view_vars() );
