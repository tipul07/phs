<?php
    /** @var \phs\system\core\views\PHS_View $this */
?>
<script id="PHS_RActive_autocomplete_inputs" type="text/html">
{{ # !hide_component }}
    <div class="phs_ractive phs_ractive_autocomplete_inputs clearfix">
    <input type="hidden" id="{{id_input_id}}" name="{{id_input_name}}" value="{{id_input_value}}" />

    <div class="input-group">
        {{ #if display_show_all && !text_is_readonly }}
        <div class="input-group-prepend">
            <span class="input-group-text"><span on-click="event_show_all_results"><span on-click="event_show_all_results_custom">
                <a href="javascript:void(0)" class="phs_ractive_autocomplete_showall fa fa-arrow-down" onfocus="this.blur()"></a>
            </span></span></span>
        </div>
        {{ /if }}
        {{ #if text_is_readonly }}
        <div class="input-group-prepend">
            <span class="input-group-text">
                <a href="javascript:void(0)" class="phs_ractive_autocomplete_reset fa fa-refresh" onfocus="this.blur()" on-click="@.do_reset_inputs()"></a>
            </span>
        </div>
        {{ /if }}
        <input type="text" id="{{text_input_id}}" name="{{text_input_name}}" class="{{text_input_css_classes.join(' ')}}"
               value="{{text_input_value}}" autocomplete="off" lazy="{{input_lazyness}}" twoway="{{text_input_twoway}}"
               {{#text_input_style}}style="{{text_input_style}}"{{/}} {{#text_is_readonly}}readonly="readonly"{{/}} />
    </div>
    {{ # show_filtered_items && !text_is_readonly }}
        <div class="phs_ractive_autocomplete_results">
        <div class="phs_ractive_autocomplete_items_list">
        {{ # filtered_items }}
            <div class="phs_ractive_autocomplete_item" on-click="@.select_item( id, input_title, this )">
                {{{ listing_title_html }}}
            </div>
        {{ / }}
        </div>
        <div class="phs_ractive_autocomplete_status">
            <?php echo $this->_pt( '%s results', '{{total_items_count}}' )?>
            <a href="javascript:void(0)" on-click="@.hide_filtered_items()"><?php echo $this->_pt( 'Close' )?></a>
        </div>
        </div>
    {{ / }}
    </div>
{{ / }}
</script>
