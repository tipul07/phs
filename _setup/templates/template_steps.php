<?php
    /** @var \phs\setup\libraries\PHS_Setup_view $this */

    /** @var \phs\setup\libraries\PHS_Setup $setup_obj */
    if( ($setup_obj = $this->get_context( 'phs_setup_obj' )) )
    {
        ?>
        <h1>Step <?php echo $setup_obj->current_step().' / '.$setup_obj->max_steps()?></h1>
        <?php
    }

    /** @var \phs\setup\libraries\PHS_Step $step_obj */
    if( ($step_obj = $this->get_context( 'step_instance' ))
    and ($step_details = $step_obj->step_details()) )
    {
        ?>
        <h3><?php echo $step_details['title']?></h3>
        <small><?php echo $step_details['description']?></small>
        <?php
    }

    echo $this->get_context( 'step_interface_buf' );

