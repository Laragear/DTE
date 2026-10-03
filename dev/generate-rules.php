<?php

use Laragear\Dte\Dev\ClassEmitter;
use Laragear\Dte\Dev\RuleMapper;
use Laragear\Dte\Dev\RulesGenerator;
use Laragear\Dte\Dev\XsdParser;

/**
 * Regenerate src/Validation/DteRules.php from the SII XSD files.
 *
 * Usage: composer dte:rules
 *
 * Reads the XSD files, maps every element listed in dev/field-map.php
 * to a builder array key, and writes one committed plain-PHP class.
 * Fails loudly on unmapped elements so the maintainer maps them first.
 */

require __DIR__.'/../vendor/autoload.php';

$root = dirname(__DIR__);

try {
    $source = (new RulesGenerator(
        new XsdParser,
        new RuleMapper,
        new ClassEmitter,
    ))->generate($root);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);

    exit(1);
}

file_put_contents($root.'/src/Validation/DteRules.php', $source);

echo 'Generated src/Validation/DteRules.php'.PHP_EOL;
