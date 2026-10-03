<?php

namespace Laragear\Dte\Enums;

enum InboundDteStatus: string
{
    public const self DEFAULT = self::Received;

    /** The supplier document was received but has not been validated. */
    case Received = 'received';

    /** The SII reports a document whose XML has not been received. */
    case PhantomPending = 'phantom_pending';

    /** Official SII records do not recognize the received document. */
    case Forged = 'forged';

    /** Technical validation rejected the document with IC code 2. */
    case TechnicalRejected = 'technical_rejected';

    /** Technical validation accepted the document with IC code 0. */
    case TechnicalAccepted = 'technical_accepted';

    /** Technical validation found discrepancies with IC code 1. */
    case TechnicalDiscrepancy = 'technical_discrepancy';

    /** The document is waiting for a commercial decision. */
    case CommercialPending = 'commercial_pending';

    /** The receiver commercially accepted the document using ACD. */
    case CommercialAccepted = 'commercial_accepted';

    /** The receiver commercially rejected or claimed the document using RCD or RFT. */
    case CommercialRejected = 'commercial_rejected';

    /** The receiver confirmed receipt of goods or services using ER. */
    case GoodsReceipt = 'goods_receipt';

    /**
     * Determine if inbound processing has reached a final state.
     */
    public function isTerminalState(): bool
    {
        return match ($this) {
            self::Forged, self::TechnicalRejected, self::CommercialAccepted, self::CommercialRejected => true,
            default => false,
        };
    }
}
