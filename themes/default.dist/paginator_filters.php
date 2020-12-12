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

    $show_filters = (PHS_Params::_g( 'show_filters', PHS_Params::T_INT )?true:false);

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
                 || !empty( $filter_details['hidden_filter'] ))
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

                        ?> <a href="javascript:void(0)" onclick="clear_filter_value( '<?php echo $field_id?>' )"><i class="fa fa-times-circle" title="<?php echo $this->_pt( 'Clear filter' )?>"></i></a> <?php

                    ?></label>
                    <div class="paginator_input"><?php

                    if( !empty( $filter_details['values_arr'] ) && is_array( $filter_details['values_arr'] ) )
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

                if( isset( $scope_arr[$filter_details['var_name']] )
                 || ($filter_details['default'] !== null && !empty( $filter_details['display_default_as_filter'] )) )
                    $filters_display_arr[] = '<em>'.$filter_details['display_name'].'</em>: '.$field_value_display;
            }
            ?>

            <div class="clearfix"></div>
            <div>
                <input type="submit" id="submit" name="submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Filter' )?>" />
                <input type="button" onclick="toggle_filters_inputs_and_text()" class="btn btn-primary" value="<?php echo $this->_pte( 'Hide Filters' )?>" style="margin-right:5px;" />
            </div>
            </div>

            <div id="<?php echo $filters_form_name?>_text" style="display:<?php echo (!$show_filters?'block':'none')?>;">
            <?php
            $filters_str_arr = [];
            foreach( $filters_arr as $filter_details )
            {
                if( empty( $filter_details['var_name'] ) )
                    continue;

                $field_value = null;
                if( isset( $scope_arr[$filter_details['var_name']] ) )
                    $field_value = $scope_arr[$filter_details['var_name']];
                elseif( $filter_details['default'] !== null && !empty( $filter_details['display_default_as_filter'] ) )
                    $field_value = $filter_details['default'];

                if( $field_value === null )
                    continue;

                if( is_array( $field_value ) )
                    $field_value = implode( ',', $field_value );

                $filters_str_arr[] = $filter_details['display_name'].': '.$field_value;
            }

            if( empty( $filters_display_arr ) )
                echo $this->_pt( 'No filters set.' );
            else
                echo '<strong>'.$this->_pt( 'Current filters' ).'</strong> - '.implode( ', ', $filters_display_arr ).'.';
            ?>
            (<a href="javascript:void(0);" onclick="toggle_filters_inputs_and_text()"><?php echo $this->_pt( 'Show filters' )?></a>)
            </div>

            <div class="clearfix"></div>

        </div>
    </form>
    <div class="clearfix"></div>
</div>
<div class="clearfix"></div>
<script type="text/javascript">
$(document).ready(function(){
    $(".phs_filter_datepicker").datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        showButtonPanel: true
    });
});
function clear_filter_value( obj_id )
{
    var obj = $("#"+obj_id);
    if( !obj )
        return;

    var obj_type = obj.prop( 'type' );
    if( obj_type != "select-one" && obj_type != "select-multiple" )
        obj.val( '' );

    else
    {
        var default_val = "";
        if( obj[0] && obj[0][0] )
            default_val = obj[0][0].value;

        obj.val( default_val );

        obj.trigger( "chosen:updated" );
    }
}
function toggle_filters_inputs_and_text()
{
    var inputs_obj = $('#<?php echo $filters_form_name?>_inputs');
    var text_obj = $('#<?php echo $filters_form_name?>_text');

    if( inputs_obj )
        inputs_obj.slideToggle('fast');
    if( text_obj )
        text_obj.slideToggle('fast');
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
