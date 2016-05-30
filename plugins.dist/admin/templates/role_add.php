<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_utils;

    if( !($ru_slugs = $this->context_var( 'ru_slugs' )) )
        $ru_slugs = array();
    if( !($role_units_by_slug = $this->context_var( 'role_units_by_slug' )) )
        $role_units_by_slug = array();
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="min-width:100%;max-width:1000px;margin: 0 auto;">
    <form id="add_role_form" name="add_role_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'role_add' ) )?>" method="post" class="wpcf7">
        <input type="hidden" name="foobar" value="1" />

        <div class="form_container responsive" style="width: 650px;">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Add Role' )?></h3>
            </section>

            <fieldset class="lineform">
                <label for="name"><?php echo $this->_pt( 'Name' )?>:</label>
                <input type="text" id="name" name="name" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'name' ) )?>" style="width: 260px;" autocomplete="off" />
            </fieldset>

            <fieldset class="lineform">
                <label for="slug"><?php echo $this->_pt( 'Slug' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="slug" name="slug" class="wpcf7-text" required="required" value="<?php echo form_str( $this->context_var( 'slug' ) )?>" style="width: 260px;" autocomplete="off" /><br/>
                <small><?php echo $this->_pt( 'Slug should be unique. Once role is defined you cannot change this slug.' )?></small>
                </div>
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
                        <div style="float:left;"><input type="checkbox" id="ru_slug_<?php echo $unit_slug ?>" name="ru_slugs[]" class="wpcf7-text" value="<?php echo form_str( $unit_slug )?>" rel="skin_checkbox" <?php echo (in_array( $unit_slug, $ru_slugs ) ? 'checked="checked"' : '')?> /></div>
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
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection" value="<?php echo $this->_pte( 'Add role' )?>" />
            </fieldset>

        </div>
    </form>
</div>
<div class="clearfix"></div>
