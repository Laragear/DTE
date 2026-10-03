<?php

namespace Laragear\Dte\Support;

/** @internal */
class SiiEndpoints
{
    public const string SOAP_CERTIFICATION = 'https://maullin.sii.cl';

    public const string SOAP_PRODUCTION = 'https://palena.sii.cl';

    public const string REST_CERTIFICATION = 'https://apicert.sii.cl/recursos/v1';

    public const string REST_PRODUCTION = 'https://api.sii.cl/recursos/v1';

    /**
     * REST Upload endpoints (separate from auth/query endpoints).
     *
     * SII uses dedicated servers for boleta uploads:
     * - Certification: pangal.sii.cl
     * - Production: rahue.sii.cl
     *
     * @see https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml servers section
     */
    public const string REST_UPLOAD_CERTIFICATION = 'https://pangal.sii.cl/recursos/v1';

    public const string REST_UPLOAD_PRODUCTION = 'https://rahue.sii.cl/recursos/v1';

    /**
     * Default user agent to use with contacting SII endpoints.
     */
    public const string USER_AGENT = 'Laragear-Dte/1.0 (PHP; PROG 1.0; +[https://github.com/Laragear/Dte](https://github.com/Laragear/Dte))';

    /**
     * Name of the SII authentication cookie carrying the session token.
     */
    public const string TOKEN_COOKIE = 'TOKEN';

    /**
     * Reclamo webservice endpoints (Ley 19.983 acceptance/rejection).
     * These run on separate SII hosts from the DTE SOAP endpoints.
     */
    public const string RECLAMO_CERTIFICATION = 'https://ws2.sii.cl/WSREGISTRORECLAMODTECERT/registroreclamodteservice';

    public const string RECLAMO_PRODUCTION = 'https://ws1.sii.cl/WSREGISTRORECLAMODTE/registroreclamodteservice';

    /**
     * Name of the HTTP header carrying the user agent.
     */
    public const string USER_AGENT_HEADER = 'User-Agent';
}
