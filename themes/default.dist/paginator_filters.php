<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Params;

    /** @var \phs\libraries\PHS_Paginator $paginator_obj */
    if( !($paginator_obj = $this->view_var( 'paginator' )) )
    {
        echo $this->_pt( 'Rendered from outside of paginator.' );
        return;
    }

    if( !($base_url = $paginator_obj->base_url()) )
        $base_url = '#';
    if( !($full_listing_url = $paginator_obj->get_full_url( [ 'include_filters' => false ] )) )
        $full_listing_url = '#';

    if( !($flow_params_arr = $paginator_obj->flow_params()) )
        $flow_params_arr = $paginator_obj->default_flow_params();

    if( !($filters_form_name = $paginator_obj->get_filters_form_name()) )
        $filters_form_name = $flow_params_arr['form_prefix'].'paginator_filters_form';

    if( !($filters_arr = $paginator_obj->get_filters()) )
        $filters_arr = [];
    if( !($scope_arr = $paginator_obj->get_scope()) )
        $scope_arr = [];
    if( !($originals_arr = $paginator_obj->get_originals()) )
        $originals_arr = [];

    $show_filters = (bool) PHS_Params::_g( 'show_filters', PHS_Params::T_INT );

    if( !empty( $flow_params_arr['before_filters_callback'] )
     && @is_callable( $flow_params_arr['before_filters_callback'] ) )
    {
        $callback_params = $paginator_obj->default_others_render_call_params();
        $callback_params['filters'] = $filters_arr;

        if( ($cell_content = @call_user_func( $flow_params_arr['before_filters_callback'], $callback_params )) === false
         || $cell_content === null )
            $cell_content = '[' . $this->_pt( 'Render before filters call failed.' ) . ']';

        echo $cell_content;
    }

    $paginator_full_path_id = '';
    if( ($route_as_string = PHS::get_route_as_string()) )
        $paginator_full_path_id = 'phs_aof_'.str_replace( [ '/', '-' ], '_', $route_as_string );

    $phs_first_ac_autocomplete_action = false;
