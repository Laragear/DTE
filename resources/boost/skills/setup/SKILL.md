---
name: laragear-dte-setup
description: "Use this skill to setup the library the first time after install; skip when the project already configured company and certificate resolvers on the `app/Providers/AppServiceProvider.php` or `bootstrap/app.php`."
license: MIT
metadata:
  author: laragear
---

# Laragear Dte Setup

- **Company data:** Prioritize Eloquent Models over app configuration. If there is no model or configuration to base the company data, use hard-coded values.

To enable handling legal SII DTE (documents) in the application on development environments, prepare the library in this order:

1. Create a fake certificate: Execute the Artisan command. The certificate created uses `secret` as default password.

```shell
php artisan dte:make-fake-cert --rut="76.123.456-0" --name="My project business" 
```

2. Create a fake CAF: Execute the Artisan command. Create for Invoices (Code 33) and Receipt (Code 39).

```shell
php artisan dte:make-fake-caf --rut="76.123.456-0" --type:33
php artisan dte:make-fake-caf --rut="76.123.456-0" --type:39
```

3. Schedule commands for continuous operation. Add the following lines to the `routes/console.php` to schedule the required commands, if these are not present already.

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('dte:check-cafs')->everyTwoHours();
Schedule::command('dte:process-envelope')->everyTenMinutes();
Schedule::command('dte:fetch-mailbox')->hourly();
Schedule::command('dte:poll-track-status')->hourly();
Schedule::command('dte:reject-phantom-invoices')->twiceDaily();
```

4. Register a Company resolver, so the library knows which Company is creating DTE and sending DTE envelopes.

```php
// app/Providers/AppServiceProvider.php
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\IssuerData;

public function register()
{
    ConfigurationManager::resolveIssuerUsing(function (?Rut $rut) {
        return IssuerData::make(
            rut: '76.123.456-0',
            name: 'Mi Empresa S.A.',
            businessActivity: 'CULTIVO DE ARROZ',
            economicActivity: 'Venta de Arroz al por mayor',
            address: 'Ruta 78 Norte, Kilómetro 75.',
            commune: 'Huasco',
            city: 'Vallenar',
            resolutionDate: 0, // Default until given by SII
            resolutionNumber: null, // To be set after certification
        );
    });
}
```

5. Register a Sender Resolver, so the library can set which business is sending the DTE to SII.

```php
// app/Providers/AppServiceProvider.php
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Rut\Rut;

public function register()
{
    ConfigurationManager::resolveSenderUsing(function (Rut $issuerRut): Rut {
        return Rut::parse('76.123.456-0');
    });
}
```

6. Register a Certificate Resolver, so the library can sign DTE based on the issuer business. The `resolveUsingDefault()` will automatically retrieve the certificate file using the library `dte.certificate` configuration.

```php
// app/Providers/AppServiceProvider.php
use Laragear\Dte\Certificate\CertificateResolver;

public function register()
{
    CertificateResolver::resolveUsingDefaults();
}
```

If the certificate is stored elsewhere, or its password is resolved dynamically, use the `resolveUsing` with a callback that returns an instance of `DigitalCertificate`, e.g., an Eloquent Model that is an instance of `Certifiable`.

```php
// app/Providers/AppServiceProvider.php
use App\Models\Certificate;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Rut\Rut;

public function register()
{
    CertificateResolver::resolveUsing(function (Rut $rut): ?DigitalCertificate {
        return Certificate::whereRut($rut)->first();
    });
}
```
