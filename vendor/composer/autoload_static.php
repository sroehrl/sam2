<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit31a8e34cb1cc7b0e0304ee47c638caf6
{
    public static $prefixLengthsPsr4 = array (
        'N' => 
        array (
            'Neoan3\\Apps\\' => 12,
        ),
        'L' => 
        array (
            'League\\CommonMark\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Neoan3\\Apps\\' => 
        array (
            0 => __DIR__ . '/..' . '/neoan3-apps/db',
            1 => __DIR__ . '/..' . '/neoan3-apps/template',
            2 => __DIR__ . '/..' . '/neoan3-apps/transformer',
        ),
        'League\\CommonMark\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/commonmark/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'C' => 
        array (
            'Composer\\CustomDirectoryInstaller' => 
            array (
                0 => __DIR__ . '/..' . '/mnsami/composer-custom-directory-installer/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit31a8e34cb1cc7b0e0304ee47c638caf6::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit31a8e34cb1cc7b0e0304ee47c638caf6::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit31a8e34cb1cc7b0e0304ee47c638caf6::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}