?>
<div class="list_filters_container">
    <form id="<?php echo $filters_form_name?>" name="<?php echo $filters_form_name?>" action="<?php echo $full_listing_url?>" method="post">
    <input type="hidden" name="foobar" value="1" />

        <div class="form_container">

            <div id="<?php echo $filters_form_name?>_inputs" style="display:<?php echo ($show_filters?'block':'none')?>;">
            <?php
            $filters_display_arr = [];
            foreach( $filters_arr as $filter_details )
            {
                if( empty( $filter_details ) || !is_array( $filter_details )
                 || empty( $filter_details['var_name'] )
                 || !empty( $filter_details['hidden_filter'] )
                 || (!empty( $filter_details['autocomplete'] )
                        && (!is_array( $filter_details['autocomplete'] ) || empty( $filter_details['autocomplete']['action'] ))
                    ) )
                    continue;

                if( !empty( $filter_details['display_placeholder'] ) )
                    $field_placeholder = $filter_details['display_placeholder'];
                else
                    $field_placeholder = '';

                $field_id = $flow_params_arr['form_prefix'].$filter_details['var_name'];
                $field_name = $field_id;
                $field_value = null;
                if( isset( $scope_arr[$filter_details['var_name']] ) )
                    $field_value = $scope_arr[$filter_details['var_name']];
                elseif( $filter_details['default'] !== null )
                    $field_value = $filter_details['default'];

                if( is_array( $field_value ) )
                    $field_value = implode( ',', $field_value );

                $field_value_display = $field_value;

                ?>
                <fieldset class="paginator_filter">
                    <label for="<?php echo $field_id?>"><?php

                        echo $filter_details['display_name'];

                        if( !empty( $filter_details['display_hint'] ) )
                        {
                            ?> <i class="fa fa-question-circle" title="<?php echo form_str( $filter_details['display_hint'] )?>"></i> <?php
                        }

                        $default_value = '';
                        if( is_scalar( $filter_details['default'] ) )
                            $default_value = $filter_details['default'];

                        ?> <a href="javascript:void(0)"
                              onclick="this.blur();clear_filter_value( '<?php echo $field_id?>', '<?php echo $this::_e( $default_value, '\'' )?>', <?php echo (!empty( $filter_details['autocomplete'] )?'true':'false')?> )"
                            <i class="fa fa-times-circle" title="<?php echo $this->_pt( 'Clear filter' )?>"></i></a> <?php

                    ?></label>
                    <div class="paginator_input"><?php

                    if( !empty( $filter_details['autocomplete'] ) )
                    {
                        /** @var \phs\libraries\PHS_Action_Autocomplete $ac_action */
                        $ac_action = $filter_details['autocomplete']['action'];

                        if( empty( $filter_details['autocomplete']['display_data_format'] ) )
                            $filter_details['autocomplete']['display_data_format'] = false;

                        if( !empty( $scope_arr[$filter_details['var_name'].'_phs_ac_name'] ) )
                            $field_value_display = $scope_arr[$filter_details['var_name'].'_phs_ac_name'];

                        elseif( !empty( $field_value )
                             && $filter_details['default'] !== null
                             && $filter_details['default'] != $field_value )
                        {
                            if( !($field_value_display = $ac_action->format_data( $field_value, $filter_details['autocomplete']['display_data_format'], true )) )
                                $field_value_display = '['.$this->_pt( 'Unknown value.' ).']';
                        }

                        else
                            $field_value_display = '';

                        if( empty( $filter_details['autocomplete']['input_vars'] )
                         || !is_array( $filter_details['autocomplete']['input_vars'] ) )
                            $filter_details['autocomplete']['input_vars'] = [];

                        $input_vars = $filter_details['autocomplete']['input_vars'];
                        $input_vars['id_id'] = $field_id;
                        $input_vars['id_name'] = $field_name;
                        $input_vars['text_id'] = $field_id.'_phs_ac_name';
                        $input_vars['text_name'] = $field_name.'_phs_ac_name';

                        if( $filter_details['default'] !== null )
                            $input_vars['default_value'] = $filter_details['default'];

                        $input_vars['id_value'] = $field_value;
                        $input_vars['text_value'] = $field_value_display;
                        if( !empty( $field_placeholder ) )
                            $input_vars['text_placeholder'] = $field_placeholder;

                        $ac_action->autocomplete_params( $input_vars );

                        echo $ac_action->autocomplete_inputs( $input_vars );
                        echo $ac_action->js_autocomplete_functionality( $input_vars );

                        $phs_first_ac_autocomplete_action = $ac_action;
                    } elseif( !empty( $filter_details['values_arr'] ) && is_array( $filter_details['values_arr'] ) )
                    {
                        ?><select id="<?php echo $field_id?>" name="<?php echo $field_name?>" class="chosen-select <?php echo $filter_details['extra_classes']?>"
                                  style="<?php echo $filter_details['extra_style']?>"><?php

                        foreach( $filter_details['values_arr'] as $key => $val )
                        {
                            if( $field_value == $key )
                                $field_value_display = $val;

                            ?><option value="<?php echo $key?>" <?php echo ($field_value==$key?'selected="selected"':'')?>><?php echo $val?></option><?php
                        }

                        ?></select><?php
                    } else
                    {
                        switch( $filter_details['type'] )
                        {
                            case PHS_Params::T_DATE:
                                ?><input type="text" id="<?php echo $field_id?>" name="<?php echo $field_name?>" readonly="readonly"
                                         class="phs_filter_datepicker form-control <?php echo $filter_details['extra_classes']?>"
                                         value="<?php echo form_str( $field_value )?>" style="<?php echo $filter_details['extra_style']?>" /><?php
                            break;

                            case PHS_Params::T_BOOL:
                                $field_value_display = (!empty( $field_value )?$this->_pt( 'True' ):$this->_pt( 'False' ));
                                ?><input type="checkbox" id="<?php echo $field_id?>" name="<?php echo $field_name?>"
                                         class="<?php echo $filter_details['extra_classes']?>" value="1" rel="skin_checkbox"
                                         <?php echo (!empty( $field_value )?'checked="checked"':'')?> style="<?php echo $filter_details['extra_style']?>" /><?php
                            break;

                            default:
                                ?><input type="text" id="<?php echo $field_id?>" name="<?php echo $field_name?>"
                                         class="form-control <?php echo $filter_details['extra_classes']?>"
                                         value="<?php echo form_str( $field_value )?>" <?php echo (!empty( $field_placeholder )?'placeholder="'.form_str( $field_placeholder ).'"':'')?>
                                         style="<?php echo $filter_details['extra_style']?>" /><?php
                            break;
                        }
                    }

                    ?></div>
                </fieldset>
                <?php

                if( $field_value_display !== null
                 && (isset( $scope_arr[$filter_details['var_name']] )
                        || ($filter_details['default'] !== null && !empty( $filter_details['display_default_as_filter'] ))
                     ) )
                    $filters_display_arr[] = '<em>'.$filter_details['display_name'].'</em>: '.$field_value_display;
            }
            ?>

            <div class="clearfix"></div>
            <div class="row">
            <div class="col-6">
                <input type="submit" id="submit" name="submit" class="btn btn-primary submit-protection ignore_hidden_required"
                       value="<?php echo $this->_pte( 'Filter' )?>" />
                <input type="button" onclick="toggle_filters_inputs_and_text( '<?php echo $filters_form_name?>' )" class="btn btn-primary" style="margin-right:5px;"
                       value="<?php echo $this->_pte( 'Hide Filters' )?>" />
            </div>
            <?php
            if( !empty( $paginator_full_path_id ) )
            {
                ?>
                <div class="col-6 text-right">
                    <label for="<?php echo $paginator_full_path_id?>">
                        <input type="checkbox" value="1" rel="skin_checkbox"
                               name="<?php echo $paginator_full_path_id?>" id="<?php echo $paginator_full_path_id?>"
                               onchange="phs_keep_filters_opened_tick( '<?php echo $paginator_full_path_id?>' )" />
                        <small><?php echo $this->_pt( 'Keep filters opened' )?></small>
                    </label>
                </div>
                <?php
            }
            ?>
            </div>
            </div>

            <div id="<?php echo $filters_form_name?>_text" style="display:<?php echo (!$show_filters?'block':'none')?>;">
            <?php
            // $filters_str_arr = [];
            // foreach( $filters_arr as $filter_details )
            // {
            //     if( empty( $filter_details['var_name'] ) )
            //         continue;
            //
            //     $field_value = null;
            //     /** @var \phs\libraries\PHS_Action_Autocomplete $ac_action */
            //     if( !empty( $filter_details['autocomplete'] )
            //      && is_array( $filter_details['autocomplete'] )
            //      && ($ac_action = $filter_details['autocomplete']['action']) )
            //     {
            //         if( isset( $scope_arr[$filter_details['var_name']] ) )
            //         {
            //             if( empty( $filter_details['autocomplete']['display_data_format'] ) )
            //                 $filter_details['autocomplete']['display_data_format'] = false;
            //
            //             if( !($field_value = $ac_action->format_data( $scope_arr[$filter_details['var_name']], $filter_details['autocomplete']['display_data_format'], true )) )
            //                 $field_value = null;
            //
            //             var_dump( $field_value );
            //         }
            //     } elseif( isset( $scope_arr[$filter_details['var_name']] ) )
            //         $field_value = $scope_arr[$filter_details['var_name']];
            //     elseif( $filter_details['default'] !== null && !empty( $filter_details['display_default_as_filter'] ) )
            //         $field_value = $filter_details['default'];
            //
            //     if( $field_value === null )
            //         continue;
            //
            //     if( is_array( $field_value ) )
            //         $field_value = implode( ',', $field_value );
            //
            //     $filters_str_arr[] = $filter_details['display_name'].': '.$field_value;
            // }

            if( empty( $filters_display_arr ) )
                echo $this->_pt( 'No filters set.' );
            else
                echo '<strong>'.$this->_pt( 'Current filters' ).'</strong> - '.implode( ', ', $filters_display_arr ).'.';
            ?>
            (<a href="javascript:void(0);" onclick="toggle_filters_inputs_and_text( '<?php echo $filters_form_name?>' )"><?php echo $this->_pt( 'Show filters' )?></a>)
            </div>

            <div class="clearfix"></div>

        </div>
    </form>
    <div class="clearfix"></div>
