<?php

namespace Tests\Unit\Validation;

use Laragear\Dte\Dev\ClassEmitter;
use Laragear\Dte\Dev\RuleMapper;
use Laragear\Dte\Dev\RulesGenerator;
use Laragear\Dte\Dev\XsdParser;
use Tests\TestCase;

use function dirname;
use function file_get_contents;

class DteRulesFreshTest extends TestCase
{
    public function test_the_committed_rules_match_a_fresh_generation(): void
    {
        $root = dirname(__DIR__, 3);

        $generated = (new RulesGenerator(
            new XsdParser,
            new RuleMapper,
            new ClassEmitter,
        ))->generate($root);

        $committed = file_get_contents($root.'/src/Validation/DteRules.php');

        static::assertSame($committed, $generated);
    }
}
