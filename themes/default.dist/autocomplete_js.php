<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_ajax;

    if( !($id_id = $this->view_var( 'id_id' ))
     or !($text_id = $this->view_var( 'text_id' ))
     or !($route_arr = $this->view_var( 'route_arr' ))
     or !is_array( $route_arr ) )
        return '<!-- Autocomplete not setup correctly -->';

    if( !($route_params_arr = $this->view_var( 'route_params_arr' )) )
        $route_params_arr = false;
?>
<script type="text/javascript">
$(document).ready(function(){

    <?php
    if( $this->view_var( 'id_value' ) )
    {
        ?>phs_autocomplete_input_lock( '<?php echo $text_id?>' );<?php
    }
    ?>

    PHS_JSEN.do_autocomplete( "#<?php echo $text_id?>", {
        url: "<?php echo PHS_ajax::url( $route_arr, $route_params_arr )?>",
        autocomplete_obj: {
            minLength: <?php echo $this->view_var( 'min_text_length' )?>,
            select: function( event, ui )
            {
                $("#<?php echo $id_id?>").val( ui.item.id );
                $("#<?php echo $text_id?>").val( ui.item.label );

                phs_autocomplete_input_lock( '<?php echo $text_id?>' )
            }
        },
        ajax_options: {}
    }).autocomplete( "instance" )._renderItem = function( ul, item ) {
        return $( "<li>" )
                .append( "<a>" + item.label + "</a>" )
                .appendTo( ul );
    };

});
</script>
