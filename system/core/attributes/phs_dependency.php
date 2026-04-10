<?php
namespace phs\system\core\attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PHS_Dependency
{
    public function __construct(
        public bool $as_singleton = true,
        public bool $error_if_fails = true,
        public array $depends_on = [],
        public int $priority = 100,
    ) {
    }
}
