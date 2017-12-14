<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;

    if( !($hook_args = $this->view_var( 'hook_args' )) )
        $hook_args = array();
    if( !($settings_arr = $this->view_var( 'settings_arr' )) )
        $settings_arr = array();

    $url_params = array();
    /** @var \phs\plugins\captcha\PHS_Plugin_Captcha $plugin_instance */
    if( ($plugin_instance = $this->get_plugin_instance()) )
        $check_fields = $plugin_instance->indexes_to_vars();
    else
        $check_fields = array();

    foreach( $check_fields as $field => $url_param )
    {
        if( array_key_exists( $field, $hook_args ) )
        {
            $exists_in_settings = array_key_exists( $field, $settings_arr );
            if( !$exists_in_settings
             or ($exists_in_settings and $settings_arr[$field] != $hook_args[$field]) )
                $url_params[$url_param] = $hook_args[$field];
        }
    }

    if( !empty( $hook_args['default_width'] ) )
        $img_width = $hook_args['default_width'];
    if( !empty( $hook_args['default_height'] ) )
        $img_height = $hook_args['default_height'];

    if( empty( $img_width ) )
        $img_width = 200;
    if( empty( $img_height ) )
        $img_height = 50;

?><img src="<?php echo PHS::url( array( 'p' => 'captcha' ), $url_params );?>" style="width: <?php echo $img_width;?>px;height: <?php echo $img_height;?>px;<?php echo (!empty( $hook_args['extra_img_style'] )?$hook_args['extra_img_style']:'')?>" <?php echo (!empty( $hook_args['extra_img_attrs'] )?$hook_args['extra_img_attrs']:'')?> class="captcha-img" />
