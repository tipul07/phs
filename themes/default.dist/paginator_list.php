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

    if( !($model_obj = $paginator_obj->get_model()) )
        $model_obj = false;

    if( !($base_url = $paginator_obj->base_url()) )
        $base_url = '#';
    if( !($full_filters_url = $paginator_obj->get_full_url()) ) // array( 'include_filters' => false ) )) )
        $full_filters_url = '#';

    if( !($flow_params_arr = $paginator_obj->flow_params()) )
        $flow_params_arr = $paginator_obj->default_flow_params();

    if( !($pagination_arr = $paginator_obj->pagination_params()) )
        $pagination_arr = $paginator_obj->default_pagination_params();

    if( !($listing_form_name = $paginator_obj->get_filters_form_name()) )
        $listing_form_name = $flow_params_arr['form_prefix'].'paginator_list_form';
    if( !($bulk_select_name = $paginator_obj->get_bulk_action_select_name()) )
        $bulk_select_name = '';

    if( !($bulk_actions = $paginator_obj->get_bulk_actions())
     or !is_array( $bulk_actions ) )
        $bulk_actions = array();
    if( !($filters_arr = $paginator_obj->get_filters()) )
        $filters_arr = array();
    if( !($columns_arr = $paginator_obj->get_columns()) )
        $columns_arr = array();
    if( !($records_arr = $paginator_obj->get_records()) )
        $records_arr = array();

    if( !($scope_arr = $paginator_obj->get_scope()) )
        $scope_arr = array();

    $per_page_options_arr = array( 20, 50, 100 );
