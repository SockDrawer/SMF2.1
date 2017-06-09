<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit0e9ec336bffbe66e833272efb175b760
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'LightnCandy\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'LightnCandy\\' => 
        array (
            0 => __DIR__ . '/..' . '/zordius/lightncandy/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit0e9ec336bffbe66e833272efb175b760::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit0e9ec336bffbe66e833272efb175b760::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
