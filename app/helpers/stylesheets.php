<?php

require_once __DIR__ . '/dev_locator.php';

function stylesheet_bundle(string $name): string
{
    static $bundles = null;
    $bundles ??= require __DIR__ . '/../config/stylesheets.php';
    if (!isset($bundles[$name])) {
        throw new InvalidArgumentException('Unknown stylesheet bundle: ' . $name);
    }

    $links = [];
    foreach ($bundles[$name] as $file) {
        $path = __DIR__ . '/../../public/assets/css/' . $file;
        $version = md5_file($path);
        if ($version === false) {
            throw new RuntimeException('Cannot load stylesheet: ' . $file);
        }
        $href = htmlspecialchars('assets/css/' . $file . '?v=' . $version, ENT_QUOTES, 'UTF-8');
        $links[] = '<link rel="stylesheet" href="' . $href . '">';
    }

    return implode("\n", $links) . "\n";
}
