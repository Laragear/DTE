<?php

namespace Laragear\Dte\Enums;

/**
 * SII Reclamo (claim) action codes for vendor invoice acceptance/rejection.
 *
 * @see https://www.sii.cl/leyes_y_normativas/leyes/19983.pdf
 */
enum ReclamoAction: string
{
    /**
     * Aceptación Comercial del Documento.
     */
    case Accept = 'ACD';

    /**
     * Reclamo Comercial al Contenido del Documento.
     */
    case Reject = 'RCD';

    /**
     * Reclamo por Falta Total de Entrega de Mercaderías.
     */
    case RejectGoods = 'ERM';

    /**
     * Reclamo por Falta Parcial de Entrega de Mercaderías.
     */
    case RejectPartial = 'RFP';

    /**
     * Recibo de Mercaderías o Servicios Prestados.
     */
    case GoodsReceipt = 'RMA';
}
