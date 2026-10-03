<?php

namespace Laragear\Dte\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Gateways\Exceptions\TokenInvalidException;
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Dte\Gateways\TokenStatus;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Rut\Rut;
use Psr\Log\LoggerInterface;
use Throwable;

class DteAuthenticityVerifier
{
    /**
     * Create a new DTE Authenticity Verifier instance.
     */
    public function __construct(
        protected SoapGateway $soapGateway,
        protected XmlDomFactory $xml,
        protected TokenAuthenticator $authenticator,
        protected LoggerInterface $logger,
    ) {
        //
    }

    /**
     * Query the SII WS to verify if the DTE exists in their records.
     */
    public function verify(
        Rut $issuer,
        Rut $receiver,
        DteType $type,
        int $folio,
        Carbon $issuedOn,
        int $amountTotal,
    ): bool {
        try {
            return $this->authenticator->retryWithFreshToken(function () use (
                $issuer,
                $receiver,
                $type,
                $folio,
                $issuedOn,
                $amountTotal
            ): bool {
                $token = $this->authenticator->token($receiver);
                $xml = $this->querySii($token, $issuer, $receiver, $type, $folio, $issuedOn, $amountTotal);

                $this->throwIfTokenInvalid($xml);

                return $this->evaluateVerificationResponse($xml);
            }, $receiver);
        } catch (Throwable $e) {
            $this->logger->warning('SII authenticity verification failed: '.$e->getMessage(), [
                'issuer' => $issuer->toString(),
                'receiver' => $receiver->toString(),
                'type' => $type->value,
                'folio' => $folio,
            ]);

            // Returns false if the WS is unreachable (treated as pending verification).
            return false;
        }
    }

    /**
     * Ask the SII for the document authenticity.
     */
    protected function querySii(
        Token $token,
        Rut $issuer,
        Rut $receiver,
        DteType $type,
        int $folio,
        Carbon $issuedOn,
        int $amountTotal,
    ): string {
        $response = $this->soapGateway->query($token, 'QueryEstDteAv', 'getEstDteAv', [
            'RutEmisor' => $issuer->num,
            'DvEmisor' => $issuer->vd,
            'RutReceptor' => $receiver->num,
            'DvReceptor' => $receiver->vd,
            'TipoDoc' => (string) $type->value,
            'Folio' => (string) $folio,
            'FchEmis' => $issuedOn->format('d-m-Y'),
            'MontoTotal' => (string) $amountTotal,
            'Token' => $token->value,
        ]);

        return is_object($response) ? (string) $response->getEstDteAvResult : (string) $response;
    }

    /**
     * Throw when SII reports the token as invalid.
     */
    protected function throwIfTokenInvalid(string $xml): void
    {
        if ($this->isTokenInvalidStatus($xml)) {
            throw new TokenInvalidException('SII SOAP token was invalidated (001/002/003).');
        }
    }

    /**
     * Normalize the response status from SII.
     */
    protected function evaluateVerificationResponse(string $xml): bool
    {
        $xmlResponse = $this->xml->simpleXml($xml);
        $sii = $xmlResponse->children('http://www.sii.cl/XMLSchema');
        $body = $sii->RESP_BODY->children('');

        return in_array((string) $body->CODIGO_ESTADO, ['0', '3'], true);
    }

    /**
     * Checks if the XML response indicates an invalid/expired SOAP token.
     */
    protected function isTokenInvalidStatus(string $xml): bool
    {
        // SII returns 001 (inactive), 002 (invalid) or 003 (invalid) for a
        // token that must be refreshed by re-authenticating.
        return Str::contains($xml, array_map(
            static fn(string $code) => '<ESTADO>'.$code.'</ESTADO>',
            TokenStatus::INVALID_CODES,
        ));
    }
}
