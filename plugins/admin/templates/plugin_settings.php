<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_params;

    /** @var \phs\libraries\PHS_Plugin $plugin_obj */
    if( !($plugin_obj = $this->context_var( 'plugin_obj' )) )
        return $this::_t( 'Plugin ID is invalid or plugin was not found.' );

    if( !($back_page = $this->context_var( 'back_page' )) )
        $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'plugins_list' ) );
    if( !($form_data = $this->context_var( 'form_data' )) )
        $form_data = array();

    if( !($plugin_settings = $plugin_obj->get_plugin_db_settings()) )
        $plugin_settings = array();

    if( !($plugin_info = $plugin_obj->get_plugin_info()) )
        $plugin_info = $plugin_obj->default_plugin_details_fields();

    $current_user = PHS::user_logged_in();
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:100%;max-width:1000px;margin: 0 auto;">
    <form id="plugin_settings_form" name="plugin_settings_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'plugin_settings', array( ) ) )?>" method="post" class="wpcf7">
        <input type="hidden" name="foobar" value="1" />
        <?php
        if( !empty( $back_page ) )
        {
            ?><input type="hidden" name="back_page" value="<?php echo form_str( safe_url( $back_page ) )?>" /><?php
        }
        ?>
        <div class="form_container responsive" style="width: 850px;">

            <?php
            if( !empty( $back_page ) )
            {
                ?><i class="fa fa-chevron-left"></i> <a href="<?php echo form_str( from_safe_url( $back_page ) ) ?>"><?php echo $this::_t( 'Back' )?></a><?php
            }
            ?>

            <section class="heading-bordered">
                <h3><?php echo $plugin_info['name'].' <small>'.$plugin_obj->get_plugin_version().'</small>'?></h3>
            </section>

            <?php
            if( !empty( $plugin_info['description'] ) )
            {
                ?><div class="clearfix"></div>
                <small style="top:-25px;position:relative;"><?php echo $plugin_info['description']?></small><?php
            }

            if( ($settings_fields = $plugin_obj->validate_settings_structure()) )
            {
                foreach( $settings_fields as $field_name => $field_details )
                {
                    if( empty($field_details['editable']) )
                        continue;

                    if( !empty($field_details['display_placeholder']) )
                        $field_placeholder = $field_details['display_placeholder'];
                    else
                        $field_placeholder = '';

                    $field_id    = $field_name;
                    $field_name  = $field_id;
                    $field_value = null;
                    if( isset($form_data[$field_name]) )
                        $field_value = $form_data[$field_name];
                    if( isset( $plugin_settings[$field_name] ) )
                        $field_value = $plugin_settings[$field_name];
                    elseif( $field_details['default'] !== null )
                        $field_value = $field_details['default'];

                    ?>
                    <fieldset class="lineformwide">
                        <label for="<?php echo $field_id ?>"><strong><?php echo $field_details['display_name']; ?></strong></label>
                        <div class="lineformwide_line"><?php

                        if( !empty( $field_details['custom_renderer'] )
                        and is_callable( $field_details['custom_renderer'] ) )
                        {
                            $callback_params = $plugin_obj->default_custom_renderer_params();
                            $callback_params['plugin_obj'] = $plugin_obj;
                            $callback_params['field_name'] = $field_name;
                            $callback_params['field_details'] = $field_details;
                            $callback_params['field_value'] = $field_value;
                            $callback_params['form_data'] = $form_data;

                            if( ($cell_content = @call_user_func( $field_details['custom_renderer'], $callback_params )) === false
                             or $cell_content === null )
                                $cell_content = '[' . $this::_t( 'Render settings field call failed.' ) . ']';

                            echo $cell_content;

                        } elseif( !empty( $field_details['input_type'] ) )
                        {
                            switch( $field_details['input_type'] )
                            {
                                case $plugin_obj::INPUT_TYPE_TEMPLATE:

                                    echo $this::_t( 'Template file' ).': ';
                                    if( is_string( $field_value ) )
                                        echo $field_value;

                                    elseif( !empty( $field_value['file'] ) )
                                        echo $field_value['file'];

                                    else
                                        echo $this::_t( 'N/A' );

                                    if( is_array( $field_value )
                                    and !empty( $field_value['extra_paths'] ) and is_array( $field_value['extra_paths'] ) )
                                    {
                                        echo '<br/>'.$this::_t( 'From paths' ).': ';

                                        $paths_arr = array();
                                        foreach( $field_value['extra_paths'] as $path_dir => $path_www )
                                        {
                                            $paths_arr[] = $path_dir;
                                        }

                                        if( empty( $paths_arr ) )
                                            echo $this::_t( 'N/A' );

                                        else
                                            echo implode( ', ', $paths_arr );
                                    }
                                break;

                                case $plugin_obj::INPUT_TYPE_ONE_OR_MORE:
                                    if( empty( $field_details['values_arr'] )
                                     or !is_array( $field_details['values_arr'] ) )
                                        echo $this::_t( 'Values array should be provided' );

                                    else
                                    {
                                        if( empty( $field_value ) or !is_array( $field_value ) )
                                            $field_value = array();

                                        foreach( $field_details['values_arr'] as $one_more_key => $one_more_text )
                                        {
                                            $option_checked = in_array( $one_more_key, $field_value );

                                            $field_id .= '_'.$one_more_key;

                                            ?>
                                            <div style="float:left; margin-right:10px;">
                                            <input type="checkbox" id="<?php echo $field_id ?>" name="<?php echo $field_name ?>[]" class="wpcf7-text <?php echo $field_details['extra_classes'] ?>" value="<?php echo form_str( $one_more_key )?>" rel="skin_checkbox" <?php echo(!empty($option_checked) ? 'checked="checked"' : '') ?> style="<?php echo $field_details['extra_style'] ?>" />
                                            <label for="<?php echo $field_id?>" style="margin-left:5px;width:auto !important;float:right;"><?php echo $one_more_text?></label>
                                            </div>
                                            <?php
                                        }
                                    }
                                break;
                            }
                        } else
                        {
                            if( !empty( $field_details['values_arr'] )
                            and is_array( $field_details['values_arr'] ) )
                            {
                                ?>
                                <select id="<?php echo $field_id ?>" name="<?php echo $field_name ?>" class="wpcf7-select <?php echo $field_details['extra_classes'] ?>" style="<?php echo $field_details['extra_style'] ?>"><?php

                                foreach( $field_details['values_arr'] as $key => $val )
                                {
                                    ?><option value="<?php echo $key ?>" <?php echo($field_value == $key ? 'selected="selected"' : '') ?>><?php echo $val ?></option><?php
                                }

                                ?></select><?php
                            }
                            else
                            {
                                switch( $field_details['type'] )
                                {
                                    case PHS_params::T_DATE:
                                        ?>
                                        <input type="text" id="<?php echo $field_id ?>" name="<?php echo $field_name ?>" class="datepicker wpcf7-text <?php echo $field_details['extra_classes'] ?>" value="<?php echo form_str( $field_value ) ?>" style="<?php echo $field_details['extra_style'] ?>" /><?php
                                    break;

                                    case PHS_params::T_BOOL:
                                        ?><input type="checkbox" id="<?php echo $field_id ?>" name="<?php echo $field_name ?>" class="wpcf7-text <?php echo $field_details['extra_classes'] ?>" value="1" rel="skin_checkbox" <?php echo(!empty($field_value) ? 'checked="checked"' : '') ?> style="<?php echo $field_details['extra_style'] ?>" /><?php
                                    break;

                                    default:
                                        ?>
                                        <input type="text" id="<?php echo $field_id ?>" name="<?php echo $field_name ?>" class="wpcf7-text <?php echo $field_details['extra_classes'] ?>" value="<?php echo form_str( $field_value ) ?>" <?php echo(!empty($field_placeholder) ? 'placeholder="' . form_str( $field_placeholder ) . '"' : '') ?> style="<?php echo $field_details['extra_style'] ?>" /><?php
                                    break;
                                }
                            }
                        }

                        if( !empty($field_details['display_hint']) )
                        {
                            ?>
                            <div class="clearfix"></div>
                            <small><?php echo form_str( $field_details['display_hint'] ) ?></small><?php
                        }

                        ?></div>
                    </fieldset>
                    <?php
                }
                ?>

                <fieldset>
                    <input type="submit" id="submit" name="submit" class="wpcf7-submit submit-protection" value="<?php echo $this::_te( 'Save settings' ) ?>"/>
                    <button type="button" id="cancel" class="wpcf7-submit" style="margin-right:10px;" onclick="document.location='<?php echo $this::_e( $back_page, '\'' )?>';"><?php echo $this::_te( 'Cancel' ) ?></button>
                </fieldset>
                <?php
            } else
            {
                ?><p style="text-align: center;"><?php echo $this::_t( 'This plugin doesn\'t have any settings..' )?></p><?php
            }
            ?>

        </div>
    </form>
</div>
<div class="clearfix"></div>
