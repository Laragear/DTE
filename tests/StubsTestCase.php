<?php

namespace Tests;

use Generator;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Symfony\Component\Finder\Finder;

use function file_get_contents;
use function hash;
use function json_decode;

class StubsTestCase extends PHPUnitTestCase
{
    public function test_has_checksum_file(): void
    {
        static::assertFileExists(TestCase::STUBS.'/checksums.json');
    }

    /**
     * @return Generator<string>
     */
    public static function providesFilesInStubsDirectory(): iterable
    {
        foreach (new Finder()->in(TestCase::STUBS)->files() as $file) {
            if ($file->getFilename() !== 'checksums.json') {
                yield [$file->getRelativePathname()];
            }
        }
    }

    #[DataProvider('providesFilesInStubsDirectory')]
    public function test_stub_checksum_files_exist(string $filename): void
    {
        static::assertArrayHasKey($filename, json_decode(TestCase::getStub('checksums.json'), true));
    }

    public static function providesStubFilesWithChecksum(): array
    {
        return Arr::mapWithKeys(
            json_decode(TestCase::getStub('checksums.json'), true),
            static function (string $hash, string $file) {
                return [
                    $file => [TestCase::STUBS.'/'.$file, $hash],
                ];
            }
        );
    }

    #[DataProvider('providesStubFilesWithChecksum')]
    public function test_stubs_are_not_been_tampered_with(string $filename, string $hash): void
    {
        static::assertFileExists($filename);

        static::assertSame(
            $hash,
            hash('sha256', file_get_contents($filename)),
            "The file [$filename] has been modified, tests will fail."
        );
    }
}
