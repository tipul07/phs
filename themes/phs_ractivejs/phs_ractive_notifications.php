{{ # messages }}
<div class="phs_ractive_notifications_container {{@.positional_class()}}">
    {{ # messages.success : item_id }}
    <div class="phs_ractive_notifications_box phs_ractive_notifications_success">
        <div class="phs_ractive_notifications_text">{{ this }}</div>
        <div class="phs_ractive_notifications_close" on-click="@.phs_remove_success_message( item_id )">Close</div>
    </div>
    {{ / }}
    {{ # messages.errors : item_id }}
    <div class="phs_ractive_notifications_box phs_ractive_notifications_error">
        <div class="phs_ractive_notifications_text">{{ this }}</div>
        <div class="phs_ractive_notifications_close" on-click="@.phs_remove_error_message( item_id )">Close</div>
    </div>
    {{ / }}
    {{ # messages.warnings : item_id }}
    <div class="phs_ractive_notifications_box phs_ractive_notifications_warning">
        <div class="phs_ractive_notifications_text">{{ this }}</div>
        <div class="phs_ractive_notifications_close" on-click="@.phs_remove_warning_message( item_id )">Close</div>
    </div>
    {{ / }}
</div>
{{ / }}
