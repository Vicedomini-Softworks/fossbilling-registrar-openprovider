# Changelog

## [Unreleased] - 2026-04-07

### Changed

- **Compatibilità con FOSSBilling 0.7.x**: aggiornato l'adapter per conformarsi all'interfaccia attuale di `Registrar_AdapterAbstract`.
- Aggiunti i return type hint PHP a tutti i metodi astratti implementati (`bool`, `Registrar_Domain`, `string`).
- Rinominato `isDomainCanBeTransferred` in `isDomaincanBeTransferred` per allinearsi alla firma definita dalla classe base.
- In `getDomainDetails`, sostituiti i metodi deprecati `setRegistrationTime()` e `setExpirationTime()` con `setRegisteredAt(\DateTime)` e `setExpiresAt(\DateTime)`.
- I nameserver ora usano oggetti `Registrar_Domain_Nameserver` (con `setHost()` / `getHost()`) al posto di stringhe semplici, come richiesto dal nuovo modello.

### Removed

- **`library/Registrar/Adapter/OpenProvider/API.php`**: rimosso il wrapper cURL interno. Le chiamate HTTP sono ora gestite tramite il Symfony HttpClient iniettato dalla classe base (`$this->getHttpClient()`), in linea con le convenzioni dei moderni adapter FOSSBilling.

### Fixed

- Risolto il bug per cui l'header `Content-Type: application/json` nelle richieste non-GET veniva silenziosamente ignorato (il risultato di `array_merge` non veniva riassegnato).
- La risposta dell'API in caso di errore ora usa `$response['desc']` per il messaggio, più accurato rispetto al campo precedentemente usato.

### Improved

- Il token Bearer viene ora acquisito una sola volta per istanza dell'adapter e messo in cache in `$this->accessToken`, evitando una chiamata di autenticazione ridondante per ogni operazione.
- Il logging usa `$this->getLog()->debug()` fornito dalla classe base, eliminando la dipendenza da `file_put_contents` e dalla directory `logs/`.
- Estratto il metodo privato `_hydrateContact()` per centralizzare la mappatura dei dati cliente in `Registrar_Domain_Contact`.
