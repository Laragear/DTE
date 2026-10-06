<?php

namespace Laragear\Dte\Validation;

/**
 * XSD-derived validation rules for DTE sections.
 *
 * @generated from resources/xsd/DTE_v10.xsd + resources/xsd/SiiTypes_v10.xsd — DO NOT EDIT.
 * Run `composer dte:rules` to regenerate.
 */
final class DteRules
{
    public const string SOURCE_HASH = '83961e3cb9f29b069bb38088823e5d9f426b8ed463d04f3dcc27d0ee7e6cf39e';

    public const array COMMISSIONS = [
        'items' => 'present|array|max:20',
        'items.*.description' => 'required|string|max:60|min:1',
        'items.*.exempt' => 'nullable|integer|min:0|max_digits:18',
        'items.*.net' => 'nullable|integer|min:0|max_digits:18',
        'items.*.rate' => 'nullable|numeric|min:0.01|max:999.99|regex:/^\d+(\.\d{1,2})?$/',
        'items.*.tax' => 'nullable|integer|min:0|max_digits:18',
        'items.*.type' => 'required|string|min:1|in:C,O',
    ];

    public const array DETAIL_ITEMS = [
        'items' => 'present|array|max:60',
        'items.*.code' => 'nullable|string|max:35',
        'items.*.code_type' => 'nullable|string|max:10',
        'items.*.description' => 'nullable|string|max:1000',
        'items.*.discount_amount' => 'nullable|numeric|min:0',
        'items.*.discount_percentage' => 'nullable|numeric|min:0|max:999.99|regex:/^\d+(\.\d{1,2})?$/',
        'items.*.exempt' => 'required|boolean',
        'items.*.name' => 'required|string|max:80',
        'items.*.quantity' => 'nullable|numeric|min:0|max:999999999999.999999|regex:/^\d+(\.\d{1,6})?$/',
        'items.*.taxes' => 'nullable|array|max:2',
        'items.*.taxes.*' => 'required|integer|min:0|max_digits:18',
        'items.*.unit' => 'nullable|string|max:4',
        'items.*.unit_price' => 'nullable|numeric|min:0|max:999999999999.999999|regex:/^\d+(\.\d{1,6})?$/',
    ];

    public const array DOCUMENT = [
        'document_type' => 'required|integer|in:30,32,33,34,39,41,43,46,52,56,61',
        'folio' => 'required|integer|min:1|max_digits:10',
        'ind_mnt_neto' => 'nullable|integer|in:0,1,2',
        'ind_traslado' => 'nullable|integer|in:1,2,3,4,5,6,7,8,9|min:1',
        'issued_on' => 'required|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
        'non_rebillable' => 'nullable|integer|in:1|min:1',
        'tipo_despacho' => 'nullable|integer|in:1,2,3|min:1',
    ];

    public const array EMISSION_GEOREF = [
        'latitude' => 'required|string|max:30',
        'longitude' => 'required|string|max:30',
        'reference_system' => 'required|integer|max:9|min:1',
    ];

    public const array GLOBAL_MODIFIER = [
        'description' => 'nullable|string|max:45',
        'target' => 'nullable|integer|in:0,1,2',
        'type' => 'required|string|max:1|min:1|in:D,R',
        'value' => 'required|numeric|min:0.01|max:9999999999999999.99|regex:/^\d+(\.\d{1,2})?$/',
        'value_type' => 'required|string|max:1|min:1|in:%,$',
    ];

    public const array GLOBAL_MODIFIERS = [
        'items' => 'present|array|max:20',
        'items.*.description' => 'nullable|string|max:45',
        'items.*.target' => 'nullable|integer|in:0,1,2',
        'items.*.type' => 'required|string|max:1|min:1|in:D,R',
        'items.*.value' => 'required|numeric|min:0.01|max:9999999999999999.99|regex:/^\d+(\.\d{1,2})?$/',
        'items.*.value_type' => 'required|string|max:1|min:1|in:%,$',
    ];

    public const array HEADER_ID_DOC = [
        'document_type' => 'required|integer|in:30,32,33,34,39,41,43,46,52,56,61',
        'exempt_amount_override' => 'nullable|integer|min:0|max_digits:18',
        'ind_mnt_neto' => 'nullable|integer|in:0,1,2',
        'ind_traslado' => 'nullable|integer|in:1,2,3,4,5,6,7,8,9|min:1',
        'issued_on' => 'required|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
        'non_rebillable' => 'nullable|integer|in:1|min:1',
        'payment.condition' => 'nullable|integer|in:1,2,3|min:1',
        'payment.expiration_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
        'payments' => 'nullable|array|max:30',
        'payments.*.amount' => 'required|integer|min:0|max_digits:18',
        'payments.*.date' => 'required|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
        'payments.*.gloss' => 'nullable|string|max:40',
        'tax_exempt' => 'nullable|boolean',
        'tipo_despacho' => 'nullable|integer|in:1,2,3|min:1',
    ];

