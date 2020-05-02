<?php

namespace phs\tests\phs\contexts;

include_once( __DIR__.'/../../bootstrap.php' );

use phs\libraries\PHS_Registry;
use Behat\Behat\Context\Context as BehatContext;

abstract class PHSAbstractContext extends  PHS_Registry implements BehatContext
{
}
