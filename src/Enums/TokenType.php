<?php

namespace Laragear\Dte\Enums;

/**
 * The SII service family a token authenticates against.
 *
 * Each service has its own token flow and its own cache entry.
 */
enum TokenType: string
{
    /** Legacy SOAP Web Services (CrSeed/GetTokenFromSeed, QueryEst*, Upload). */
    case Soap = 'soap';

    /** Boleta REST API (boleta.electronica.*). */
    case Rest = 'rest';
}
