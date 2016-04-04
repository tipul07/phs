<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_params;

    /** @var \phs\libraries\PHS_Paginator $paginator_obj */
    if( !($paginator_obj = $this->context_var( 'paginator' )) )
    {
        echo $this::_t( 'Rendered from outside of paginator.' );
        return;
    }

    if( !($base_url = $paginator_obj->base_url()) )
        $base_url = '#';
    if( !($full_url = $paginator_obj->get_url_with_filters()) )
        $full_url = '#';

    if( !($flow_params_arr = $paginator_obj->flow_params()) )
        $flow_params_arr = $paginator_obj->default_flow_params();

    if( !($filters_arr = $paginator_obj->get_filters()) )
        $filters_arr = array();
    if( !($scope_arr = $paginator_obj->get_scope()) )
        $scope_arr = array();
    if( !($originals_arr = $paginator_obj->get_originals()) )
        $originals_arr = array();

    $show_filters = (PHS_params::_g( 'show_filters', PHS_params::T_INT )?true:false);
    $show_filters = true;
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="width:100%;min-width:1000px;margin: 0 auto;">
    <form id="<?php echo $flow_params_arr['form_prefix']?>paginator_filters_form" name="<?php echo $flow_params_arr['form_prefix']?>paginator_filters_form" action="<?php echo $base_url?>" method="post" class="wpcf7">
    <input type="hidden" name="foobar" value="1" />

        <div class="form_container responsive">

            <section class="heading-bordered">
                <h3><?php echo $this::_t( 'Filters' )?></h3>
            </section>
            
            <fieldset id="<?php echo $flow_params_arr['form_prefix']?>paginator_filters_form_inputs" style="display:<?php echo ($show_filters?'block':'none')?>;">
            <?php
            foreach( $filters_arr as $filter_details )
            {
                if( empty( $filter_details['var_name'] ) )
                    continue;

                $field_id = $flow_params_arr['form_prefix'].$filter_details['var_name'];
                $field_name = $field_id;
                $field_value = null;
                if( isset( $scope_arr[$filter_details['var_name']] ) )
                    $field_value = $scope_arr[$filter_details['var_name']];
                elseif( $filter_details['default'] !== null )
                    $field_value = $filter_details['default'];

                ?>
                <fieldset class="paginator_filter">
                    <label for="<?php echo $field_id?>"><?php echo $filter_details['display_name']?></label>
                    <div class="paginator_input"><?php

                    if( !empty( $filter_details['values_arr'] ) and is_array( $filter_details['values_arr'] ) )
                    {
                        ?><select id="<?php echo $field_id?>" name="<?php echo $field_name?>" class="wpcf7-select <?php echo $filter_details['extra_classes']?>" style="<?php echo $filter_details['extra_style']?>"><?php

                        foreach( $filter_details['values_arr'] as $key => $val )
                        {
                            ?><option value="<?php echo $key?>" <?php echo ($field_value==$key?'selected="selected"':'')?>><?php echo $val?></option><?php
                        }

                        ?></select><?php
                    } else
                    {
                        switch( $filter_details['type'] )
                        {
                            case PHS_params::T_DATE:
                                ?><input type="text" id="<?php echo $field_id?>" name="<?php echo $field_name?>" class="datepicker wpcf7-text <?php echo $filter_details['extra_classes']?>" value="<?php echo form_str( $field_value )?>" style="<?php echo $filter_details['extra_style']?>" /><?php
                            break;

                            case PHS_params::T_BOOL:
                                ?><input type="checkbox" id="<?php echo $field_id?>" name="<?php echo $field_name?>" class="wpcf7-text <?php echo $filter_details['extra_classes']?>" value="1" rel="skin_checkbox" <?php echo (!empty( $field_value )?'checked="checked"':'')?> style="<?php echo $filter_details['extra_style']?>" /><?php
                            break;

                            default:
                                ?><input type="text" id="<?php echo $field_id?>" name="<?php echo $field_name?>" class="wpcf7-text <?php echo $filter_details['extra_classes']?>" value="<?php echo form_str( $field_value )?>" style="<?php echo $filter_details['extra_style']?>" /><?php
                            break;
                        }
                    }

                    ?></div>
                </fieldset>
                <?php
            }
            ?>

            <div class="clearfix"></div>
            <div>
                <input type="submit" id="submit" name="submit" class="wpcf7-submit submit-protection" value="<?php echo $this::_te( 'Filter' )?>" />
                <input type="button" onclick="toggle_filters_inputs_and_text()" class="wpcf7-submit" value="<?php echo $this::_te( 'Hide Filters' )?>" style="margin-right:5px;" />
            </div>
            </div>

            <div id="<?php echo $flow_params_arr['form_prefix']?>paginator_filters_form_text" style="display:<?php echo (!$show_filters?'block':'none')?>;">
            <?php
            $filters_str_arr = array();
            foreach( $filters_arr as $filter_details )
            {
                if( empty( $filter_details['var_name'] ) )
                    continue;

                $field_value = null;
                if( isset( $scope_arr[$filter_details['var_name']] ) )
                    $field_value = $scope_arr[$filter_details['var_name']];
                elseif( $filter_details['default'] !== null and !empty( $filter_details['display_default_as_filter'] ) )
                    $field_value = $filter_details['default'];

                if( $field_value === null )
                    continue;

                $filters_str_arr[] = $filter_details['display_name'].': '.$field_value;
            }

            if( empty( $filters_str_arr ) )
                echo $this::_t( 'No filters set.' );
            else
                echo $this::_t( 'Filters' ).': '.implode( ', ', $filters_str_arr ).'.';
            ?>
            (<a href="javascript:void(0);" onclick="toggle_filters_inputs_and_text()">Show filters</a>)
            </div>

            <div class="clearfix"></div>

        </div>
    </form>
    <div class="clearfix"></div>
</div>
<div class="clearfix"></div>
<script type="text/javascript">
function toggle_filters_inputs_and_text()
{
    var inputs_obj = $('#<?php echo $flow_params_arr['form_prefix']?>paginator_filters_form_inputs');
    var text_obj = $('#<?php echo $flow_params_arr['form_prefix']?>paginator_filters_form_text');

    if( inputs_obj )
        inputs_obj.toggle();
    if( text_obj )
        text_obj.toggle();
}
</script>
