# Changelog

## [Unreleased] - 2026-04-09

### Fixed

- **`getDomainDetails`: gestione `admin_handle` null**: fallback su `owner_handle` se `admin_handle` è assente; skip idratazione contatti se entrambi i campi sono null, evitando `TypeError` su domini senza handle amministrativo.
- **`deleteDomain`: parametri DELETE ora come query string**: secondo la spec OpenProvider v1beta, `skip_soft_quarantine`, `force_delete` e `type` sono query parameters, non campi del body JSON.

## 2026-04-08

### Added

- **`docker-compose.yml`**: ambiente di sviluppo locale con `ghcr.io/blacksoulgem95/fossbilling-railway:latest` e MySQL 8.0. Il file `OpenProvider.php` viene montato direttamente da `./library/Registrar/Adapter/` per sviluppo live senza rebuild.

## 2026-04-07

### Changed

- **Compatibilità con l'API reale di FOSSBilling 0.7.x**: verificata la sorgente effettiva di `Registrar_Domain` e `Registrar_AdapterAbstract` su GitHub; corretti gli errori introdotti in precedenza basati su documentazione non accurata.
- Rinominato `isDomainCanBeTransferred` in `isDomaincanBeTransferred` per allinearsi alla firma definita dalla classe base.
- `$config` reso `public` per seguire la convenzione degli adapter FOSSBilling ufficiali.
- Rimossi i return type hint PHP dai metodi pubblici astratti: la classe base non li dichiara, e aggiungerli crea incompatibilità con il caricamento dinamico dell'adapter da parte del framework.
- Logging cambiato da `$this->getLog()->debug()` a `$this->getLog()->info()`, più coerente con l'uso negli adapter ufficiali.
- TLD stripping ora usa `$domain->getTld(false)` invece di `trim()` manuale, sfruttando il parametro nativo di `Registrar_Domain`.

### Fixed

- **Errore fatale al caricamento**: rimosso ogni riferimento a `Registrar_Domain_Nameserver`, classe che non esiste in FOSSBilling. I nameserver sono e rimangono stringhe semplici in `Registrar_Domain`.
- In `getDomainDetails`, ripristinati i metodi corretti `setRegistrationTime(int)` e `setExpirationTime(int)` al posto di `setRegisteredAt(\DateTime)` e `setExpiresAt(\DateTime)`, che non esistono nell'API reale.
- In `modifyNs`, corretta la lettura dei nameserver: `getNs1()` restituisce una stringa, non un oggetto.

### Added

- **Unit test con Composer**: aggiunto `composer.json` con `phpunit/phpunit ^11` e `symfony/http-client ^7` come dipendenze di sviluppo.
- Suite di 44 test (`tests/OpenProviderTest.php`) che coprono tutti i metodi pubblici dell'adapter: happy path, failure, casi limite (token cached, customer non trovato, domain non trovato, TLD stripping).
- Stub FOSSBilling in `tests/Stubs/` che replicano fedelmente le classi `Registrar_AdapterAbstract`, `Registrar_Domain`, `Registrar_Domain_Contact`, `Registrar_Exception` e `Box_Log`.

### Removed

- **`library/Registrar/Adapter/OpenProvider/API.php`**: rimosso il wrapper cURL interno. Le chiamate HTTP sono ora gestite tramite il Symfony HttpClient iniettato dalla classe base (`$this->getHttpClient()`).

### Improved

- Il token Bearer viene acquisito una sola volta per istanza dell'adapter e messo in cache in `$this->accessToken`.
- Estratto il metodo privato `_hydrateContact()` per centralizzare la mappatura dei dati cliente in `Registrar_Domain_Contact`.
- Risolto il bug originale per cui l'header `Content-Type: application/json` nelle richieste non-GET veniva silenziosamente ignorato.
