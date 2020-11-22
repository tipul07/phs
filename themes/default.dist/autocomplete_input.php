<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_Ajax;

    if( !($id_id = $this->view_var( 'id_id' ))
     or !($id_name = $this->view_var( 'id_name' ))
     or !($text_id = $this->view_var( 'text_id' ))
     or !($text_name = $this->view_var( 'text_name' ))
     or !($onclick_attribute = $this->view_var( 'onclick_attribute' ))
     or !($onfocus_attribute = $this->view_var( 'onfocus_attribute' )) )
        return '<!-- Autocomplete not setup correctly -->';

    if( !($css_style = $this->view_var( 'text_css_style' )) )
        $css_style = '';

    $css_style .= 'width:90%;float:left;';
?>
<input type="hidden" id="<?php echo $id_id?>" name="<?php echo $id_name?>" value="<?php echo form_str( $this->view_var( 'id_value' ) )?>" />
<input type="text" id="<?php echo $text_id?>" name="<?php echo $text_name?>" class="<?php echo $this->view_var( 'text_css_classes' )?>" value="<?php echo form_str( $this->view_var( 'text_value' ) )?>" <?php echo (!empty( $css_style )?'style="'.$css_style.'"':'')?> />
<a href="javascript:void(0)" class="action-icons fa fa-refresh"
    <?php echo $onclick_attribute?>="phs_autocomplete_input_reset( '<?php echo $id_id?>', '<?php echo $text_id?>' )"
    <?php echo $onfocus_attribute?>="this.blur()"></a>
