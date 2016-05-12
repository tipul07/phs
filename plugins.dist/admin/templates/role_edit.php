<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_utils;

    if( !($role_slugs = $this->context_var( 'role_slugs' )) )
        $role_slugs = array();
    if( !($role_units_by_slug = $this->context_var( 'role_units_by_slug' )) )
        $role_units_by_slug = array();

    if( !($back_page = $this->context_var( 'back_page' )) )
        $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'roles_list' ) );
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:100%;max-width:1000px;margin: 0 auto;">
    <form id="edit_role_form" name="edit_role_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'edit_role' ), array( 'rid' => $this->context_var( 'rid' ) ) )?>" method="post" class="wpcf7">
        <input type="hidden" name="foobar" value="1" />
        <?php
        if( !empty( $back_page ) )
        {
            ?><input type="hidden" name="back_page" value="<?php echo form_str( safe_url( $back_page ) )?>" /><?php
        }
        ?>

        <div class="form_container responsive" style="width: 650px;">

            <?php
            if( !empty( $back_page ) )
            {
                ?><i class="fa fa-chevron-left"></i> <a href="<?php echo form_str( from_safe_url( $back_page ) ) ?>"><?php echo $this->_pt( 'Back' )?></a><?php
            }
            ?>

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Edit Role' )?></h3>
            </section>

            <fieldset class="lineform">
                <label for="name"><?php echo $this->_pt( 'Name' )?>:</label>
                <input type="text" id="name" name="name" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'name' ) )?>" style="width: 260px;" autocomplete="off" />
            </fieldset>

            <fieldset class="lineform">
                <label for="description"><?php echo $this->_pt( 'Description' )?>:</label>
                <input type="text" id="description" name="description" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'description' ) )?>" style="width: 400px;" autocomplete="off" />
            </fieldset>

            <fieldset class="lineform">
                <label for="email"><?php echo $this->_pt( 'Role Units' )?>:</label>
                <div class="lineform_line">
                <?php
                foreach( $role_units_by_slug as $unit_slug => $unit_arr )
                {
                    ?>
                    <div>
                        <div style="float:left;"><input type="checkbox" id="ru_slug_<?php echo $unit_slug ?>" name="ru_slugs[]" class="wpcf7-text" value="<?php echo form_str( $unit_slug )?>" rel="skin_checkbox" <?php echo (in_array( $unit_slug, $role_slugs ) ? 'checked="checked"' : '')?> /></div>
                        <label style="margin-left:5px;width: auto !important;float:left;" for="ru_slug_<?php echo $unit_slug ?>">
                            <?php echo $unit_arr['name']?>
                            <i class="fa fa-question-circle" title="<?php echo form_str( $unit_arr['description'] )?>"></i>
                        </label>
                    </div>
                    <div class="clearfix"></div>
                    <?php
                }
                ?>
                </div>
            </fieldset>

            <fieldset>
                <input type="submit" id="submit" name="submit" class="wpcf7-submit submit-protection" value="<?php echo $this->_pte( 'Save changes' )?>" />
            </fieldset>

        </div>
    </form>
</div>
<div class="clearfix"></div>
