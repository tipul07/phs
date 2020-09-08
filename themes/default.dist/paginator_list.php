<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_Scope;
    use \phs\libraries\PHS_params;

    /** @var \phs\libraries\PHS_Paginator $paginator_obj */
    if( !($paginator_obj = $this->view_var( 'paginator' )) )
    {
        echo $this::_t( 'Rendered from outside of paginator.' );
        return;
    }

    if( !($model_obj = $paginator_obj->get_model()) )
        $model_obj = false;

    if( !($base_url = $paginator_obj->base_url()) )
        $base_url = '#';
    if( !($full_filters_url = $paginator_obj->get_full_url()) ) // [ 'include_filters' => false ] )) )
        $full_filters_url = '#';

    if( !($flow_params_arr = $paginator_obj->flow_params()) )
        $flow_params_arr = $paginator_obj->default_flow_params();

    if( !($pagination_arr = $paginator_obj->pagination_params()) )
        $pagination_arr = $paginator_obj->default_pagination_params();

    if( !($listing_form_name = $paginator_obj->get_listing_form_name()) )
        $listing_form_name = $flow_params_arr['form_prefix'].'paginator_list_form';
    if( !($bulk_select_name = $paginator_obj->get_bulk_action_select_name()) )
        $bulk_select_name = '';

    if( !($bulk_actions = $paginator_obj->get_bulk_actions())
     || !is_array( $bulk_actions ) )
        $bulk_actions = [];
    if( !($filters_arr = $paginator_obj->get_filters()) )
        $filters_arr = [];
    if( !($columns_arr = $paginator_obj->get_columns_for_scope()) )
        $columns_arr = [];
    if( !($records_arr = $paginator_obj->get_records()) )
        $records_arr = [];

    if( !($scope_arr = $paginator_obj->get_scope()) )
        $scope_arr = [];

    $per_page_options_arr = [ 20, 50, 100 ];

    $columns_count = 0;
    $current_scope = (int)PHS_Scope::current_scope();
    if( !empty( $columns_arr ) && is_array( $columns_arr ) )
    {
        foreach( $columns_arr as $column_arr )
        {
            if( !isset( $column_arr['column_colspan'] ) )
                $column_arr['column_colspan'] = 1;

            $columns_count += $column_arr['column_colspan'];
        }
    }

    $is_api_scope = ($current_scope === PHS_Scope::SCOPE_API);
    $api_listing_arr = [];

    //
    // Begin output caching so we don't send HTML to scopes which don't need it
    //
    if( $is_api_scope )
    {
        ob_start();
        $api_listing_arr = $paginator_obj->default_api_listing_response();
        $api_listing_arr['offset'] = $paginator_obj->pagination_params( 'offset' );
        $api_listing_arr['records_per_page'] = $paginator_obj->pagination_params( 'records_per_page' );
        $api_listing_arr['total_records'] = $paginator_obj->pagination_params( 'total_records' );
        $api_listing_arr['listing_records_count'] = $paginator_obj->pagination_params( 'listing_records_count' );
        $api_listing_arr['page'] = $paginator_obj->pagination_params( 'page' );
        $api_listing_arr['max_pages'] = $paginator_obj->pagination_params( 'max_pages' );
        $api_listing_arr['filters']['sort'] = $paginator_obj->pagination_params( 'sort' );
        $api_listing_arr['filters']['sort_by'] = $paginator_obj->pagination_params( 'sort_by' );
    }
    //
    //
    //