?>
<div class="list_container">
    <form id="<?php echo $listing_form_name?>" name="<?php echo $listing_form_name?>" action="<?php echo form_str( $full_filters_url )?>" method="post">
    <input type="hidden" name="foobar" value="1" />
    <input type="submit" id="foobar_submit" name="foobar_submit" value="Just Submit" style="display:none;" />

	<?php
		
	if( !empty( $bulk_actions )
	and !empty( $bulk_select_name )
	and (!empty( $flow_params_arr['display_top_bulk_actions'] )
			or !empty( $flow_params_arr['display_bottom_bulk_actions'] )) )
	{
		$json_actions = array();
		foreach( $bulk_actions as $action )
		{
			$json_actions[$action['action']] = $action;
		}

		// display js functionality for bulk actions
		?>
		<script type="text/javascript">
		var phs_paginator_bulk_actions = <?php echo @json_encode( $json_actions );?>;

		function submit_bulk_action( area )
		{
			if( area != 'top' && area != 'bottom' )
				return false;

			var bulk_select_obj = $('#<?php echo $bulk_select_name?>' + area);
			if( !bulk_select_obj )
				return false;

			var bulk_action = bulk_select_obj.val();
			if( !bulk_action
			 || !(bulk_action in phs_paginator_bulk_actions) )
			{
				alert( '<?php echo $this::_e( 'Please choose an action first.', '\'' )?>' );
				return false;
			}

			var action_func = false;
			if( phs_paginator_bulk_actions[bulk_action]['js_callback'] )
				action_func = phs_paginator_bulk_actions[bulk_action]['js_callback'];

			if( action_func
			 && typeof window[action_func] === 'function' )
				return eval( action_func+'()' );

			return phs_paginator_default_bulk_action( bulk_action );
		}
		</script>
		<?php
	}

	if( !empty( $flow_params_arr['display_top_bulk_actions'] )
	and !empty( $bulk_select_name )
	and !empty( $bulk_actions ) )
	{
		$select_name = $bulk_select_name.'top';
		$select_with_action = ((!empty( $flow_params_arr['bulk_action_area'] ) and $flow_params_arr['bulk_action_area']=='top')?true:false);

		?><div style="margin-bottom:5px;float:left;">
		<select name="<?php echo $select_name?>" id="<?php echo $select_name?>" class="chosen-select-nosearch" style="width:150px;">
			<option value=""><?php echo $this::_t( ' - Bulk Actions - ' )?></option>
			<?php
			foreach( $bulk_actions as $action_arr )
			{
				$selected_option = '';
				if( $select_with_action
				and !empty( $flow_params_arr['bulk_action'] )
				and $action_arr['action'] == $flow_params_arr['bulk_action'] )
					$selected_option = 'selected="selected"';

				?><option value="<?php echo $action_arr['action']?>" <?php echo $selected_option?>><?php echo $action_arr['display_name']?></option><?php
			}
			?>
		</select>
		<input type="submit" class="btn btn-primary btn-small" onclick="this.blur();return submit_bulk_action( 'top' );" value="<?php echo form_str( $this::_t( 'Apply' ) )?>" />
		</div>
		<?php
	}

	$per_page_var_name = $flow_params_arr['form_prefix'] . $pagination_arr['per_page_var_name'];

	?><div style="margin-bottom:5px;float:right;">
	<?php echo $this::_t( '%s per page', ucfirst( $flow_params_arr['term_plural'] ) )?>
	<select name="<?php echo $per_page_var_name?>top" id="<?php echo $per_page_var_name.'top'?>" onchange="$('#foobar_submit').click()" class="chosen-select-nosearch" style="width:80px;">
	<?php
		foreach( $per_page_options_arr as $per_page_option )
		{
			$selected_option = '';
			if( $per_page_option == $pagination_arr['records_per_page'] )
				$selected_option = 'selected="selected"';

			?><option value="<?php echo $per_page_option?>" <?php echo $selected_option?>><?php echo $per_page_option?></option><?php
		}
	?>
	</select>
	</div>

	<div class="clearfix"></div>
	<?php

	if( !empty( $columns_count )
	and !empty( $flow_params_arr['before_table_callback'] )
	and is_callable( $flow_params_arr['before_table_callback'] ) )
	{
		$callback_params = $paginator_obj->default_others_render_call_params();
		$callback_params['columns'] = $columns_arr;
		$callback_params['filters'] = $filters_arr;

		if( ($cell_content = @call_user_func( $flow_params_arr['before_table_callback'], $callback_params )) === false
		 or $cell_content === null )
			$cell_content = '[' . $this::_t( 'Render before table call failed.' ) . ']';

		echo $cell_content;
	}

	?>

	<div>
	<table style="min-width:100%;margin-bottom:5px;" class="tgrid">
	<?php
	$columns_count = 0;
	if( !empty( $columns_arr ) and is_array( $columns_arr ) )
	{
		$columns_count = count( $columns_arr );
		?>
		<thead>
		<tr>
		<?php
		$sort_url = exclude_params( $paginator_obj->get_full_url(), array( $flow_params_arr['form_prefix'].'sort', $flow_params_arr['form_prefix'].'sort_by' ) );
		$current_sort = $paginator_obj->pagination_params( 'sort' );
		$current_sort_by = $paginator_obj->pagination_params( 'sort_by' );

		foreach( $columns_arr as $column_arr )
		{
			if( !empty( $column_arr['record_db_field'] ) )
				$field_name = $column_arr['record_db_field'];
			else
				$field_name = $column_arr['record_field'];

			?><th class="<?php echo (!empty( $column_arr['sortable'] )?'sortable':'').' '.$column_arr['extra_classes']?>" <?php if( !empty( $column_arr['extra_style'] ) ) echo 'style="'.$column_arr['extra_style'].'"' ?>><?php

			if( ($checkbox_column_name = $paginator_obj->get_checkbox_name_for_column( $column_arr )) )
			{
				$checkbox_name_all = $checkbox_column_name.$paginator_obj::CHECKBOXES_COLUMN_ALL_SUFIX;
				$checkbox_checked = false;
				if( !empty( $scope_arr ) and is_array( $scope_arr )
				and !empty( $scope_arr[$checkbox_name_all] ) )
					$checkbox_checked = true;

				?><span style="width:100%;">
				<span style="float:left;">
					<input type="checkbox" value="1" name="<?php echo $checkbox_name_all?>" id="<?php echo $checkbox_name_all?>" rel="skin_checkbox" <?php echo ($checkbox_checked?'checked="checked"':'')?> onchange="phs_paginator_update_list_checkboxes( '<?php echo $this::_e( $checkbox_column_name, '\'' )?>', '<?php echo $this::_e( $checkbox_name_all, '\'' )?>' )" />
				</span>
				<?php
			}

			if( !empty( $column_arr['sortable'] ) )
			{
				$column_sort_url = add_url_params( $sort_url, array(
														$flow_params_arr['form_prefix'].'sort' => ($current_sort?'0':'1'),
														$flow_params_arr['form_prefix'].'sort_by' => $field_name
				) );

				?><a href="<?php echo $column_sort_url?>"><?php
			}

			echo $column_arr['column_title'];

			if( !empty( $column_arr['sortable'] ) )
			{
				?></a><?php

				if( $current_sort_by == $field_name )
				{
					?> <i class="fa fa-caret-<?php echo ($current_sort?'down':'up')?>"></i><?php
				}
			}

			if( !empty( $checkbox_column_name ) )
			{
				?></span><?php
			}

			?></th><?php
		}
		?>
		</tr>
		</thead>
		<?php
	}

	?><tbody><?php

	if( !empty( $columns_count )
	and !empty( $flow_params_arr['table_after_headers_callback'] )
	and is_callable( $flow_params_arr['table_after_headers_callback'] ) )
	{
		?>
		<tr>
			<td colspan="<?php echo $columns_count?>"><?php

			$callback_params = $paginator_obj->default_others_render_call_params();
			$callback_params['columns'] = $columns_arr;
			$callback_params['filters'] = $filters_arr;

			if( ($cell_content = @call_user_func( $flow_params_arr['table_after_headers_callback'], $callback_params )) === false
			 or $cell_content === null )
				$cell_content = '[' . $this::_t( 'Render after headers call failed.' ) . ']';

			echo $cell_content;

			?></td>
		</tr>
		<?php
	}

	if( empty( $records_arr ) or !is_array( $records_arr ) )
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

			foreach( $columns_arr as $column_arr )
			{
				if( !empty( $column_arr['record_field'] ) or !empty( $column_arr['record_db_field'] ) )
				{
					if( !empty( $column_arr['record_db_field'] ) )
						$field_name = $column_arr['record_db_field'];
					else
						$field_name = $column_arr['record_field'];
				}

				$cell_content = false;
				if( empty( $column_arr['record_field'] )
				and empty( $column_arr['record_db_field'] )
				and empty( $column_arr['display_callback'] ) )
					$cell_content = '['.$this::_t( 'Bad column setup' ).']';

				elseif( !empty( $column_arr['display_key_value'] )
					and is_array( $column_arr['display_key_value'] )
					and !empty( $field_name )
					and isset( $record_arr[$field_name] )
					and isset( $column_arr['display_key_value'][$record_arr[$field_name]] ) )
					$cell_content = $column_arr['display_key_value'][$record_arr[$field_name]];

				elseif( !empty( $model_obj )
					and !empty( $field_name )
					and isset( $record_arr[$field_name] )
					and ($field_details = $model_obj->table_field_details( $field_name ))
					and is_array( $field_details ) )
				{
					switch( $field_details['type'] )
					{
						case $model_obj::FTYPE_DATETIME:
						case $model_obj::FTYPE_DATE:
							if( empty_db_date( $record_arr[$field_name] ) )
								$cell_content = $this::_t( 'N/A' );
							elseif( !empty( $column_arr['date_format'] ) )
								$cell_content = @date( $column_arr['date_format'], parse_db_date( $record_arr[$field_name] ) );
						break;
					}
				}

				if( $cell_content === false
				and !empty( $field_name )
				and isset( $record_arr[$field_name] ) )
					$cell_content = $record_arr[$field_name];

				if( $cell_content === false )
				{
					if( !empty( $column_arr['invalid_value'] ) )
						$cell_content = $column_arr['invalid_value'];
					else
						$cell_content = '(???)';
				}

				if( !empty( $column_arr['display_callback'] ) )
				{
					if( !@is_callable( $column_arr['display_callback'] ) )
						$cell_content = '[' . $this::_t( 'Cell callback failed.' ) . ']';

					else
					{
						if( empty( $field_name )
                         or !isset( $record_arr[$field_name] )
						 or !($field_details = $model_obj->table_field_details( $field_name ))
						 or !is_array( $field_details ) )
							$field_details = false;

						$cell_callback_params                   = $paginator_obj->default_cell_render_call_params();
						$cell_callback_params['page_index']     = $knti;
						$cell_callback_params['list_index']     = $offset + $knti;
						$cell_callback_params['columns_count']  = $columns_count;
						$cell_callback_params['record']         = $record_arr;
						$cell_callback_params['column']         = $column_arr;
						$cell_callback_params['table_field']    = $field_details;
						$cell_callback_params['preset_content'] = $cell_content;
						$cell_callback_params['model_obj']      = $model_obj;

						if( ($cell_content = @call_user_func( $column_arr['display_callback'], $cell_callback_params )) === false
						 or $cell_content === null )
							$cell_content = '[' . $this::_t( 'Render cell call failed.' ) . ']';
					}
				}

				// Allow display_callback parameter on checkbox fields...
				$checkbox_callback = array( $paginator_obj, 'display_checkbox_column' );
				if( $paginator_obj->get_checkbox_name_for_column( $column_arr )
				and is_callable( $checkbox_callback ) )
				{
					if( empty( $field_name )
                     or !isset( $record_arr[$field_name] )
					 or !($field_details = $model_obj->table_field_details( $field_name ))
					 or !is_array( $field_details ) )
						$field_details = false;

					$cell_callback_params                   = $paginator_obj->default_cell_render_call_params();
					$cell_callback_params['page_index']     = $knti;
					$cell_callback_params['list_index']     = $offset + $knti;
					$cell_callback_params['columns_count']  = $columns_count;
					$cell_callback_params['record']         = $record_arr;
					$cell_callback_params['column']         = $column_arr;
					$cell_callback_params['table_field']    = $field_details;
					$cell_callback_params['preset_content'] = $cell_content;
					$cell_callback_params['model_obj']      = $model_obj;

					if( ($checkbox_content = @call_user_func( $checkbox_callback, $cell_callback_params )) !== false
					and $checkbox_content !== null and is_string( $checkbox_content ) )
						$cell_content = $checkbox_content;
				}

				?><td class="<?php echo $column_arr['extra_records_classes']?>" <?php if (!empty($column_arr['extra_records_style'])) echo 'style="'.$column_arr['extra_records_style'].'"'?>><?php echo $cell_content?></td><?php
			}

			?></tr><?php

			if( !empty( $flow_params_arr['after_record_callback'] )
			and is_callable( $flow_params_arr['after_record_callback'] ) )
			{
				$callback_params                   = $paginator_obj->default_cell_render_call_params();
				$callback_params['page_index']     = $knti;
				$callback_params['list_index']     = $offset + $knti;
				$callback_params['columns_count']  = $columns_count;
				$callback_params['record']         = $record_arr;

				if( ($row_content = @call_user_func( $flow_params_arr['after_record_callback'], $callback_params )) !== false
				and $row_content !== null )
					echo $row_content;
			}

			$knti++;
		}
	}

	if( !empty( $columns_count )
	and !empty( $flow_params_arr['table_before_footer_callback'] )
	and is_callable( $flow_params_arr['table_before_footer_callback'] ) )
	{
		?>
		<tr>
			<td colspan="<?php echo $columns_count?>"><?php

			$callback_params = $paginator_obj->default_others_render_call_params();
			$callback_params['columns'] = $columns_arr;
			$callback_params['filters'] = $filters_arr;

			if( ($cell_content = @call_user_func( $flow_params_arr['table_before_footer_callback'], $callback_params )) === false
			 or $cell_content === null )
				$cell_content = '[' . $this::_t( 'Render before footer call failed.' ) . ']';

			echo $cell_content;

			?></td>
		</tr>
		<?php
	}

	if( $pagination_arr['max_pages'] > 1 )
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
	} else
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

	if( !empty( $columns_count )
	and !empty( $flow_params_arr['after_table_callback'] )
	and is_callable( $flow_params_arr['after_table_callback'] ) )
	{
		$callback_params = $paginator_obj->default_others_render_call_params();
		$callback_params['columns'] = $columns_arr;
		$callback_params['filters'] = $filters_arr;

		if( ($cell_content = @call_user_func( $flow_params_arr['after_table_callback'], $callback_params )) === false
		 or $cell_content === null )
			$cell_content = '[' . $this::_t( 'Render after table call failed.' ) . ']';

		echo $cell_content;
	}

	if( !empty( $flow_params_arr['display_top_bulk_actions'] )
	and !empty( $bulk_select_name )
	and !empty( $bulk_actions ) )
	{
		$select_name = $bulk_select_name.'bottom';
		$select_with_action = ((!empty( $flow_params_arr['bulk_action_area'] ) and $flow_params_arr['bulk_action_area']=='bottom')?true:false);

		?><div style="margin-bottom:5px;float:left;">
		<select name="<?php echo $select_name?>" id="<?php echo $select_name?>" class="chosen-select-nosearch" style="width:150px;">
			<option value=""><?php echo $this::_t( ' - Bulk Actions - ' )?></option>
			<?php
				foreach( $bulk_actions as $action_arr )
				{
					$selected_option = '';
					if( $select_with_action
					and !empty( $flow_params_arr['bulk_action'] )
					and $action_arr['action'] == $flow_params_arr['bulk_action'] )
						$selected_option = 'selected="selected"';

					?><option value="<?php echo $action_arr['action']?>" <?php echo $selected_option?>><?php echo $action_arr['display_name']?></option><?php
				}
			?>
		</select>
		<input type="submit" class="btn btn-primary btn-small" onclick="this.blur();return submit_bulk_action( 'bottom' );" value="<?php echo form_str( $this::_t( 'Apply' ) )?>" />
		</div>
		<div class="clearfix"></div>
		<?php
	}
	?>

    </form>