    public const array HEADER_OTHER_CURRENCY = [
        'commercial_margin' => 'nullable|numeric|min:0.0001|max:99999999999999.9999|regex:/^\d+(\.\d{1,4})?$/',
        'currency' => 'required|string|max:15|in:BOLIVAR,BOLIVIANO,CHELIN,CORONA DIN,CORONA NOR,CORONA SC,CRUZEIRO REAL,DIRHAM,DOLAR AUST,DOLAR CAN,DOLAR HK,DOLAR NZ,DOLAR SIN,DOLAR TAI,DOLAR USA,DRACMA,ESCUDO,EURO,FLORIN,FRANCO BEL,FRANCO FR,FRANCO SZ,GUARANI,LIBRA EST,LIRA,MARCO AL,MARCO FIN,NUEVO SOL,OTRAS MONEDAS,PESETA,PESO,PESO CL,PESO COL,PESO MEX,PESO URUG,RAND,RENMINBI,RUPIA,SUCRE,YEN',
        'exchange_rate' => 'nullable|numeric|min:0.0001|max:999999.9999|regex:/^\d+(\.\d{1,4})?$/',
        'exempt' => 'nullable|numeric|min:0.0001|max:99999999999999.9999|regex:/^\d+(\.\d{1,4})?$/',
        'livestock_base' => 'nullable|numeric|min:0.0001|max:99999999999999.9999|regex:/^\d+(\.\d{1,4})?$/',
        'net' => 'nullable|numeric|min:0.0001|max:99999999999999.9999|regex:/^\d+(\.\d{1,4})?$/',
        'tax' => 'nullable|numeric|min:0.0001|max:99999999999999.9999|regex:/^\d+(\.\d{1,4})?$/',
        'total' => 'required|numeric|min:0.0001|max:99999999999999.9999|regex:/^\d+(\.\d{1,4})?$/',
        'unretained_tax' => 'nullable|numeric|min:0.0001|max:99999999999999.9999|regex:/^\d+(\.\d{1,4})?$/',
        'withheld_taxes' => 'nullable|array|max:20',
        'withheld_taxes.*.amount' => 'required|numeric|min:0.0001|max:99999999999999.9999|regex:/^\d+(\.\d{1,4})?$/',
        'withheld_taxes.*.rate' => 'nullable|numeric|min:0.01|max:999.99|regex:/^\d+(\.\d{1,2})?$/',
        'withheld_taxes.*.type' => 'required|integer|max:3|in:14,15,16,17,18,19,23,24,25,26,27,28,30,31,32,33,34,35,36,37,38,39,40,41,44,45,46,47,48,49,50,51,52,53,54,55,271,301,321,331,341,361,371,481',
    ];

    public const array ISSUER = [
        'activity' => 'required|string|max:80|min:1',
        'activity_code' => 'required|array|max:4',
        'activity_code.*' => 'integer|min:1|max_digits:6',
        'address' => 'required|string|max:70',
        'branch' => 'nullable|string|max:20',
        'city' => 'nullable|string|max:20',
        'commune' => 'required|string|max:20',
        'email' => 'nullable|email|max:80',
        'name' => 'required|string|max:100',
        'resolution_date' => 'required|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
        'resolution_number' => 'required|integer|min:0|max_digits:6',
        'rut' => 'required|sii_rut',
        'telephone' => 'nullable|string|max:20',
    ];

    public const array ITEM = [
        'code' => 'nullable|string|max:35',
        'code_type' => 'nullable|string|max:10',
        'description' => 'nullable|string|max:1000',
        'discount_amount' => 'nullable|numeric|min:0',
        'discount_percentage' => 'nullable|numeric|min:0|max:999.99|regex:/^\d+(\.\d{1,2})?$/',
        'exempt' => 'required|boolean',
        'name' => 'required|string|max:80',
        'quantity' => 'nullable|numeric|min:0|max:999999999999.999999|regex:/^\d+(\.\d{1,6})?$/',
        'taxes' => 'nullable|array|max:2',
        'taxes.*' => 'required|integer|min:0|max_digits:18',
        'unit' => 'nullable|string|max:4',
        'unit_price' => 'nullable|numeric|min:0|max:999999999999.999999|regex:/^\d+(\.\d{1,6})?$/',
    ];

