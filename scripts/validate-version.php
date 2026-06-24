#!/usr/bin/env php
<?php

$root = dirname(__DIR__);
$pluginFile = $root . '/idoneo-custom-fields.php';
$readmeFile = $root . '/readme.txt';

$plugin = file_get_contents($pluginFile);
$readme = file_get_contents($readmeFile);

if (false === $plugin || false === $readme) {
    fwrite(STDERR, "Could not read plugin version files.\n");
    exit(1);
}

preg_match('/^\s*\*\s*Version:\s*(\S+)/mi', $plugin, $headerMatch);
preg_match("/define\\(\\s*'ICF_VERSION'\\s*,\\s*'([^']+)'\\s*\\)/", $plugin, $constantMatch);
preg_match('/^Stable tag:\s*(\S+)/mi', $readme, $stableTagMatch);

$versions = [
    'plugin header' => $headerMatch[1] ?? '',
    'ICF_VERSION'   => $constantMatch[1] ?? '',
    'stable tag'    => $stableTagMatch[1] ?? '',
];

foreach ($versions as $location => $version) {
    if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
        fwrite(STDERR, "Invalid or missing version in {$location}: {$version}\n");
        exit(1);
    }
}

if (1 !== count(array_unique($versions))) {
    fwrite(STDERR, "Version values do not match:\n");
    foreach ($versions as $location => $version) {
        fwrite(STDERR, "- {$location}: {$version}\n");
    }
    exit(1);
}

$version = reset($versions);
if (! preg_match('/^=\s*' . preg_quote($version, '/') . '\s*=$/mi', $readme)) {
    fwrite(STDERR, "Missing changelog section for version {$version} in readme.txt.\n");
    exit(1);
}

fwrite(STDOUT, $version . "\n");
