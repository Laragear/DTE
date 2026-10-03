<?php

namespace Laragear\Dte\Enums;

enum RcvType: string
{
    /** Purchases (compras) */
    case Purchases = 'compras';

    /** Sales (ventas) */
    case Sales = 'ventas';
}
