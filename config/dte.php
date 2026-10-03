<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Operational Environment
     |--------------------------------------------------------------------------
     |
     | By default, the library automatically inherits the application environment
     | (APP_ENV). Non-testing and non-production environments default to "local"
     | environment. Set this only if you need an explicit environment override.
     |
     | Options: 'local', 'testing', 'certification', 'production', or null.
     |
     */

    'environment' => env('DTE_ENV'),

    /*
     |--------------------------------------------------------------------------
     | Tax Configuration
     |--------------------------------------------------------------------------
     |
     | The current national IVA rate as an integer percentage. This is kept on
     | the configuration because of future legislative rate changes: when the
     | IVA changes, we will only needs a single edit here to propagate that.
     |
     */

    'taxes' => [
        'iva_rate' => env('DTE_IVA_RATE', 19),
    ],

    /*
     |--------------------------------------------------------------------------
     | XSD Schema Validation
     |--------------------------------------------------------------------------
     |
     | You can enable XSD schema validation against official SII schemas before
     | being signed and sent. This catches structural errors early but latency
     | is added. Enable this when you require to modify the DTE XML directly.
     |
     */

    'validation' => [
        'xsd_enabled' => env('DTE_XSD_VALIDATION', false),
        'xsd_path' => resource_path('xsd'),
    ],

    /*
     |--------------------------------------------------------------------------
     | Digital Certificate Configuration
     |--------------------------------------------------------------------------
     |
     | When the app operates as a single-business environment this library will
     | look for the certificate using this storage drive and path. If your app
     | is multi-tenant, use `Certificate::resolveCertificateUsing()` instead.
     |
     | If you don't have a certificate on development, use `dte:make-fake-cert`.
     |
     */

    'certificate' => [
        'disk' => 'local',
        'path' => 'dte/certificate.p12',
        'password' => 'secret',
    ],

    /*
     |--------------------------------------------------------------------------
     | Envelope Packaging & Batching Configuration
     |--------------------------------------------------------------------------
     |
     | DTE are grouped by sender and type into an envelope. The configuration
     | tells how the library should tie them together: how much documents to
     | process, how much to wait for the next, and the max holding minutes.
     |
     */

    'envelopes' => [
        // Maximum number of DTE documents packaged into a single EnvioDTE XML
        // file. Adjust based on your volume. If you're a heavy hitter, you can
        // add more but your application may crash due to memory exhaustion.
        'max' => [
            'documents' => env('DTE_MAX_DOCUMENTS_DOCUMENTS', 20),

            // Maximum number of Boleta (39/41) documents per EnvioBOLETA envelope.
            // SII REST API recommends batches of ~50 boletas per upload for optimal throughput.
            'receipts' => env('DTE_MAX_DOCUMENTS_RECEIPTS', 50),
        ],

        // Delay / backoff duration (in seconds) to wait before generating or
        // dispatching the next envelope chunk. This avoids hammering the SII
        // servers and basically being throttled or rate-limited by them.
        'backoff_seconds' => 60,

        // Hard time limit (in minutes) to force sending pending DTEs inside the
        // envelope, regardless of max_documents or backoff. This ensures the
        // envelope is always sent, instead of waiting until it is filled.
        'max_holding_minutes' => 30,

        // Maximum number of times a DTE can be structurally released and
        // repacked into a new envelope before it is permanently rejected.
        'max_retries' => 3,

        // Maximum backoff in seconds. While local queue drivers support backoff
        // relatively far, some drivers do not, like AWS SQS/SNS which support
        // only a maximum of 15 minutes. Adjust to your app infrastructure.
        'max_backoff' => 60 * 15,
    ],

    /*
     |--------------------------------------------------------------------------
     | Polling
     |--------------------------------------------------------------------------
     |
     | SII requires a minimum wait before the first status poll after an envelope
     | is uploaded, based on its size: at least 2 minutes (< 30 KB) or 6 minutes
     | (>= 30 KB). The offsets below only ADD seconds to the base waiting time.
     |
     */

    'polling' => [
        'delay_under_30kb' => env('DTE_POLL_DELAY_SMALL', 0),
        'delay_over_30kb' => env('DTE_POLL_DELAY_LARGE', 0),
    ],

    /*
     |--------------------------------------------------------------------------
     | Cache
     |--------------------------------------------------------------------------
     |
     | This library will conveniently store data like tokens or other inside the
     | default application cache. Here you can adjust if you want to use other
     | cache storage driver and the prefix to use to avoid cache collisions.
     |
     */

    'cache' => [
        'store' => null,
        'prefix' => env('DTE_CACHE_PREFIX', 'dte'),
    ],

    /*
     |--------------------------------------------------------------------------
     | SOAP Gateway Configuration
     |--------------------------------------------------------------------------
     |
     | SII revokes SOAP session tokens after 1 hour (3600 seconds) since their
     | last use, refreshing the window every time these are used. This states
     | how long the library caches a token. It must not exceed 3600 seconds.
     |
     */

    'soap' => [
        'token_ttl' => env('DTE_SOAP_TOKEN_TTL', 60 * 60),
    ],

    /*
     |--------------------------------------------------------------------------
     | Queue
     |--------------------------------------------------------------------------
     |
     | Your DTE are not sent sync. These require building an XML and signed with
     | a certificate, and DTE Envelopes too. The library will push the logic in
     | a queued job guaranteeing the app request lifecycle is kept responsive.
     |
     | The "track" is basically the job queue to check the envelope status.
     |
     */

    'queue' => [
        'dte' => [
            'connection' => env('DTE_QUEUE_DTE_CONNECTION'),
            'name' => env('DTE_QUEUE_DTE', 'default'),
        ],
        'envelope' => [
            'connection' => env('DTE_QUEUE_ENVELOPE_CONNECTION'),
            'name' => env('DTE_QUEUE_ENVELOPE'),
        ],
        'track' => [
            'connection' => env('DTE_QUEUE_TRACK_CONNECTION'),
            'name' => env('DTE_QUEUE_TRACK'),
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | CAF Ratio reporting
     |--------------------------------------------------------------------------
     |
     | When using folios, these will eventually deplete. To avoid downtimes, you
     | can set a custom "depletion ratio". When the ratio is exceeded, an event
     | will be fired, so you can listen to it and send notifications or mails.
     |
     */

    'caf' => [
        'depletion_threshold' => env('DTE_CAF_DEPLETION_THRESHOLD', 10),
    ],

    /*
     |--------------------------------------------------------------------------
     | PDF generation
     |--------------------------------------------------------------------------
     |
     | When generating documents PDF this config will be used to store the PDF
     | in the application. When "disk" is "null", the PDF will be saved using
     | the default application disk. The prefix allows to separate the PDFs.
     |
     */

    'pdf' => [
        'driver' => env('DTE_PDF_DRIVER', 'dompdf'),
        'disk' => null,
        'prefix' => 'dte/pdf',
        'views' => [
            'default' => 'dte::pdf.document',
            '39' => 'dte::pdf.document',
            '41' => 'dte::pdf.document',
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Exclusive DTE Interchange Email (Casilla de Intercambio)
     |--------------------------------------------------------------------------
     |
     | When you register to the SII, you will need to point a System-to-system
     | interchange mailbox for receiving XML vendor invoices (EnvioDTE) and
     | commercial responses (RespuestaDTE). This controls how to use it.
     |
     */

    'dim' => [
        // The Laravel Mailer connection (from config/mail.php) used to SEND interchange emails.
        // Set to null to use your application's default mailer.
        'mailer' => env('DTE_DIM_MAILER'),

        // Should the DIM addresses from SII be cached, and by how much.
        'addresses' => [
            'cache' => env('DTE_EXCHANGE_CACHE_DIM', true),
            'days' => 30,
        ],

        // Automatically email generated Acuse de Recibo (Commercial receipts) back to the sender
        // when accepting an inbound invoice via the Claim facade/service. Opt-out if needed.
        'auto_email_receipts' => env('DTE_EXCHANGE_AUTO_EMAIL_RECEIPTS', true),

        // Automatically distribute DTEs grouped by receiver to their respective mailboxes.
        // This is done to comply with SII: the business must send a DTE copy to the other
        // business. This library will do it only when its envelope is marked as ok by SII.
        'auto_send_interchange' => env('DTE_AUTO_SEND_INTERCHANGE', true),

        // When an email comes from addresses with these prefixes, it won't be processed.
        'disallowed_prefixes' => 'admin,ayuda,consulta,contacto,cotiz,gerencia,hola,info,soporte,test,ventas',

        // This also avoids personal email addresses services to avoid scams/errors/poking.
        'disallowed_domains' => 'gmail.com,outlook.com,hotmail.com,yahoo.com,icloud.com,live.com',
    ],

    'mailbox' => [
        'default' => env('DTE_MAILBOX_DRIVER', 'imap'),

        // Number of unread emails to fetch per batch/page to control memory and API limits.
        'batch_size' => env('DTE_EXCHANGE_BATCH_SIZE', 50),

        // Driver configuration
        'drivers' => [
            'imap' => [
                'host' => env('DTE_IMAP_HOST'),
                'port' => env('DTE_IMAP_PORT', 993),
                'encryption' => env('DTE_IMAP_ENCRYPTION', 'ssl'),
                'username' => env('DTE_IMAP_USERNAME'),
                'password' => env('DTE_IMAP_PASSWORD'),
            ],

            'aws_ses' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            ],

            'microsoft' => [
                'client_id' => env('MICROSOFT_GRAPH_CLIENT_ID'),
                'client_secret' => env('MICROSOFT_GRAPH_CLIENT_SECRET'),
                'tenant_id' => env('MICROSOFT_GRAPH_TENANT_ID'),
            ],

            'google' => [
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
            ],
        ],
    ],
];
