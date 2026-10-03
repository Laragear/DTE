<?php

namespace Laragear\Dte\Enums;

use Laragear\Dte\Support\SiiEndpoints;

enum DteEnvironment: string
{
    public const self DEFAULT = self::Local;

    /** Local development runs without contacting SII services. */
    case Local = 'local';

    /** Automated tests run without contacting SII services. */
    case Testing = 'testing';

    /** Certification sends test documents to the SII Maullín services. */
    case Certification = 'certification';

    /** Production sends legally valid documents to the SII Palena services. */
    case Production = 'production';

    /**
     * Determine if the environment is production.
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    /**
     * Determine if the environment is testing.
     */
    public function isTesting(): bool
    {
        return $this === self::Testing;
    }

    /**
     * Determine if the environment is local.
     */
    public function isLocal(): bool
    {
        return $this === self::Local;
    }

    /**
     * Determine if certification workflows are permitted in this environment.
     */
    public function isAllowedOnCertification(): bool
    {
        return $this !== self::Testing && $this !== self::Production;
    }

    /**
     * Determine if the certification workflows are not permitted in this environment.
     */
    public function isNotAllowedOnCertification(): bool
    {
        return !$this->isAllowedOnCertification();
    }

    /**
     * Determine if fake asset generation commands are permitted in this environment.
     */
    public function allowsFakeAssets(): bool
    {
        return $this !== self::Production;
    }

    /**
     * Return the SII base URL available for this environment for SOAP endpoints.
     */
    public function soapBaseUrl(): ?string
    {
        return match ($this) {
            self::Certification => SiiEndpoints::SOAP_CERTIFICATION,
            self::Production => SiiEndpoints::SOAP_PRODUCTION,
            default => null,
        };
    }

    /**
     * Return the SII base URL for the Ley 19.983 Reclamo webservice.
     */
    public function reclamoBaseUrl(): ?string
    {
        return match ($this) {
            self::Certification => SiiEndpoints::RECLAMO_CERTIFICATION,
            self::Production => SiiEndpoints::RECLAMO_PRODUCTION,
            default => null,
        };
    }

    /**
     * Return the SII base URL available for this environment for REST endpoints.
     */
    public function restBaseUrl(): ?string
    {
        return match ($this) {
            self::Certification => SiiEndpoints::REST_CERTIFICATION,
            self::Production => SiiEndpoints::REST_PRODUCTION,
            default => null,
        };
    }

    /**
     * Return the SII base URL for REST upload endpoints (boleta uploads).
     *
     * @see https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml servers section
     */
    public function restUploadBaseUrl(): ?string
    {
        // SII uses dedicated servers for uploads (pangal/rahue) separate from
        // the auth/query servers (apicert/api).
        return match ($this) {
            self::Certification => SiiEndpoints::REST_UPLOAD_CERTIFICATION,
            self::Production => SiiEndpoints::REST_UPLOAD_PRODUCTION,
            default => null,
        };
    }
}
