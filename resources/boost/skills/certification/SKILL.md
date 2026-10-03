---
name: laragear-dte-certification
description: "Use this skill to prepare the application to certification subsequent production (real) environment. Don't use this if the business does not have a real Digital Certificate or is still on development."
license: MIT
metadata:
  author: laragear
---

# Laragear Dte Certification

The application requires to be _certified_ by SII before real operation on production environments. The library requires a real Digital Certificate (not the fake created by the library for development/testing) and to follow the certification process. If this has not been set up, use the apropiate skill to do it.  

Once the business complies with _"Pre-Certificación"_, it will receive a `.txt` file with data to create the different sets the SII will require to handle. The set will have an "Attention Number", like `5145080`.

## Prerequisites

1. Truncate the tables: Delete all data from the tables to start with a fresh certification process for the very first time.

```bash
php artisan dte:purge
```

2. Visit the [Certification Portal](https://maullin.sii.cl/cvc/dte/certificacion_dte.html) at "Mantención de Usuarios" and allow the user to authorize CAF ("Solicitar Folios").
3. On the same Certification Portal, download a CAF to create the required DTE ("Solicitud de Timbraje Electrónico").(https://maullin.sii.cl/cvc/dte/certificacion_dte.html)).
4. Upload/Load the CAF to the library.

## Documentation

Follow the certification documentation `CERTIFICATION.md` at the root if this package. It's usually found in `vendor/laragear/dte/CERTIFICATION.md` (or equivalent path).

## Existing companies

The certification process requires a Test Set of documents with existing (real) business receivers. Use the [targets.json](references/targets.json) for a stable set of target businesses to use on these documents. 
