<?php
namespace phs\plugins\messages\controllers;

class PHS_Controller_Index extends \phs\libraries\PHS_Controller_Index
{
    /**
     * @inheritdoc
     */
    public function should_request_have_logged_in_user() : bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function should_user_have_any_of_defined_role_units() : bool
    {
        return true;
    }
}
