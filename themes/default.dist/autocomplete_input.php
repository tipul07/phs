<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_Ajax;

    if( !($id_id = $this->view_var( 'id_id' ))
     || !($id_name = $this->view_var( 'id_name' ))
     || !($text_id = $this->view_var( 'text_id' ))
     || !($text_name = $this->view_var( 'text_name' ))
     || !($onclick_attribute = $this->view_var( 'onclick_attribute' ))
     || !($onfocus_attribute = $this->view_var( 'onfocus_attribute' )) )
        return '<!-- Autocomplete not setup correctly -->';

    $allow_view_all = (bool)$this->view_var( 'allow_view_all' );

    if( !($css_style = $this->view_var( 'text_css_style' )) )
        $css_style = '';

    if( !($default_value = $this->view_var( 'default_value' )) )
        $default_value = 0;
?>
<input type="hidden" id="<?php echo $id_id?>" name="<?php echo $id_name?>" value="<?php echo form_str( $this->view_var( 'id_value' ) )?>" />
<div class="input-group phs_autocomplete_group">
    <?php
    if( $allow_view_all )
    {
        ?>
        <div class="input-group-prepend" id="<?php echo $text_id?>_prepend_icons">
            <div class="input-group-text">
                <a href="javascript:void(0)" class="fa fa-arrow-down"
                <?php echo $onclick_attribute?>="phs_autocomplete_trigger_search( '<?php echo $text_id?>', true )"
                <?php echo $onfocus_attribute?>="this.blur()"></a>
            </div>
        </div>
        <?php
    }
    ?>
    <input type="text" id="<?php echo $text_id?>" name="<?php echo $text_name?>" class="form-control <?php echo $this->view_var( 'text_css_classes' )?>"
           placeholder="<?php echo form_str( $this->view_var( 'text_placeholder' ) )?>"
           value="<?php echo form_str( $this->view_var( 'text_value' ) )?>" <?php echo (!empty( $css_style )?'style="'.$css_style.'"':'')?> />
    <div class="input-group-append" id="<?php echo $text_id?>_append_icons" style="display:none;">
        <div class="input-group-text">
            <a href="javascript:void(0)" class="fa fa-refresh"
            <?php echo $onclick_attribute?>="phs_autocomplete_input_reset( '<?php echo $id_id?>', '<?php echo $text_id?>', '<?php echo $this::_e( $default_value, '\'' )?>' )"
            <?php echo $onfocus_attribute?>="this.blur()"></a>
        </div>
    </div>
</div>