</div>
<div class="clearfix"></div>
<?php
if( !empty( $phs_first_ac_autocomplete_action ) )
{
    echo $phs_first_ac_autocomplete_action->js_generic_functionality();
}
?>
<script type="text/javascript">
$(document).ready(function(){
    $(".phs_filter_datepicker").datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        showButtonPanel: true
    });

    // check if we should keep filters opened...
    if( phs_filters_should_be_opened( "<?php echo $paginator_full_path_id?>" ) ) {
        phs_filters_force_open( "<?php echo $paginator_full_path_id?>", "<?php echo $filters_form_name?>" );
    }
});
function clear_filter_value( obj_id, scalar_default_val, for_autocomplete )
{
    if( for_autocomplete ) {
        phs_autocomplete_input_reset( obj_id, obj_id + "_phs_ac_name", scalar_default_val );
        return;
    }

    var obj = $("#"+obj_id);
    if( !obj )
        return;

    var obj_type = obj.prop( "type" );
    if( obj_type !== "select-one" && obj_type !== "select-multiple" )
        obj.val( scalar_default_val );

    else
    {
        var default_val = "";
        if( obj[0] && obj[0][0] )
            default_val = obj[0][0].value;

        obj.val( default_val );

        obj.trigger( "chosen:updated" );
    }
}
function toggle_filters_inputs_and_text( filters_form_name )
{
    var inputs_obj = $("#" + filters_form_name + "_inputs");
    var text_obj = $("#" + filters_form_name + "_text");

    if( inputs_obj )
        inputs_obj.slideToggle('fast');
    if( text_obj )
        text_obj.slideToggle('fast');
}
function phs_filters_force_open( path_id, filters_form_name )
{
    var inputs_obj = $("#" + filters_form_name + "_inputs");
    var text_obj = $("#" + filters_form_name + "_text");
    var checkbox_obj = $("#" + path_id);

    if( checkbox_obj )
        checkbox_obj.prop( "checked", true );
    if( inputs_obj )
        inputs_obj.show();
    if( text_obj )
        text_obj.hide();
}
function phs_keep_filters_opened_tick( path_id )
{
    var checkbox_obj = $("#" + path_id);
    if( !checkbox_obj )
        return false;

    let ac_opened_filters = PHS_JSEN.load_storage( "__PHS_PACF_" );
    let opened_filters_obj = {};
    if( ac_opened_filters && ac_opened_filters.length > 0 )
        opened_filters_obj = JSON.parse( ac_opened_filters );

    if( checkbox_obj.is( ":checked" ) ) {
        opened_filters_obj[path_id] = true;
    } else {
        if( typeof opened_filters_obj[path_id] !== "undefined" )
            delete opened_filters_obj[path_id];
    }

    PHS_JSEN.save_storage( "__PHS_PACF_", JSON.stringify( opened_filters_obj ) );
}
function phs_filters_should_be_opened( path_id )
{
    let ac_opened_filters = PHS_JSEN.load_storage( "__PHS_PACF_" );
    if( !ac_opened_filters || ac_opened_filters.length <= 0 )
        return false;

    let opened_filters_obj = JSON.parse( ac_opened_filters );

    return (typeof opened_filters_obj[path_id] !== "undefined" && opened_filters_obj[path_id]);
}
</script>
<?php

    if( !empty( $flow_params_arr['after_filters_callback'] )
     && @is_callable( $flow_params_arr['after_filters_callback'] ) )
    {
        $callback_params = $paginator_obj->default_others_render_call_params();
        $callback_params['filters'] = $filters_arr;

        if( ($cell_content = @call_user_func( $flow_params_arr['after_filters_callback'], $callback_params )) === false
         || $cell_content === null )
            $cell_content = '[' . $this->_pt( 'Render after filters call failed.' ) . ']';

        echo $cell_content;
    }
