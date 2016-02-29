<?php

namespace phs\plugins\accounts\actions;

use \phs\libraries\PHS_Action;
use phs\libraries\PHS_params;
use \phs\system\core\views\PHS_View;

class PHS_Action_Login extends PHS_Action
{
    public function execute()
    {
        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $nick = PHS_params::_pg( 'nick', PHS_params::T_NOHTML );
        $pass = PHS_params::_pg( 'pass', PHS_params::T_NOHTML );
        $do_remember = PHS_params::_pg( 'do_remember', PHS_params::T_INT );
        $submit = PHS_params::_p( 'submit' );

        $data = array(
            'nick' => $nick,
            'pass' => $pass,
            'do_remember' => (!empty( $do_remember )?'checked="checked"':''),
        );

        return $this->quick_render_template( 'login', $data );
    }
}
