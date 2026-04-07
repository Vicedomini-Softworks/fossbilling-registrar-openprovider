# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A FOSSBilling registrar adapter plugin that integrates OpenProvider's domain API. It is deployed by copying the `library/` directory into the root of a FOSSBilling installation — there is no build step.

## No Build / Lint / Test Infrastructure

There is no Composer, no test suite, no linter config, and no CI pipeline. `phpcs:ignoreFile` is set in both PHP files. Development is done by editing the two PHP files and testing manually against the OpenProvider sandbox API.

- **Live API**: `https://api.openprovider.eu`
- **Sandbox API**: `http://api.sandbox.openprovider.nl:8480`

## Architecture

One file — `library/Registrar/Adapter/OpenProvider.php` — extends `Registrar_AdapterAbstract` (FOSSBilling base class, not in this repo). HTTP calls use the Symfony HttpClient injected by the base class via `$this->getHttpClient()`. Logging uses `$this->getLog()`.

### Key design details

- All four contact roles (owner, admin, tech, billing) map to a single OpenProvider customer handle derived from `$domain->getContactAdmin()`.
- Customer lookup is by email (`_findCustomerByEmail`); if not found, a new customer is created. Pass `$updateExisting = true` to `_getOrCreateCustomer` to also push contact updates.
- Domain operations that need an OpenProvider numeric ID first call `_getDomainId`, which does `GET /domains?full_name=...`.
- TLD stripping (`_stripTld`) removes leading/trailing dots from the FOSSBilling TLD string.
- The bearer token is fetched once per adapter instance via `POST /v1beta/auth/login` and cached in `$this->accessToken`.
