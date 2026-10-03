<?php

namespace Laragear\Dte\Enums;

enum ModifierTarget: int
{
    public const self DEFAULT = self::Default;

    /**
     * Affected / Taxable items (default); Items afectos
     *
     * IndExeDR is omitted in XML.
     */
    case Default = 0;

    /**
     * Exempt items; Items exentos
     *
     * IndExeDR = 1
     */
    case Exempt = 1;

    /**
     * Non-billable items; Items no facturables
     *
     * IndExeDR = 2
     */
    case NonBillable = 2;
}
