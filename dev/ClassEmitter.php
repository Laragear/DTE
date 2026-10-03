<?php

namespace Laragear\Dte\Dev;

use function ksort;
use function rtrim;
use function sprintf;

/**
 * Emit the generated DteRules class source.
 *
 * Outputs plain string rule maps only, so the result stays serializable,
 * diffable and reviewable as a one-file diff.
 *
 * @internal Library development tooling only.
 */
class ClassEmitter
{
    /**
     * Emit the class source for the given sections.
     *
     * @param  array<string, array<string, string>>  $sections
     */
    public function emit(array $sections, string $hash, string $sources): string
    {
        return <<<PHP
<?php

namespace Laragear\Dte\Validation;

/**
 * XSD-derived validation rules for DTE sections.
 *
 * @generated from $sources — DO NOT EDIT.
 * Run `composer dte:rules` to regenerate.
 */
final class DteRules
{
    public const string SOURCE_HASH = '$hash';

{$this->renderClassBody($sections)}
}

PHP;
    }

    /**
     * Render every section constant, sorted by section then rule name.
     *
     * @param  array<string, array<string, string>>  $sections
     */
    protected function renderClassBody(array $sections): string
    {
        ksort($sections);

        $body = '';

        foreach ($sections as $section => $rules) {
            ksort($rules);

            $body .= $this->renderSection($section, $rules);
        }

        return rtrim($body);
    }

    /**
     * Render a single section constant.
     *
     * @param  array<string, string>  $rules
     */
    protected function renderSection(string $section, array $rules): string
    {
        $body = sprintf("    public const array %s = [\n", $section);

        foreach ($rules as $key => $rule) {
            $body .= sprintf("        '%s' => '%s',\n", $key, $rule);
        }

        return $body."    ];\n\n";
    }
}
