<?php

namespace Laragear\Dte\Enums;

/**
 * Response states (ESTADO) returned by the SII authentication services
 *
 * (CrSeed and GetTokenFromSeed).
 */
enum SiiAuthState: string
{
    /** The seed or token was issued correctly. */
    case Ok = '00';

    /** "Error no genera Semilla": the seed request may be retried once. */
    case SeedGenerationError = '-1';

    /** "Error en Base de Datos SII": retry the seed later with backoff. */
    case SeedDatabaseError = '-2';

    /** "Error interno SII": the token is not ready yet; retry with backoff. */
    case TokenPending = '12';

    /** The certificate is invalid, expired, or the RUT is not enrolled. */
    case CertificateRejected = '-3';

    case XmlIoError = '01';

    case XmlSaxError = '02';

    case MissingSignature = '04';

    case InvalidSignature = '05';

    case MissingSemilla = '06';

    case CertificateNotFound = '11';

    /**
     * GetTokenFromSeed client error codes that are not retryable.
     *
     * @return list<self>
     */
    public static function clientErrors(): array
    {
        return [
            self::XmlIoError,
            self::XmlSaxError,
            self::MissingSignature,
            self::InvalidSignature,
            self::MissingSemilla,
            self::CertificateNotFound,
        ];
    }

    /**
     * Human-readable meaning of the state, per the SII documentation.
     */
    public function gloss(): string
    {
        return match ($this) {
            self::XmlIoError => 'XML inválido (IOException).',
            self::XmlSaxError => 'XML inválido (SAXException) — verificar encoding UTF-8 / ISO-8859-1.',
            self::MissingSignature => 'Falta el tag <Signature> en la estructura XML.',
            self::InvalidSignature => 'Firma inválida — revisar algoritmo RSA-SHA1 o canonicalización C14N.',
            self::MissingSemilla => 'El elemento <Semilla> no existe en la estructura XML.',
            self::CertificateNotFound => 'Certificado no encontrado — incluir el tag <X509Certificate> completo.',
            default => 'Error desconocido.',
        };
    }
}