?>
<div class="list_container">
    <form method="post" id="<?php echo $listing_form_name?>" name="<?php echo $listing_form_name?>" action="<?php echo form_str( $full_filters_url )?>">
    <input type="hidden" name="foobar" value="1" />
    <input type="submit" id="foobar_submit" name="foobar_submit" value="Just Submit" style="display:none;" />

	<?php

	if( !empty( $bulk_actions )
	 && !empty( $bulk_select_name )
	 && (!empty( $flow_params_arr['display_top_bulk_actions'] )
			|| !empty( $flow_params_arr['display_bottom_bulk_actions'] )) )
	{
		$json_actions = array();
        $bulk_top_actions_arr = array();
        $bulk_bottom_actions_arr = array();
		foreach( $bulk_actions as $action )
		{
            if( empty( $action ) || !is_array( $action ) )
                continue;

            if( !empty( $action['display_in_top'] ) )
                $bulk_top_actions_arr[] = $action;
            if( !empty( $action['display_in_bottom'] ) )
                $bulk_bottom_actions_arr[] = $action;

			$json_actions[$action['action']] = $action;
		}

		// display js functionality for bulk actions
		?>
		<script type="text/javascript">
		var phs_paginator_bulk_actions = <?php echo @json_encode( $json_actions );?>;

		function submit_bulk_action( area )
		{
			if( area !== 'top' && area !== 'bottom' )
				return false;

			var bulk_select_obj = $('#<?php echo $bulk_select_name?>' + area);
			if( !bulk_select_obj )
				return false;

			return submit_bulk_action_with_name( bulk_select_obj.val() );
		}

		function submit_bulk_action_with_name( bulk_action )
		{
			if( !bulk_action
			 || !(bulk_action in phs_paginator_bulk_actions) )
			{
				alert( '<?php echo $this::_e( 'Please choose an action first.', '\'' )?>' );
				return false;
			}

			var action_func = false;
			if( typeof phs_paginator_bulk_actions !== "undefined"
			 && typeof phs_paginator_bulk_actions[bulk_action] !== "undefined"
			 && typeof phs_paginator_bulk_actions[bulk_action]["js_callback"] !== "undefined" )
				action_func = phs_paginator_bulk_actions[bulk_action]["js_callback"];

			if( action_func
			 && typeof window[action_func] === "function" )
				return eval( action_func+"()" );

			return phs_paginator_default_bulk_action( bulk_action );
		}
		</script>
		<?php
	}

	if( !empty( $flow_params_arr['display_top_bulk_actions'] )
	 && !empty( $bulk_select_name )
	 && !empty( $bulk_actions ) )
	{
		$select_name = $bulk_select_name.'top';
		$select_with_action = (!empty( $flow_params_arr['bulk_action_area'] ) && $flow_params_arr['bulk_action_area'] === 'top');

		if( empty( $bulk_top_actions_arr ) )
        {
            ?><input type="hidden" name="<?php echo $select_name?>" id="<?php echo $select_name?>" value="" /><?php
        } else
        {
            ?><div style="margin-bottom:5px;float:left;">
            <select name="<?php echo $select_name?>" id="<?php echo $select_name?>" class="chosen-select-nosearch" style="width:150px;">
                <option value=""><?php echo $this::_t( ' - Bulk Actions - ' )?></option>
                <?php
                foreach( $bulk_top_actions_arr as $action_arr )
                {
                    $selected_option = '';
                    if( $select_with_action
                    && !empty( $flow_params_arr['bulk_action'] )
                    && $action_arr['action'] === $flow_params_arr['bulk_action'] )
                        $selected_option = 'selected="selected"';

                    ?><option value="<?php echo $action_arr['action']?>" <?php echo $selected_option?>><?php echo $action_arr['display_name']?></option><?php
                }
                ?>
            </select>
            <input type="submit" class="btn btn-primary btn-small ignore_hidden_required" onclick="this.blur();return submit_bulk_action( 'top' );" value="<?php echo form_str( $this::_t( 'Apply' ) )?>" />
            </div>
            <?php
	    }
	}

	$per_page_var_name = $flow_params_arr['form_prefix'] . $pagination_arr['per_page_var_name'];

	?><div style="margin-bottom:5px;float:right;">
	<?php echo $this::_t( '%s per page', ucfirst( $flow_params_arr['term_plural'] ) )?>
	<select name="<?php echo $per_page_var_name?>top" id="<?php echo $per_page_var_name.'top'?>"
            onchange="$('#foobar_submit').click()" class="chosen-select-nosearch" style="width:80px;">
	<?php
		foreach( $per_page_options_arr as $per_page_option )
		{
			$selected_option = '';
			if( $per_page_option === (int)$pagination_arr['records_per_page'] )
				$selected_option = 'selected="selected"';

			?><option value="<?php echo $per_page_option?>" <?php echo $selected_option?>><?php echo $per_page_option?></option><?php
		}
	?>
	</select>
	</div>

	<div class="clearfix"></div>
	<?php

	if( !empty( $columns_count )
	 && !empty( $flow_params_arr['before_table_callback'] )
	 && is_callable( $flow_params_arr['before_table_callback'] ) )
	{
		$callback_params = $paginator_obj->default_others_render_call_params();
		$callback_params['columns'] = $columns_arr;
		$callback_params['filters'] = $filters_arr;

		if( ($cell_content = @call_user_func( $flow_params_arr['before_table_callback'], $callback_params )) === false
		 || $cell_content === null )
			$cell_content = '[' . $this::_t( 'Render before table call failed.' ) . ']';

		echo $cell_content;
	}

	?>

	<div>
	<table style="min-width:100%;margin-bottom:5px;" class="tgrid">
	<?php
	if( !$is_api_scope
    && !empty( $columns_count ) )
	{
		?>
		<thead>
		<tr>
		<?php
		$sort_url = exclude_params( $paginator_obj->get_full_url(), [ $flow_params_arr['form_prefix'] . 'sort', $flow_params_arr['form_prefix'] . 'sort_by' ] );
		$current_sort = $paginator_obj->pagination_params( 'sort' );
		$current_sort_by = $paginator_obj->pagination_params( 'sort_by' );

		foreach( $columns_arr as $column_arr )
		{
            if( empty( $column_arr ) || !is_array( $column_arr ) )
                continue;

            if( !empty( $column_arr['record_db_field'] ) )
				$field_name = $column_arr['record_db_field'];
			else
				$field_name = $column_arr['record_field'];

			?><th class="<?php echo (!empty( $column_arr['sortable'] )?'sortable':'').' '.$column_arr['extra_classes']?>"
                  <?php if( !empty( $column_arr['extra_style'] ) ) echo 'style="'.$column_arr['extra_style'].'"'; ?>
                  <?php if( !empty( $column_arr['column_colspan'] ) && $column_arr['column_colspan'] > 1 ) echo 'colspan="'.$column_arr['column_colspan'].'"'; ?>
                  <?php if( !empty( $column_arr['raw_attrs'] ) ) echo $column_arr['raw_attrs']; ?>
            ><?php

			if( ($checkbox_column_name = $paginator_obj->get_checkbox_name_for_column( $column_arr )) )
			{
				$checkbox_name_all = $checkbox_column_name.$paginator_obj::CHECKBOXES_COLUMN_ALL_SUFIX;
				$checkbox_checked = false;
				if( !empty( $scope_arr ) && is_array( $scope_arr )
				 && !empty( $scope_arr[$checkbox_name_all] ) )
					$checkbox_checked = true;

				?><span style="width:100%;">
				<span style="float:left;">
					<input type="checkbox" value="1" rel="skin_checkbox"
                           name="<?php echo $checkbox_name_all?>" id="<?php echo $checkbox_name_all?>"
                           <?php echo ($checkbox_checked?'checked="checked"':'')?>
                           onchange="phs_paginator_update_list_checkboxes( '<?php echo $this::_e( $checkbox_column_name, '\'' )?>', '<?php echo $this::_e( $checkbox_name_all, '\'' )?>' )"
                    />
				</span>
				<?php
			}

			if( !empty( $column_arr['sortable'] ) )
			{
				$column_sort_url = add_url_params( $sort_url, [
														$flow_params_arr['form_prefix'].'sort' => ($current_sort?'0':'1'),
														$flow_params_arr['form_prefix'].'sort_by' => $field_name
                ] );

				?><a href="<?php echo $column_sort_url?>"><?php
			}

			echo $column_arr['column_title'];

			if( !empty( $column_arr['sortable'] ) )
			{
				?></a><?php

				if( $current_sort_by === $field_name )
				{
					?> <i class="fa fa-caret-<?php echo ($current_sort?'down':'up')?>"></i><?php
				}
			}

			if( !empty( $checkbox_column_name ) )
			{
				?></span><?php
			}

            if( !empty( $column_arr['column_title_extra'] ) )
                echo $column_arr['column_title_extra'];

			?></th><?php
		}
		?>
		</tr>
		</thead>
		<?php
	}

	?><tbody><?php

	if( !$is_api_scope
     && !empty( $columns_count )
	 && !empty( $flow_params_arr['table_after_headers_callback'] )
	 && is_callable( $flow_params_arr['table_after_headers_callback'] ) )
	{
		?>
		<tr>
			<td colspan="<?php echo $columns_count?>"><?php

			$callback_params = $paginator_obj->default_others_render_call_params();
			$callback_params['columns'] = $columns_arr;
			$callback_params['filters'] = $filters_arr;

			if( ($cell_content = @call_user_func( $flow_params_arr['table_after_headers_callback'], $callback_params )) === false
			 || $cell_content === null )
				$cell_content = '[' . $this::_t( 'Render after headers call failed.' ) . ']';

			echo $cell_content;

			?></td>
		</tr>
		<?php
	}

	if( empty( $records_arr ) || !is_array( $records_arr ) )
	{
		if( $columns_count )
		{
			?>
			<tr>
				<td colspan="<?php echo $columns_count?>" style="text-align: center;"><p><?php

				if( !empty( $scope_arr ) )
					echo $this::_t( 'No %s found with specified filters.', $paginator_obj->flow_param( 'term_plural' ) );
				else
					echo $this::_t( 'No %s found in database.', $paginator_obj->flow_param( 'term_plural' ) );

				?></p></td>
			</tr>
			<?php
		}
	} elseif( $columns_count )
	{
		$knti = 0;
		$offset = $paginator_obj->pagination_params( 'offset' );
		foreach( $records_arr as $record_arr )
		{
			?><tr><?php

            $api_record = array();

			foreach( $columns_arr as $column_arr )
			{
			    if( empty( $column_arr ) || !is_array( $column_arr ) )
			        continue;

                $cell_render_params = $paginator_obj->default_cell_render_call_params();
                $cell_render_params['request_render_type'] = (!$is_api_scope?$paginator_obj::CELL_RENDER_HTML:$paginator_obj::CELL_RENDER_JSON);
                $cell_render_params['page_index'] = $knti;
                $cell_render_params['list_index'] = $offset + $knti;
                $cell_render_params['columns_count'] = $columns_count;
                $cell_render_params['record'] = $record_arr;
                $cell_render_params['column'] = $column_arr;
                $cell_render_params['table_field'] = false;
                $cell_render_params['preset_content'] = '';
                $cell_render_params['model_obj'] = $model_obj;
                $cell_render_params['paginator_obj'] = $paginator_obj;
                $cell_render_params['for_scope'] = $current_scope;

                $cell_content = $paginator_obj->render_column_for_record( $cell_render_params );

				if( $is_api_scope )
                {
                    if( ($api_pair = $paginator_obj->format_api_export( $cell_content, $column_arr, $current_scope )) )
                    {
                        $api_field_name = $api_pair['key'];
                        $api_field_content = $api_pair['value'];
                    } else
                    {
                        if( !($field_name = $paginator_obj->get_column_name( $column_arr, $current_scope )) )
                            continue;

                        $api_field_name = $field_name;
                        $api_field_content = $cell_content;
                    }

                    $api_field_name = str_replace( [ '.', '`' ], [ '_', '' ], $api_field_name );

                    $api_record[$api_field_name] = $api_field_content;
                } else
                {
                    if( $cell_content === null )
                        $cell_content = '!'.$this::_t( 'Failed rendering cell' ).'!';

                    ?><td class="<?php echo $column_arr['extra_records_classes']?>"
                        <?php if( !empty( $column_arr['extra_records_style'] ) ) echo 'style="'.$column_arr['extra_records_style'].'"';?>
                        <?php if( !empty( $column_arr['raw_records_attrs'] ) ) echo $column_arr['raw_records_attrs'];?>
                        ><?php echo $cell_content?></td><?php
                }
			}

            if( $is_api_scope )
                $api_listing_arr['list'][] = $api_record;

			?></tr><?php

			if( !$is_api_scope
            && !empty( $flow_params_arr['after_record_callback'] )
			&& is_callable( $flow_params_arr['after_record_callback'] ) )
			{
				$callback_params = $paginator_obj->default_cell_render_call_params();
                $callback_params['request_render_type'] = $paginator_obj::CELL_RENDER_HTML;
				$callback_params['page_index'] = $knti;
				$callback_params['list_index'] = $offset + $knti;
				$callback_params['columns_count'] = $columns_count;
				$callback_params['record'] = $record_arr;
				$callback_params['for_scope'] = $current_scope;

				if( ($row_content = @call_user_func( $flow_params_arr['after_record_callback'], $callback_params )) !== false
				 && $row_content !== null )
					echo $row_content;
			}

			$knti++;
		}
	}

	if( !$is_api_scope
     && !empty( $columns_count )
	 && !empty( $flow_params_arr['table_before_footer_callback'] )
	 && is_callable( $flow_params_arr['table_before_footer_callback'] ) )
	{
		?>
		<tr>
			<td colspan="<?php echo $columns_count?>"><?php

			$callback_params = $paginator_obj->default_others_render_call_params();
			$callback_params['columns'] = $columns_arr;
			$callback_params['filters'] = $filters_arr;

			if( ($cell_content = @call_user_func( $flow_params_arr['table_before_footer_callback'], $callback_params )) === false
			 || $cell_content === null )
				$cell_content = '[' . $this::_t( 'Render before footer call failed.' ) . ']';

			echo $cell_content;

			?></td>
		</tr>
		<?php
	}

	if( !$is_api_scope
    && $pagination_arr['max_pages'] > 1 )
	{
		$page_var_name = $flow_params_arr['form_prefix'].$pagination_arr['page_var_name'];

		$url_without_page = exclude_params( $full_filters_url, array( $page_var_name ) );

		// Display pages...
		$left_pages_no = (!empty( $pagination_arr['left_pages_no'] )?$pagination_arr['left_pages_no']:10);
		$right_pages_no = (!empty( $pagination_arr['right_pages_no'] )?$pagination_arr['right_pages_no']:10);

		$left_start = 0;
		$right_end = $pagination_arr['max_pages'];
		if( $pagination_arr['page'] - $left_pages_no > 0 )
			$left_start = $pagination_arr['page'] - $left_pages_no;
		if( $pagination_arr['page'] + $right_pages_no < $pagination_arr['max_pages'] )
			$right_end = $pagination_arr['page'] + $right_pages_no;

		?>
		<tr class="list_info_row">
			<td colspan="<?php echo $columns_count?>"><?php

		if( $pagination_arr['page'] > 0 )
		{
			?><a href="<?php echo add_url_params( $url_without_page, array( $page_var_name => ($pagination_arr['page']-1) ) );?>"><?php echo $this::_t( 'Previous page' )?></a> | <?php
		}

		if( $left_start > 0 )
		{
			?><a href="<?php echo add_url_params( $url_without_page, array( $page_var_name => 0 ) )?>">1</a> ... <?php
		}

		for( $knti = $left_start; $knti < $right_end; $knti++ )
		{
			if( $knti == $pagination_arr['page'] )
				echo ' <strong>'.($knti+1).'</strong> ';
			else
				echo ' <a href="'.add_url_params( $url_without_page, array( $page_var_name => $knti ) ).'">'.($knti+1).'</a> ';
		}

		if( $right_end < $pagination_arr['max_pages'] )
		{
			?> ... <a href="<?php echo add_url_params( $url_without_page, array( $page_var_name => ($pagination_arr['max_pages']-1) ) );?>"><?php echo $pagination_arr['max_pages']?></a><?php
		}

		if( $pagination_arr['page']+1 < $pagination_arr['max_pages'] )
		{
			?> | <a href="<?php echo add_url_params( $url_without_page, array( $page_var_name => ($pagination_arr['page']+1) ) );?>"><?php echo $this::_t( 'Next page' )?></a><?php
		}

		?>
		<div class="list_info"><?php
		echo $this::_t( 'Displaying %s / %s %s, page %s / %s, %s per page.',
					   $pagination_arr['listing_records_count'],
					   $pagination_arr['total_records'],
					   $flow_params_arr['term_plural'],
					   $pagination_arr['page'] + 1,
					   $pagination_arr['max_pages'],
					   $pagination_arr['records_per_page']
		);
		?></div>
		</td>
		</tr>
		<?php
	} elseif( !$is_api_scope )
    {
		?>
		<tr class="list_info_row">
		<td colspan="<?php echo $columns_count?>">
		<div class="list_info"><?php
		echo $this::_t( 'Displaying %s / %s %s, page %s / %s, %s per page.',
					   $pagination_arr['listing_records_count'],
					   $pagination_arr['total_records'],
					   $flow_params_arr['term_plural'],
					   $pagination_arr['page'] + 1,
					   $pagination_arr['max_pages'],
					   $pagination_arr['records_per_page']
		);
		?></div>
		</td>
		</tr>
		<?php
	}
	?>
	</tbody>
	</table>
	</div>

	<?php

	if( !$is_api_scope
     && !empty( $columns_count )
	 && !empty( $flow_params_arr['after_table_callback'] )
	 && is_callable( $flow_params_arr['after_table_callback'] ) )
	{
		$callback_params = $paginator_obj->default_others_render_call_params();
		$callback_params['columns'] = $columns_arr;
		$callback_params['filters'] = $filters_arr;

		if( ($cell_content = @call_user_func( $flow_params_arr['after_table_callback'], $callback_params )) === false
		 || $cell_content === null )
			$cell_content = '[' . $this::_t( 'Render after table call failed.' ) . ']';

		echo $cell_content;
	}

	if( !$is_api_scope
     && !empty( $flow_params_arr['display_bottom_bulk_actions'] )
	 && !empty( $bulk_select_name )
	 && !empty( $bulk_actions ) )
	{
		$select_name = $bulk_select_name.'bottom';
		$select_with_action = (!empty( $flow_params_arr['bulk_action_area'] ) && $flow_params_arr['bulk_action_area']==='bottom');

		if( empty( $bulk_bottom_actions_arr ) )
        {
            ?><input type="hidden" name="<?php echo $select_name?>" id="<?php echo $select_name?>" value="" /><?php
        } else
        {
            ?><div style="margin-bottom:5px;float:left;">
            <select name="<?php echo $select_name?>" id="<?php echo $select_name?>" class="chosen-select-nosearch" style="width:150px;">
                <option value=""><?php echo $this::_t( ' - Bulk Actions - ' )?></option>
                <?php
                    foreach( $bulk_bottom_actions_arr as $action_arr )
                    {
                        $selected_option = '';
                        if( $select_with_action
                        && !empty( $flow_params_arr['bulk_action'] )
                        && $action_arr['action'] == $flow_params_arr['bulk_action'] )
                            $selected_option = 'selected="selected"';

                        ?><option value="<?php echo $action_arr['action']?>" <?php echo $selected_option?>><?php echo $action_arr['display_name']?></option><?php
                    }
                ?>
            </select>
            <input type="submit" class="btn btn-primary btn-small ignore_hidden_required" onclick="this.blur();return submit_bulk_action( 'bottom' );" value="<?php echo form_str( $this::_t( 'Apply' ) )?>" />
            </div>
            <div class="clearfix"></div>
            <?php
	    }
	}
	?>

    </form>
