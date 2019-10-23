<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitd5f8aedec7b83d38b86c38c2fb1efb31
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'WPReadme2Markdown\\' => 18,
        ),
        'D' => 
        array (
            'Defuse\\Crypto\\' => 14,
        ),
        'C' => 
        array (
            'Composer\\Installers\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'WPReadme2Markdown\\' => 
        array (
            0 => __DIR__ . '/..' . '/wpreadme2markdown/wpreadme2markdown/src',
        ),
        'Defuse\\Crypto\\' => 
        array (
            0 => __DIR__ . '/..' . '/defuse/php-encryption/src',
        ),
        'Composer\\Installers\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/installers/src/Composer/Installers',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitd5f8aedec7b83d38b86c38c2fb1efb31::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitd5f8aedec7b83d38b86c38c2fb1efb31::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
