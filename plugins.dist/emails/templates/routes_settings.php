<?php
/** @var \phs\system\core\views\PHS_View $this */

if (!($email_routes = $this->view_var('email_routes'))) {
    $email_routes = [];
}
/** @var \phs\plugins\emails\libraries\PHS_Smtp $smtp_library */
if (!($smtp_library = $this->view_var('smtp_library'))) {
    $smtp_library = false;
}
/** @var \phs\plugins\emails\PHS_Plugin_Emails $emails_plugin */
if (!($emails_plugin = $this->parent_plugin())) {
    $emails_plugin = false;
}

foreach ($email_routes as $route_name => $route_arr) {
    $route_safe_name = str_replace([' ', '[', ']'], '', $route_name);

    ?>
    <div class="form-group row" style="border-bottom: 1px solid black;"><strong><?php echo $this->_pt('Route'); ?></strong>: <?php echo $route_name; ?></div>
    <div class="form-group">
        <label for="routes_<?php echo $route_safe_name; ?>_localhost">
            <?php echo $this->_pt('Localhost'); ?>
            <i class="fa fa-question-circle" title="<?php echo $this->_pt('What hostname should be reported to SMTP server'); ?>"></i>
        </label>
        <input type="text" class="form-control"
               name="routes[<?php echo $route_name; ?>][localhost]"
               id="routes_<?php echo $route_safe_name; ?>_localhost"
               value="<?php echo form_str($route_arr['localhost']); ?>" />
    </div>

    <div class="form-group">
        <label for="routes_<?php echo $route_safe_name; ?>_smtp_user"><?php echo $this->_pt('Username'); ?></label>
        <input type="text" class="form-control"
               autocomplete="new-password"
               name="routes[<?php echo $route_name; ?>][smtp_user]"
               id="routes_<?php echo $route_safe_name; ?>_smtp_user"
               value="<?php echo form_str($route_arr['smtp_user']); ?>" />
    </div>

    <div class="form-group">
        <label for="routes_<?php echo $route_safe_name; ?>_smtp_pass"><?php echo $this->_pt('Password'); ?></label>
        <input type="password" class="form-control"
               autocomplete="new-password"
               name="routes[<?php echo $route_name; ?>][smtp_pass]"
               id="routes_<?php echo $route_safe_name; ?>_smtp_pass"
               value="<?php echo empty($route_arr['smtp_pass']) ? '' : (!empty($emails_plugin) ? $emails_plugin::UNCHANGED_SMTP_PASS : '**********'); ?>" />
    </div>

    <div class="form-group">
        <label for="routes_<?php echo $route_safe_name; ?>_smtp_host">
            <?php echo $this->_pt('SMTP Host'); ?>
            <i class="fa fa-question-circle" title="<?php echo $this->_pt('SMTP server used to send emails'); ?>"></i>
        </label>
        <input type="text" class="form-control"
               name="routes[<?php echo $route_name; ?>][smtp_host]"
               id="routes_<?php echo $route_safe_name; ?>_smtp_host"
               value="<?php echo form_str($route_arr['smtp_host']); ?>" />
    </div>

    <div class="form-group">
        <label for="routes_<?php echo $route_safe_name; ?>_smtp_port">
            <?php echo $this->_pt('SMTP Port'); ?>
            <i class="fa fa-question-circle" title="SMTP - port 25 or 2525 or 587; Secure SMTP (SSL / TLS) - port 465 or 25 or 587, 2526 (Elastic Email)"></i>
        </label>
        <input type="text" class="form-control" style="width:100px;"
               name="routes[<?php echo $route_name; ?>][smtp_port]"
               id="routes_<?php echo $route_safe_name; ?>_smtp_port"
               value="<?php echo form_str($route_arr['smtp_port']); ?>" />
    </div>

    <div class="form-group">
        <label for="routes_<?php echo $route_safe_name; ?>_smtp_timeout"><?php echo $this->_pt('SMTP Timeout'); ?></label>
        <input type="text" class="form-control" style="width:50px;"
               name="routes[<?php echo $route_name; ?>][smtp_timeout]"
               id="routes_<?php echo $route_safe_name; ?>_smtp_timeout"
               value="<?php echo form_str($route_arr['smtp_timeout']); ?>" />
    </div>

    <div class="form-group">
        <label for="routes_<?php echo $route_safe_name; ?>_smtp_encryption">
            <?php echo $this->_pt('SMTP Encryption'); ?>
        </label>
        <?php
        if (!empty($smtp_library)
            && ($encryption_types = $smtp_library->get_encryption_types())) {
            ?>
            <select name="routes[<?php echo $route_name; ?>][smtp_encryption]"
                    id="routes_<?php echo $route_safe_name; ?>_smtp_encryption"
                    class="form-control chosen-select-nosearch">
                <option value=""> - Choose - </option>
                <?php
                foreach ($encryption_types as $encryption_type) {
                    ?><option value="<?php echo $encryption_type; ?>"
                    <?php echo $route_arr['smtp_encryption'] === $encryption_type ? 'selected="selected"' : ''; ?>
                    ><?php echo $encryption_type; ?></option><?php
                }
                ?>
            </select>
            <?php
        } else {
            ?>
            <input type="text" class="form-control"
                   name="routes[<?php echo $route_name; ?>][smtp_encryption]"
                   id="routes_<?php echo $route_safe_name; ?>_smtp_encryption"
                   value="<?php echo form_str($route_arr['smtp_encryption']); ?>" />
            <?php
        }
        ?>
    </div>

    <div class="form-group">
        <label for="routes_<?php echo $route_safe_name; ?>_smtp_authentication"><?php echo $this->_pt('SMTP Authetication'); ?></label>
        <?php
        if (!empty($smtp_library)
            && ($authentication_methods = $smtp_library->get_authentication_methods())) {
            ?><select name="routes[<?php echo $route_name; ?>][smtp_authentication]"
                      id="routes_<?php echo $route_safe_name; ?>_smtp_authentication"
                      class="form-control chosen-select-nosearch">
            <option value=""> - Choose - </option>
            <?php
            foreach ($authentication_methods as $authentication_method) {
                ?><option value="<?php echo $authentication_method; ?>"
                <?php echo $route_arr['smtp_authentication'] === $authentication_method ? 'selected="selected"' : ''; ?>
                ><?php echo $authentication_method; ?></option><?php
            }
            ?>
            </select>
            <?php
        } else {
            ?>
            <input type="text" class="form-control"
                   name="routes[<?php echo $route_name; ?>][smtp_authentication]"
                   id="routes_<?php echo $route_safe_name; ?>_smtp_authentication"
                   value="<?php echo form_str($route_arr['smtp_authentication']); ?>" />
            <?php
        }
        ?>
    </div>
    <?php
}