</div>
<div class="clearfix"></div>
<?php
if( !function_exists( 'phs_paginator_display_js_functionality' ) )
{
    function phs_paginator_display_js_functionality( $this_object, $paginator_obj )
    {
        /** @var \phs\libraries\PHS_Paginator $paginator_obj */
        /** @var \phs\system\core\views\PHS_View $this_object */
        static $js_displayed = false;

        if( empty( $js_displayed ) )
        {
            $js_displayed = true;
            ?>
            <script type="text/javascript">
            function phs_paginator_update_list_checkboxes( checkbox_name, checkbox_name_all )
            {
                checkbox_all_obj = $( '#' + checkbox_name_all );

                if( !checkbox_all_obj )
                    return;

                var should_be_checked = checkbox_all_obj.is( ':checked' );

                checkbox_all_obj.closest( 'form' ).find( 'input:checkbox' ).each( function()
                {
                    var my_name = $( this ).attr( 'name' );

                    if( my_name == checkbox_name + '[]' )
                    {
                        $( this ).prop( 'checked', should_be_checked );
                    }
                } );
            }

            function phs_paginator_update_list_all_checkbox( checkbox_id, checkbox_id_all )
            {
                checkbox_all_obj = $( '#' + checkbox_id_all );
                checkbox_obj = $( '#' + checkbox_id );

                if( !checkbox_all_obj || !checkbox_obj )
                    return;

                var should_be_unchecked = !checkbox_obj.is( ':checked' );

                if( !should_be_unchecked )
                    return;

                checkbox_all_obj.prop( 'checked', false );
            }

            function phs_paginator_default_bulk_action( action )
            {
                if( !action
                 || !(action in phs_paginator_bulk_actions) )
                {
                    alert( "<?php echo $this_object::_e( 'Action not defined.', '"' )?>" );
                    return false;
                }

                var action_display_name = '[Not defined]';
                if( typeof phs_paginator_bulk_actions !== "undefined"
                 && typeof phs_paginator_bulk_actions[action] !== "undefined"
                 && typeof phs_paginator_bulk_actions[action]["display_name"] !== "undefined" )
                    action_display_name = phs_paginator_bulk_actions[action]['display_name'];

                var selected_records = -1;
                var checkboxes_list = [];
                if( typeof phs_paginator_bulk_actions !== "undefined"
                 && typeof phs_paginator_bulk_actions[action] !== "undefined"
                 && typeof phs_paginator_bulk_actions[action]["checkbox_column"] !== "undefined"
                 && (checkboxes_list = phs_paginator_get_checkboxes_checked( phs_paginator_bulk_actions[action]['checkbox_column'] )) )
                    selected_records = checkboxes_list.length;

                var confirm_text = "";
                if( selected_records === -1 )
                    confirm_text = "<?php echo sprintf( $this_object::_e( 'Are you sure you want to run action %s?', '"' ), '" + action_display_name + "' )?>";

                else
                {
                    if( selected_records <= 0 )
                    {
                        alert( "<?php echo sprintf( $this_object::_e( 'Please select records for which you want to run action %s first.', '"' ), '" + action_display_name + "' )?>" );
                        return false;
                    }

                    confirm_text = "<?php echo sprintf( $this_object::_e( 'Are you sure you want to run action %s on %s selected records?', '"' ),
                                                        '" + action_display_name + "', '" + selected_records + "' )?>";
                }

                return confirm( confirm_text );
            }

            function phs_paginator_get_checkboxes( column )
            {
                var checkboxes_list = $( "input[type='checkbox'][name='<?php echo @sprintf( $paginator_obj->get_checkbox_name_format(), '" + column + "' )?>[]']" );
                if( !checkboxes_list || !checkboxes_list.length )
                    return [];

                return checkboxes_list;
            }

            function phs_paginator_get_checkboxes_checked( column )
            {
                var checkboxes_list = phs_paginator_get_checkboxes( column );
                if( !checkboxes_list || !checkboxes_list.length )
                    return [];

                var list_length = checkboxes_list.length;

                var checkboxes_checked = [];
                for( var i = 0; i < list_length; i++ )
                {
                    if( $( checkboxes_list[i] ).is( ':checked' ) )
                        checkboxes_checked.push( checkboxes_list[i] );
                }

                return checkboxes_checked;
            }
            </script>
            <?php
        }
    }
}
    if( !$is_api_scope )
    {
        phs_paginator_display_js_functionality( $this, $paginator_obj );

        if( !empty( $flow_params_arr['after_full_list_callback'] )
        && is_callable( $flow_params_arr['after_full_list_callback'] ) )
        {
            $callback_params = $paginator_obj->default_others_render_call_params();
            $callback_params['columns'] = $columns_arr;
            $callback_params['filters'] = $filters_arr;

            if( ($end_list_content = @call_user_func( $flow_params_arr['after_full_list_callback'], $callback_params )) === false
             || $end_list_content === null )
                $end_list_content = '[' . $this::_t( 'Render after full list call failed.' ) . ']';

            echo $end_list_content;
        }
    }

//
// END output caching so we don't send HTML to scopes which don't need it
//
if( $is_api_scope )
{
    ob_end_clean();

    return $api_listing_arr;
}
//
//
//
