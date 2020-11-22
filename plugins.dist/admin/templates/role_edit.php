<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Utils;

    /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
    if( !($plugins_model = $this->view_var( 'plugins_model' )) )
        return $this->_pt( 'Couldn\'t load plugins model.' );

    if( !($ru_slugs = $this->view_var( 'ru_slugs' )) )
        $ru_slugs = array();
    if( !($role_units_by_slug = $this->view_var( 'role_units_by_slug' )) )
        $role_units_by_slug = array();

    if( !($back_page = $this->view_var( 'back_page' )) )
        $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'roles_list' ) );
?>
<div style="min-width:100%;max-width:1000px;margin: 0 auto;">
    <form id="edit_role_form" name="edit_role_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'role_edit' ), array( 'rid' => $this->view_var( 'rid' ) ) )?>" method="post">
        <input type="hidden" name="foobar" value="1" />
        <?php
        if( !empty( $back_page ) )
        {
            ?><input type="hidden" name="back_page" value="<?php echo form_str( safe_url( $back_page ) )?>" /><?php
        }
        ?>

        <div class="form_container" style="width: 700px;">

            <?php
            if( !empty( $back_page ) )
            {
                ?><i class="fa fa-chevron-left"></i> <a href="<?php echo form_str( from_safe_url( $back_page ) ) ?>"><?php echo $this->_pt( 'Back' )?></a><?php
            }
            ?>

            <section class="heading-bordered">
                <h3><?php echo $this->_pt( 'Edit Role' )?></h3>
            </section>

            <fieldset class="form-group">
                <label for="name"><?php echo $this->_pt( 'Name' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="name" name="name" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'name' ) )?>" style="width: 260px;" autocomplete="off" />
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="slug"><?php echo $this->_pt( 'Slug' )?>:</label>
                <div class="lineform_line">
                <?php echo $this->view_var( 'slug' )?>
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="description"><?php echo $this->_pt( 'Description' )?>:</label>
                <div class="lineform_line">
                <input type="text" id="description" name="description" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'description' ) )?>" style="width: 400px;" autocomplete="off" />
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="email"><?php echo $this->_pt( 'Role Units' )?>:</label>
                <div class="lineform_line">
                <?php
                $old_plugin = false;
                foreach( $role_units_by_slug as $unit_slug => $unit_arr )
                {
                    if( $old_plugin !== $unit_arr['plugin'] )
                    {
                        if( !($plugin_name = $plugins_model->get_plugin_name_by_slug( $unit_arr['plugin'] )) )
                            $plugin_name = $this->_pt( 'Manually added' );

                        if( $old_plugin !== false )
                        {
                            ?><div style="margin-bottom:10px;"></div><?php
                        }
                        ?>
                        <section class="heading-bordered">
                            <h4><?php echo $plugin_name?></h4>
                        </section>
                        <?php
                        $old_plugin = $unit_arr['plugin'];
                    }
                    ?>
                    <div class="clearfix">
                        <div style="float:left;"><input type="checkbox" id="ru_slug_<?php echo $unit_slug ?>" name="ru_slugs[]" value="<?php echo form_str( $unit_slug )?>" rel="skin_checkbox" <?php echo (in_array( $unit_slug, $ru_slugs ) ? 'checked="checked"' : '')?> /></div>
                        <label style="margin-left:5px;width: auto !important;max-width: none !important;float:left;" for="ru_slug_<?php echo $unit_slug ?>">
                            <?php echo $unit_arr['name']?>
                            <i class="fa fa-question-circle" title="<?php echo form_str( $unit_arr['description'] )?>"></i>
                        </label>
                    </div>
                    <?php
                }
                ?>
                </div>
            </fieldset>

            <fieldset>
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Save changes' )?>" />
            </fieldset>

        </div>
    </form>
</div>
<div class="clearfix"></div>
