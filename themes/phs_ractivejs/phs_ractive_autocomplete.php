<script id="PHS_RActive_autocomplete_inputs" type="text/html">
{{ # !hide_component }}
    <div class="phs_ractive phs_ractive_autocomplete_inputs clearfix">
    <input type="hidden" id="{{id_input_id}}" name="{{id_input_name}}" value="{{id_input_value}}" />
    <input type="text" id="{{text_input_id}}" name="{{text_input_name}}" class="{{text_input_css_classes.join(' ')}}"
           value="{{text_input_value}}" autocomplete="off" lazy="{{input_lazyness}}" twoway="{{text_input_twoway}}"
           {{#text_input_style}}style="{{text_input_style}}"{{/}} {{#text_is_readonly}}readonly="readonly"{{/}} />
    {{ #if display_show_all && !text_is_readonly }}
    <a href="javascript:void(0)" class="action-icons phs_ractive_autocomplete_show_all fa fa-arrow-down" onfocus="this.blur()" on-click="@.do_show_all_results()"></a>
    {{ /if }}
    <a href="javascript:void(0)" class="action-icons phs_ractive_autocomplete_reset fa fa-refresh" onfocus="this.blur()" on-click="@.do_reset_inputs()"></a>
    {{ # show_filtered_items }}
        <div class="phs_ractive_autocomplete_results">
        <div class="phs_ractive_autocomplete_items_list">
        {{ # filtered_items }}
            <div class="phs_ractive_autocomplete_item" on-click="@.select_item( id, input_title )">
                {{{ listing_title_html }}}
            </div>
        {{ / }}
        </div>
        <div class="phs_ractive_autocomplete_status">
        {{total_items_count}} results
            <a href="javascript:void(0)" on-click="@.toggle( 'show_filtered_items' )">Close</a>
        </div>
        </div>
    {{ / }}
    </div>
{{ / }}
</script>
