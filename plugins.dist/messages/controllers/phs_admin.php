<?php

namespace phs\plugins\messages\controllers;

class PHS_Controller_Admin extends \phs\libraries\PHS_Controller_Admin
{
    /**
     * @inheritdoc
     */
    public function should_user_have_any_of_defined_role_units() : bool
    {
        return true;
    }
}
