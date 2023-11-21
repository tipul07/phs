{{ # messages }}
<div class="phs_ractive_notifications_container {{@.positional_class()}}">
    {{ # messages.success : item_id }}
    <div class="success-box" role="alert">
        {{ this }}
        <i class="fa fa-times phs_ractive_notifications_close" on-click="@.phs_remove_success_message( item_id )"></i>
    </div>
    {{ / }}
    {{ # messages.errors : item_id }}
    <div class="error-box">
        {{ this }}
        <i class="fa fa-times phs_ractive_notifications_close" on-click="@.phs_remove_error_message( item_id )"></i>
    </div>
    {{ / }}
    {{ # messages.warnings : item_id }}
    <div class="warning-box">
        {{ this }}
        <i class="fa fa-times phs_ractive_notifications_close" on-click="@.phs_remove_warning_message( item_id )"></i>
    </div>
    {{ / }}
</div>
{{ / }}
