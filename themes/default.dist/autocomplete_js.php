<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Ajax;

if (!($id_id = $this->view_var('id_id'))
 || !($text_id = $this->view_var('text_id'))
 || !($route_arr = $this->view_var('route_arr'))
 || !is_array($route_arr)) {
    return '<!-- Autocomplete not setup correctly -->';
}

if (!($route_params_arr = $this->view_var('route_params_arr'))) {
    $route_params_arr = false;
}
if (!($include_js_script_tags = $this->view_var('include_js_script_tags'))) {
    $include_js_script_tags = false;
}
if (!($include_js_on_ready = $this->view_var('include_js_on_ready'))) {
    $include_js_on_ready = false;
}

if (!($show_loading_animation = $this->view_var('show_loading_animation'))) {
    $show_loading_animation = false;
} else {
    $show_loading_animation = true;
}
if (!($loading_animation_class = $this->view_var('loading_animation_class'))) {
    $loading_animation_class = '';
}

$allow_view_all = (bool)$this->view_var('allow_view_all');
if ($allow_view_all
 || !($min_text_length = $this->view_var('min_text_length'))) {
    $min_text_length = 0;
} else {
    $min_text_length = (int)$min_text_length;
}

if ($include_js_script_tags) {
    ?><script type="text/javascript">
<?php
}

if ($include_js_on_ready) {
    ?>$(document).ready(function(){
<?php
}

$id_value = $this->view_var('id_value');
$default_value = $this->view_var('default_value');

if ($id_value !== $default_value
 && $this->view_var('lock_on_init')) {
    ?>phs_autocomplete_input_lock( '<?php echo $text_id; ?>' );<?php
}
?>

    PHS_JSEN.do_autocomplete( "#<?php echo $text_id; ?>", {
        url: "<?php echo PHS_Ajax::url($route_arr, $route_params_arr); ?>",
        show_loading_animation: <?php echo $show_loading_animation ? 'true' : 'false'; ?>,
        <?php
    if (!empty($loading_animation_class)) {
        ?>loading_animation_class: "<?php echo $loading_animation_class; ?>",
            <?php
    }
?>
        autocomplete_obj: {
            minLength: <?php echo $min_text_length; ?>,
            select: function( event, ui ) {
                $("#<?php echo $id_id; ?>").val( ui.item.id );
                $("#<?php echo $text_id; ?>").val( ui.item.label );

                phs_autocomplete_input_lock( '<?php echo $text_id; ?>' )
            }
        },
        ajax_options: {}
    }).autocomplete( "instance" )._renderItem = function( ul, item ) {
        return $( "<li>" )
                .append( "<a>" + item.label + "</a>" )
                .appendTo( ul );
    };
<?php
if ($include_js_on_ready) {
    ?>});
<?php
}
if ($include_js_script_tags) {
    ?></script>
<?php
}
