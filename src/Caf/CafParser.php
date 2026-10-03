<?php

namespace Laragear\Dte\Caf;

use DateTimeImmutable;
use Illuminate\Support\DateFactory;
use InvalidArgumentException;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Proxies\OpenSslProxy;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Rut\Rut;
use SimpleXMLElement;
use Throwable;
use function base64_decode;
use function chunk_split;
use function ctype_digit;
use function hash_equals;
use function is_array;
use function str_contains;
use function str_replace;
use function trim;

class CafParser
{
    /**
     * Create a Caf Parser instance.
     */
    public function __construct(
        protected XmlDomFactory $xml,
        protected OpenSslProxy $openSsl,
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Parse and validate an SII CAF XML document.
     *
     * @return array{
     *     issuer_rut: string,
     *     document_type: DteType,
     *     folio_from: int,
     *     folio_to: int,
     *     folio_current: int,
     *     authorized_on: DateTimeImmutable,
     *     xml: string,
     *     public_key_modulus: string,
     *     public_key_exponent: string,
     *     private_key: string,
     *     signature: string
     * }
     */
    public function parse(string $xml): array
    {
        $root = $this->xml->simpleXml($xml);

        $caf = $this->cafNode($root);
        $range = $this->folioRange($caf);
        $issuerRut = Rut::parse($this->element($caf, './/DA/RE'))->formatRaw();
        $documentType = $this->documentType($caf);
        $authorizedOn = $this->dateElement($caf, './/DA/FA');
        $signature = $this->base64Element($caf, './/FRMA', 'SII signature');

        $modulus = $this->base64Element($caf, './/DA/RSAPK/M', 'RSA public-key modulus');
        $exponent = $this->base64Element($caf, './/DA/RSAPK/E', 'RSA public-key exponent');
        $privateKey = $this->privateKey($root);

        $this->validateKeyPair($privateKey, $modulus, $exponent);

        return [
            'issuer_rut' => $issuerRut,
            'document_type' => $documentType,
            'folio_from' => $range[0],
            'folio_to' => $range[1],
            'folio_current' => $range[0],
            'authorized_on' => $authorizedOn,
            'xml' => $xml,
            'public_key_modulus' => $modulus,
            'public_key_exponent' => $exponent,
            'private_key' => $privateKey,
            'signature' => $signature,
        ];
    }

    /**
     * Find the CAF node in a downloaded authorization or embedded CAF.
     */
    protected function cafNode(SimpleXMLElement $root): SimpleXMLElement
    {
        if ($root->getName() === 'CAF') {
            return $root;
        }

        $nodes = $root->xpath('//CAF');

        if ($nodes === false || !isset($nodes[0]) || !$nodes[0] instanceof SimpleXMLElement) {
            throw new InvalidArgumentException('The XML document does not contain a CAF node.');
        }

        return $nodes[0];
    }

    /**
     * Return a required CAF element value.
     */
    protected function element(SimpleXMLElement $caf, string $path): string
    {
        $nodes = $caf->xpath($path);
        $value = $nodes === false || !isset($nodes[0]) ? '' : trim((string) $nodes[0]);

        if (!$value) {
            throw new InvalidArgumentException("The CAF element [$path] is missing.");
        }

        return $value;
    }

    /**
     * Return a required positive integer CAF element.
     */
    protected function integerElement(SimpleXMLElement $caf, string $path): int
    {
        $value = $this->element($caf, $path);

        if (!ctype_digit($value)) {
            throw new InvalidArgumentException("The CAF element [$path] must be a positive integer.");
        }

        return (int) $value;
    }

    /**
     * Return a supported CAF document type.
     */
    protected function documentType(SimpleXMLElement $caf): DteType
    {
        return
            DteType::tryFrom($this->integerElement($caf, './/DA/TD')) ?? throw new InvalidArgumentException(
            'The CAF document type is not supported.',
        );
    }

    /**
     * Return and validate the authorized folio range.
     *
     * @return array{int, int}
     */
    protected function folioRange(SimpleXMLElement $caf): array
    {
        $from = $this->integerElement($caf, './/DA/RNG/D');
        $to = $this->integerElement($caf, './/DA/RNG/H');

        if ($from > $to) {
            throw new InvalidArgumentException('The CAF folio range is invalid.');
        }

        return [$from, $to];
    }

    /**
     * Return a strict CAF authorization date.
     */
    protected function dateElement(SimpleXMLElement $caf, string $path): DateTimeImmutable
    {
        $value = $this->element($caf, $path);

        try {
            $date = $this->date->createFromFormat('!Y-m-d', $value, 'America/Santiago')?->toDateTimeImmutable();
        } catch (Throwable $e) {
            throw new InvalidArgumentException("The CAF element [$path] must use YYYY-MM-DD format.", previous: $e);
        }

        if (!$date) {
            throw new InvalidArgumentException("The CAF element [$path] must use YYYY-MM-DD format.");
        }

        if ($date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("The CAF element [$path] must use YYYY-MM-DD format.");
        }

        return $date;
    }

    /**
     * Return a strictly encoded Base64 CAF element.
     */
    protected function base64Element(SimpleXMLElement $caf, string $path, string $name): string
    {
        $value = $this->element($caf, $path);

        if (base64_decode($value, true) === false) {
            throw new InvalidArgumentException("The CAF $name is not valid Base64.");
        }

        return $value;
    }

    /**
     * Return a valid RSA private key from the CAF authorization.
     */
    protected function privateKey(SimpleXMLElement $root): string
    {
        $key = $this->element($root, '//RSASK');

        if (!str_contains($key, '-----BEGIN')) {
            $key =
                "-----BEGIN RSA PRIVATE KEY-----\n"
                .chunk_split((string) str_replace(["\r", "\n", ' '], '', $key), 64, "\n")
                .'-----END RSA PRIVATE KEY-----';
        }

        if ($this->openSsl->privateKeyDetails($key) === null) {
            throw new InvalidArgumentException('The CAF RSA private key is invalid.');
        }

        return $key;
    }

    /**
     * Ensure the CAF private key belongs to its advertised RSA public key.
     */
    protected function validateKeyPair(string $privateKey, string $modulus, string $exponent): void
    {
        $details = $this->openSsl->privateKeyDetails($privateKey);

        if ($details === null) {
            throw new InvalidArgumentException('The CAF RSA private key is invalid.');
        }

        $rsa = is_array($details) ? $details['rsa'] ?? null : null;

        if (
            !is_array($rsa)
            || !hash_equals($rsa['n'], (string) base64_decode($modulus, true))
            || !hash_equals($rsa['e'], (string) base64_decode($exponent, true))
        ) {
            throw new InvalidArgumentException('The CAF RSA private key does not match its public key.');
        }
    }
}
