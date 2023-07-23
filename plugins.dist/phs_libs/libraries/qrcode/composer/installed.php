<?php

return [
    'root' => [
        'name'           => '__root__',
        'pretty_version' => '1.0.0+no-version-set',
        'version'        => '1.0.0.0',
        'reference'      => null,
        'type'           => 'library',
        'install_path'   => __DIR__.'/../../',
        'aliases'        => [],
        'dev'            => true,
    ],
    'versions' => [
        '__root__' => [
            'pretty_version'  => '1.0.0+no-version-set',
            'version'         => '1.0.0.0',
            'reference'       => null,
            'type'            => 'library',
            'install_path'    => __DIR__.'/../../',
            'aliases'         => [],
            'dev_requirement' => false,
        ],
        'chillerlan/php-qrcode' => [
            'pretty_version'  => '4.3.4',
            'version'         => '4.3.4.0',
            'reference'       => '2ca4bf5ae048af1981d1023ee42a0a2a9d51e51d',
            'type'            => 'library',
            'install_path'    => __DIR__.'/../chillerlan/php-qrcode',
            'aliases'         => [],
            'dev_requirement' => false,
        ],
        'chillerlan/php-settings-container' => [
            'pretty_version'  => '2.1.4',
            'version'         => '2.1.4.0',
            'reference'       => '1beb7df3c14346d4344b0b2e12f6f9a74feabd4a',
            'type'            => 'library',
            'install_path'    => __DIR__.'/../chillerlan/php-settings-container',
            'aliases'         => [],
            'dev_requirement' => false,
        ],
    ],
];
