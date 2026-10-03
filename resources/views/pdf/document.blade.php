<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Plantilla DTE - SII</title>
    <style>
        /* Minimal CSS for compatibility (Dompdf, wkhtmltopdf, etc.) */
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th, td {
            vertical-align: top;
        }
        /* Cajas de Borde */
        .border-table th, .border-table td {
            border: 1px solid #000;
            padding: 5px;
        }
        .border-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: left;
        }
        /* Recuadro Rojo SII */
        .sii-box {
            border: 3px solid #FF0000;
            color: #FF0000;
            text-align: center;
            padding: 15px 10px;
            font-weight: bold;
        }
        .sii-rut { font-size: 16px; margin-bottom: 10px; }
        .sii-tipo { font-size: 18px; margin-bottom: 10px; text-transform: uppercase; }
        .sii-folio { font-size: 16px; margin-bottom: 10px; }
        .sii-ciudad { font-size: 14px; }

        /* Tipografía Utilitaria */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .m-0 { margin: 0; }

        /* Timbre y Acuse */
        .timbre-placeholder {
            width: 100%;
            height: 120px;
            border: 1px dashed #999;
            display: table;
            margin: 5px 0;
        }
        .timbre-placeholder span {
            display: table-cell;
            vertical-align: middle;
            color: #999;
            font-size: 10px;
            height: 100%;
            width: 100%;
        }
        .acuse-recibo {
            border: 1px solid #000;
            padding: 10px;
            font-size: 10px;
            line-height: 1.5;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- ENCABEZADO (Emisor y Recuadro SII) -->
    <table>
        <tr>
            <!-- Datos del Emisor -->
            <td style="width: 60%; padding-right: 20px;">
                <!-- Opcional: <img src="logo.png" style="max-height: 60px;" /> -->
                <h2 class="m-0">{{ data_get($dte->payload->data, 'issuer.legal_name') }}</h2>
                <p class="m-0 bold">{{ data_get($dte->payload->data, 'issuer.business_activity') }}</p>
                <p class="m-0"><strong>Dirección:</strong> {{ data_get($dte->payload->data, 'issuer.address') }}</p>
                <p class="m-0"><strong>Comuna/Ciudad:</strong> {{ data_get($dte->payload->data, 'issuer.commune') }}, {{ data_get($dte->payload->data, 'issuer.city') }}</p>
                @if($tel = data_get($dte->payload->data, 'issuer.telephone'))
                    <p class="m-0"><strong>Teléfono:</strong> {{ $tel }}</p>
                @endif
                @if($email = data_get($dte->payload->data, 'issuer.email'))
                    <p class="m-0"><strong>Email:</strong> {{ $email }}</p>
                @endif
                @if($branch = data_get($dte->payload->data, 'issuer.branch'))
                    <p class="m-0"><strong>Sucursal:</strong> {{ $branch }}</p>
                @endif
            </td>

            <!-- Recuadro Rojo SII -->
            <td style="width: 40%;">
                <div class="sii-box">
                    <div class="sii-rut">R.U.T.: {{ $dte->issuer_rut->format() }}</div>
                    <div class="sii-tipo">
                        {{ match($dte->document_type->value) {
                            33 => 'FACTURA ELECTRÓNICA',
                            34 => 'FACTURA NO AFECTA O EXENTA ELECTRÓNICA',
                            39 => 'BOLETA ELECTRÓNICA',
                            41 => 'BOLETA NO AFECTA O EXENTA ELECTRÓNICA',
                            52 => 'GUÍA DE DESPACHO ELECTRÓNICA',
                            56 => 'NOTA DE DÉBITO ELECTRÓNICA',
                            61 => 'NOTA DE CRÉDITO ELECTRÓNICA',
                            default => 'DOCUMENTO ELECTRÓNICO'
                        } }}
                    </div>
                    <div class="sii-folio">N° {{ $dte->folio }}</div>
                    <div class="sii-ciudad">S.I.I. - {{ mb_strtoupper(data_get($dte->payload->data, 'issuer.city', 'SANTIAGO')) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- DATOS DEL RECEPTOR -->
    <table class="border-table">
        <tr>
            <td style="width: 15%;" class="bold">Señor(es):</td>
            <td style="width: 45%;">{{ data_get($dte->payload->data, 'receiver.legal_name') }}</td>
            <td style="width: 15%;" class="bold">R.U.T.:</td>
            <td style="width: 25%;">{{ $dte->receiver_rut->format() }}</td>
        </tr>
        <tr>
            <td class="bold">Giro:</td>
            <td>{{ data_get($dte->payload->data, 'receiver.business_activity') }}</td>
            <td class="bold">Fecha Emisión:</td>
            <td>{{ $dte->issued_on?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="bold">Dirección:</td>
            <td>{{ data_get($dte->payload->data, 'receiver.address') }}</td>
            <td class="bold">Cond. Pago:</td>
            <td>{{ data_get($dte->payload->data, 'payment.condition') }}</td>
        </tr>
        <tr>
            <td class="bold">Comuna:</td>
            <td>{{ data_get($dte->payload->data, 'receiver.commune') }}, {{ data_get($dte->payload->data, 'receiver.city') }}</td>
            <td class="bold">Vencimiento:</td>
            <td>{{ data_get($dte->payload->data, 'payment.expiration_date') }}</td>
        </tr>
    </table>

    <!-- REFERENCIAS (Opcional, útil para Notas de Crédito/Débito y Guías) -->
    @if(!empty(data_get($dte->payload->data, 'references')))
    <table class="border-table">
        <thead>
        <tr>
            <th colspan="4">Referencias del Documento</th>
        </tr>
        <tr>
            <th>Tipo Documento Referencia</th>
            <th>Folio</th>
            <th>Fecha</th>
            <th>Razón / Observación</th>
        </tr>
        </thead>
        <tbody>
        @foreach(data_get($dte->payload->data, 'references', []) as $ref)
        <tr>
            <td>{{ data_get($ref, 'document_type') }}</td>
            <td>{{ data_get($ref, 'folio') }}</td>
            <td>{{ data_get($ref, 'date') }}</td>
            <td>{{ data_get($ref, 'reason') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    <!-- DETALLE DE ITEMS -->
    <table class="border-table" style="min-height: 300px;">
        <thead>
        <tr>
            <th style="width: 10%;" class="text-center">Código</th>
            <th style="width: 40%;">Descripción</th>
            <th style="width: 10%;" class="text-center">Cantidad</th>
            <th style="width: 15%;" class="text-right">Precio Unit.</th>
            <th style="width: 10%;" class="text-right">% Desc.</th>
            <th style="width: 15%;" class="text-right">Valor</th>
        </tr>
        </thead>
        <tbody>
        @foreach(data_get($dte->payload->data, 'items', []) as $item)
        <tr>
            <td class="text-center">{{ data_get($item, 'code') }}</td>
            <td>{{ data_get($item, 'name') }} {{ data_get($item, 'description') }}</td>
            <td class="text-center">{{ data_get($item, 'quantity') }}</td>
            <td class="text-right">${{ number_format(data_get($item, 'unit_price', 0), 0, ',', '.') }}</td>
            <td class="text-right">{{ data_get($item, 'discount_percentage', 0) }}%</td>
            <td class="text-right">${{ number_format(round(data_get($item, 'quantity', 0) * data_get($item, 'unit_price', 0) * (1 - data_get($item, 'discount_percentage', 0) / 100)), 0, ',', '.') }}</td>
        </tr>
        @endforeach

        <!-- Espaciador para empujar los totales hacia abajo si hay pocos items -->
        <tr>
            <td colspan="6" style="border: none; height: 100px;"></td>
        </tr>
        </tbody>
    </table>

    <!-- FOOTER ROW 1: Timbre y Totales -->
    <table style="border: none; width: 100%;">
        <tr>
            <!-- Zona Izquierda (Timbre) -->
            <td style="width: 60%; padding-right: 20px; vertical-align: bottom;">

                <!-- TIMBRE ELECTRÓNICO -->
                <div style="text-align: center; width: 300px; margin: 0 auto;">
                    <div class="timbre-placeholder text-center" style="border:none;">
                        <img src="{{ $barcode }}" alt="Timbre Electrónico SII" style="width: 100%; max-height: 110px;">
                    </div>
                    <p class="bold m-0">Timbre Electrónico SII</p>
                    <p class="m-0">Res. {{ data_get($dte->payload->data, 'issuer.resolution_number') }} de {{ \Carbon\Carbon::parse(data_get($dte->payload->data, 'issuer.resolution_date'))->year }} - Verifique documento: www.sii.cl</p>
                </div>
            </td>

            <!-- Zona Derecha (Totales) -->
            <td style="width: 40%; vertical-align: bottom;">
                <table class="border-table">
                    @if($dte->amount_exempt > 0)
                    <tr>
                        <td class="bold text-right" style="width: 50%;">Monto Exento:</td>
                        <td class="text-right" style="width: 50%;">${{ number_format($dte->amount_exempt, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($dte->amount_net > 0)
                    <tr>
                        <td class="bold text-right">Monto Neto:</td>
                        <td class="text-right">${{ number_format($dte->amount_net, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($dte->amount_taxes > 0)
                    <tr>
                        <td class="bold text-right">IVA (19%):</td>
                        <td class="text-right">${{ number_format($dte->amount_taxes, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @foreach($dte->taxes ?? [] as $taxType => $amount)
                    <tr>
                        <td class="bold text-right">Imp. Adicional ({{ $taxType }}):</td>
                        <td class="text-right">${{ number_format($amount, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    <tr>
                        <td class="bold text-right" style="font-size: 14px;">TOTAL:</td>
                        <td class="text-right bold" style="font-size: 14px;">${{ number_format($dte->amount_total, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- FOOTER ROW 2: ACUSE DE RECIBO (Ley 19.983) a ancho completo -->
    @if(in_array($dte->document_type->value, [33, 34, 52]) && $cedible)
    <div class="acuse-recibo" style="margin-top: 10px; width: 100%; box-sizing: border-box;">
        <table style="margin-bottom: 5px; width: 100%; border: none;">
            <tr>
                <td style="width: 50px; border: none; padding-bottom: 5px;">Nombre:</td>
                <td style="border-bottom: 1px solid #000; width: 30%;"></td>
                <td style="width: 45px; border: none; padding-left: 10px; padding-bottom: 5px;">R.U.T.:</td>
                <td style="border-bottom: 1px solid #000; width: 15%;"></td>
                <td style="width: 55px; border: none; padding-left: 10px; padding-bottom: 5px;">Recinto:</td>
                <td style="border-bottom: 1px solid #000;"></td>
            </tr>
            <tr>
                <td style="border: none; padding-top: 5px;">Fecha:</td>
                <td style="border-bottom: 1px solid #000; padding-top: 5px;"></td>
                <td style="border: none; padding-left: 10px; padding-top: 5px;">Firma:</td>
                <td colspan="3" style="border-bottom: 1px solid #000; padding-top: 5px;"></td>
            </tr>
        </table>
        <p style="text-align: justify; margin: 0;">
            "El acuse de recibo que se declara en este acto, de acuerdo a lo dispuesto en la letra b) del Art. 4°, y la letra c) del Art. 5° de la Ley 19.983, acredita que la entrega de mercaderías o servicio(s) prestado(s) ha(n) sido recibido(s)."
        </p>
    </div>
    @endif


</div>
</body>
</html>

