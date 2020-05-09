<?php

namespace phs\plugins\messages\controllers;

class PHS_Controller_Index extends \phs\libraries\PHS_Controller_Index
{
    /**
     * Overwrite this method to tell controller to redirect user to login page if not logged in
     * @return bool
     */
    public function should_request_have_logged_in_user()
    {
        return true;
    }

    /**
     * Overwrite this method to tell controller that a check if current logged in user should have any role units defined in current plugin
     * If this method returns true, an user checked test is also made
     * @return bool
     */
    public function should_user_have_any_of_defined_role_units()
    {
        return true;
    }
}
