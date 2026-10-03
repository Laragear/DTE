<?php

namespace Laragear\Dte\Builders\Concerns;

use InvalidArgumentException;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\ReferenceType;
use OverflowException;
use function array_unshift;
use function count;
use function preg_match;
use function sprintf;

trait HasReferences
{
    protected const int MAX_REFERENCES = 40;

    /** @var list<ReferenceData> */
    protected array $references = [];

    /**
     * Add a document reference.
     */
    public function addReference(ReferenceData $reference): static
    {
        if (count($this->references) >= static::MAX_REFERENCES) {
            throw new OverflowException('A DTE cannot contain more than 40 references.');
        }

        $this->references[] = $reference;

        return $this;
    }

    /**
     * Mark this document as part of a certification test set case.
     */
    public function forTestCase(string $case): static
    {
        // The SET reference is stored eagerly as the first reference line, as
        // required by the SII certification rules. Call it after issuedOn() so
        // the reference date matches the document issue date.
        if (!preg_match('/^\d+-\d+$/', $case)) {
            throw new InvalidArgumentException(
                sprintf('Invalid test case format [%s]. Expected "NNNNN-N" (e.g. "5034081-1").', $case),
            );
        }

        $reference = ReferenceData::make(
            ReferenceType::TestSet,
            '0',
            $this->issueDate,
            "CASO {$case}",
        );

        if (($this->references[0] ?? null)?->documentType === ReferenceType::TestSet) {
            $this->references[0] = $reference;
        } else {
            if (count($this->references) >= static::MAX_REFERENCES) {
                throw new OverflowException('A DTE cannot contain more than 40 references.');
            }

            array_unshift($this->references, $reference);
        }

        return $this;
    }

    /**
     * Remove all document references, including the test set reference.
     */
    public function clearReferences(): static
    {
        $this->references = [];

        return $this;
    }

    /**
     * Return the document references.
     *
     * @return ReferenceData[]
     */
    public function references(): array
    {
        return $this->references;
    }
}