</div>
<div class="clearfix"></div>
<?php
if( !function_exists( 'display_js_functionality' ) )
{
    function display_js_functionality( $this_object, $paginator_obj )
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
                if( phs_paginator_bulk_actions[action]['display_name'] != "undefined" )
                    action_display_name = phs_paginator_bulk_actions[action]['display_name'];

                var selected_records = -1;
                var checkboxes_list = [];
                if( phs_paginator_bulk_actions[action]['checkbox_column'] != "undefined"
                 && (checkboxes_list = phs_paginator_get_checkboxes_checked( phs_paginator_bulk_actions[action]['checkbox_column'] )) )
                    selected_records = checkboxes_list.length;

                var confirm_text = "";
                if( selected_records == -1 )
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

                if( !checkboxes_list && !checkboxes_list.length )
                    return [];

                return checkboxes_list;
            }

            function phs_paginator_get_checkboxes_checked( column )
            {
                var checkboxes_list = phs_paginator_get_checkboxes( column );

                if( !checkboxes_list && !checkboxes_list.length )
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

display_js_functionality( $this, $paginator_obj );

    if( !empty( $flow_params_arr['after_full_list_callback'] )
    and is_callable( $flow_params_arr['after_full_list_callback'] ) )
    {
        $callback_params = $paginator_obj->default_others_render_call_params();
        $callback_params['columns'] = $columns_arr;
        $callback_params['filters'] = $filters_arr;

        if( ($end_list_content = @call_user_func( $flow_params_arr['after_full_list_callback'], $callback_params )) === false
         or $end_list_content === null )
            $end_list_content = '[' . $this::_t( 'Render after full list call failed.' ) . ']';

        echo $end_list_content;
    }