    public const array PAYMENT_TERM = [
        'condition' => 'required|integer|in:1,2,3',
        'expiration_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
    ];

    public const array RECEIVER = [
        'activity' => 'nullable|string|max:40',
        'address' => 'nullable|string|max:70',
        'city' => 'nullable|string|max:20',
        'commune' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:80',
        'name' => 'required|string|max:100',
        'rut' => 'required|sii_rut',
    ];

    public const array REFERENCE = [
        'date' => 'nullable|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
        'document_type' => 'required',
        'folio' => 'required|string|max:18|min:1',
        'reason' => 'nullable|string|max:90',
        'reference_code' => 'nullable|integer|in:1,2,3|min:1',
    ];

    public const array REFERENCES = [
        'items' => 'present|array|max:40',
        'items.*.date' => 'nullable|date|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2050-12-31',
        'items.*.document_type' => 'required',
        'items.*.folio' => 'required|string|max:18|min:1',
        'items.*.reason' => 'nullable|string|max:90',
        'items.*.reference_code' => 'nullable|integer|in:1,2,3|min:1',
    ];

    public const array SUBTOTALS = [
        'items' => 'present|array|max:20',
        'items.*.additional_tax' => 'nullable|numeric|min:0.01|max:9999999999999999.99|regex:/^\d+(\.\d{1,2})?$/',
        'items.*.description' => 'required|string|max:40',
        'items.*.detail_lines' => 'nullable|array|max:60',
        'items.*.detail_lines.*' => 'integer|min:1',
        'items.*.exempt' => 'nullable|numeric|min:0.01|max:9999999999999999.99|regex:/^\d+(\.\d{1,2})?$/',
        'items.*.net' => 'nullable|numeric|min:0.01|max:9999999999999999.99|regex:/^\d+(\.\d{1,2})?$/',
        'items.*.order' => 'nullable|integer|max:99|min:1',
        'items.*.tax' => 'nullable|numeric|min:0.01|max:9999999999999999.99|regex:/^\d+(\.\d{1,2})?$/',
        'items.*.total' => 'nullable|numeric|min:0.01|max:9999999999999999.99|regex:/^\d+(\.\d{1,2})?$/',
    ];

    public const array TIMBER_HANDLING = [
        'conaf_plan_code' => 'required|string|max:40',
        'destination_block' => 'nullable|integer|min:1|max_digits:6',
        'destination_commune' => 'nullable|integer|min:1|max_digits:6',
        'destination_latitude' => 'nullable|string|max:30',
        'destination_longitude' => 'nullable|string|max:30',
        'destination_property' => 'nullable|integer|min:1|max_digits:6',
        'logging_notice' => 'nullable|string|max:40',
        'origin_block' => 'required|integer|min:1|max_digits:6',
        'origin_commune' => 'required|integer|min:1|max_digits:6',
        'origin_latitude' => 'required|string|max:30',
        'origin_longitude' => 'required|string|max:30',
        'origin_property' => 'required|integer|min:1|max_digits:6',
        'reference_system' => 'required|integer|min:1|max_digits:1',
    ];

    public const array TOTALS = [
        'exempt' => 'nullable|integer|min:0|max_digits:18',
        'net' => 'nullable|integer|min:0|max_digits:18',
        'non_billable' => 'nullable|integer|max_digits:18',
        'tax' => 'nullable|integer|min:0|max_digits:18',
        'taxes' => 'nullable|array|max:20',
        'taxes.*' => 'integer|min:0|max_digits:18',
        'total' => 'required|integer|min:0|max_digits:18',
    ];

    public const array TRANSPORT = [
        'arrival_at' => 'nullable|date|date_format:Y-m-d H:i:s|after_or_equal:2003-04-01 00:00:00|before_or_equal:2050-12-31 23:59:59',
        'carrier_rut' => 'nullable|sii_rut',
        'departure_at' => 'nullable|date|date_format:Y-m-d H:i:s|after_or_equal:2003-04-01 00:00:00|before_or_equal:2050-12-31 23:59:59',
        'destination_address' => 'nullable|string|max:70',
        'destination_city' => 'nullable|string|max:20',
        'destination_commune' => 'nullable|string|max:20',
        'driver_name' => 'nullable|string|max:30',
        'driver_rut' => 'nullable|sii_rut',
        'trailer_plate' => 'nullable|string|max:8',
        'vehicle_plate' => 'nullable|string|max:8',
    ];
}
