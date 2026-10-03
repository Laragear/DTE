<?php

namespace Laragear\Dte\Dev;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

use function array_merge;
use function sprintf;

/**
 * Parse SII XSD files into facet maps.
 *
 * Reads simpleType restrictions and element declarations, resolving
 * named-type references across included schemas.
 *
 * @internal Library development tooling only.
 */
class XsdParser
{
    protected const string NS = 'http://www.w3.org/2001/XMLSchema';

    /**
     * Named simpleType facets keyed by type name.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $types = [];

    /**
     * Parse the given XSD files.
     *
     * @param  list<string>  $files
     * @return array{types: array<string, array<string, mixed>>, elements: array<string, array<string, mixed>>}
     */
    public function parse(array $files): array
    {
        $this->types = [];

        $elements = [];

        $this->collectAllTypes($files);

        foreach ($files as $file) {
            $xpath = $this->load($file);

            $elements = array_merge($elements, $this->collectElements($xpath));
        }

        return ['types' => $this->types, 'elements' => $elements];
    }

    /**
     * Collect every named simpleType from all given files.
     *
     * Types are gathered across all sources before any element is read, so a
     * type declared in an included schema resolves everywhere.
     *
     * @param  list<string>  $files
     */
    protected function collectAllTypes(array $files): void
    {
        foreach ($files as $file) {
            $this->collectTypes($this->load($file));
        }
    }

    /**
     * Load an XSD file into an XPath reader.
     */
    protected function load(string $file): DOMXPath
    {
        if (! is_file($file)) {
            throw new RuntimeException(sprintf('XSD file not found [%s].', $file));
        }

        $document = new DOMDocument;
        $document->load($file);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('xs', static::NS);

        return $xpath;
    }

    /**
     * Collect every named simpleType restriction.
     */
    protected function collectTypes(DOMXPath $xpath): void
    {
        foreach ($xpath->query('/xs:schema/xs:simpleType') as $type) {
            $restriction = $xpath->query('xs:restriction', $type)->item(0);

            if ($restriction instanceof DOMElement) {
                $this->types[$type->getAttribute('name')] = $this->readRestriction($xpath, $restriction);
            }
        }
    }

    /**
     * Collect every named element declaration.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function collectElements(DOMXPath $xpath): array
    {
        $elements = [];

        foreach ($xpath->query('//xs:element[@name]') as $element) {
            $name = $element->getAttribute('name');

            // First declaration wins: Documento and Liquidacion share names.
            $elements[$name] ??= $this->readElementFacets($xpath, $element);
        }

        return $elements;
    }

    /**
     * Read the occurrence, type and inline facets of an element.
     *
     * @return array<string, mixed>
     */
    protected function readElementFacets(DOMXPath $xpath, DOMElement $element): array
    {
        $facets = [
            'minOccurs' => $element->hasAttribute('minOccurs') ? (int) $element->getAttribute('minOccurs') : 1,
            'maxOccurs' => $element->getAttribute('maxOccurs') === 'unbounded'
                ? 'unbounded'
                : (int) ($element->getAttribute('maxOccurs') ?: 1),
        ];

        if ($element->hasAttribute('type')) {
            $facets = array_merge($facets, $this->resolveType($element->getAttribute('type')));
        }

        $inline = $xpath->query('xs:simpleType/xs:restriction', $element)->item(0);

        if ($inline instanceof DOMElement) {
            $facets = array_merge($facets, $this->readRestriction($xpath, $inline));
        }

        return $facets;
    }

    /**
     * Resolve a type reference to its facet map.
     *
     * @return array<string, mixed>
     */
    protected function resolveType(string $type): array
    {
        $name = str_contains($type, ':') ? substr($type, strpos($type, ':') + 1) : $type;

        return match (true) {
            isset($this->types[$name]) => $this->types[$name],
            str_starts_with($name, 'xs:') || $name === 'string' => ['base' => 'string'],
            $name === 'positiveInteger' => ['base' => 'positiveInteger'],
            $name === 'nonNegativeInteger' => ['base' => 'nonNegativeInteger'],
            $name === 'integer' => ['base' => 'integer'],
            $name === 'decimal' => ['base' => 'decimal'],
            $name === 'date' => ['base' => 'date'],
            $name === 'dateTime' => ['base' => 'dateTime'],
            $name === 'time' => ['base' => 'time'],
            $name === 'ID' => ['base' => 'string'],
            default => ['base' => 'string'],
        };
    }

    /**
     * Read a restriction node into a facet map.
     *
     * @return array<string, mixed>
     */
    protected function readRestriction(DOMXPath $xpath, DOMElement $restriction): array
    {
        $facets = ['base' => $restriction->getAttribute('base')];

        $short = str_contains($facets['base'], ':') ? substr($facets['base'], strpos($facets['base'], ':') + 1) : $facets['base'];

        if (isset($this->types[$short])) {
            $facets = array_merge($this->types[$short], $facets);
        }

        foreach ($xpath->query('xs:enumeration', $restriction) as $enum) {
            $facets['enum'][] = $enum->getAttribute('value');
        }

        foreach (['maxLength', 'minLength', 'length', 'pattern', 'totalDigits', 'fractionDigits', 'minInclusive', 'maxInclusive'] as $facet) {
            $node = $xpath->query('xs:'.$facet, $restriction)->item(0);

            if ($node instanceof DOMElement) {
                $facets[$facet] = $node->getAttribute('value');
            }
        }

        return $facets;
    }
}
