#!/usr/bin/env php
<?php

$version = $argv[1] ?? '';
if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
    fwrite(STDERR, "Usage: php scripts/set-version.php <version>\nExample: php scripts/set-version.php 1.1.0\n");
    exit(1);
}

$root = dirname(__DIR__);
$pluginFile = $root . '/idoneo-custom-fields.php';
$readmeFile = $root . '/readme.txt';
$plugin = file_get_contents($pluginFile);
$readme = file_get_contents($readmeFile);

if (false === $plugin || false === $readme) {
    fwrite(STDERR, "Could not read plugin version files.\n");
    exit(1);
}

$plugin = preg_replace('/(^\s*\*\s*Version:\s*)\S+/mi', '${1}' . $version, $plugin, 1);
$plugin = preg_replace(
    "/define\\(\\s*'ICF_VERSION'\\s*,\\s*'[^']+'\\s*\\)/",
    "define('ICF_VERSION', '{$version}')",
    $plugin,
    1
);
$readme = preg_replace('/(^Stable tag:\s*)\S+/mi', '${1}' . $version, $readme, 1);

if (null === $plugin || null === $readme) {
    fwrite(STDERR, "Could not update version values.\n");
    exit(1);
}

file_put_contents($pluginFile, $plugin);
file_put_contents($readmeFile, $readme);

fwrite(STDOUT, "Version set to {$version}.\nAdd a '= {$version} =' section to the readme.txt changelog before merging.\n");
