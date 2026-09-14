# PowerDNS Authoritative (canonical DNS)

**Files:** `providers/PowerDns/PowerDnsProvider.php` · **Contract test:** `tests/Contract/PowerDnsContractTest.php`

HTTP API `/api/v1/servers/localhost` with `X-API-Key` from `env://POWERDNS_<KEY>`; options `server_id`,
`nameservers` (NS set published in new zones), `soa_edit_api` (default `DEFAULT`), `dnssec_algorithm`.

| Contract | Method | Endpoint |
| --- | --- | --- |
| ZoneDnsProvider | `ensureZone` | `POST /zones` (kind Native/Master, nameservers, SOA-EDIT-API) — existing → `alreadyExisted` |
| | `applyChanges(zone, rrsets)` | `PATCH /zones/{zone}` with RRset `changetype: REPLACE|DELETE` (records grouped by name+type, TTL per RRset) |
| | `notify` | `PUT /zones/{zone}/notify` after each commit |
| | `export` | `GET /zones/{zone}/export` (BIND format for the Data Act export) |
| | `enableDnssec` / `disableDnssec` | `POST /zones/{zone}/cryptokeys` (active KSK+ZSK or CSK), `GET …/cryptokeys` → DS records for the registrar; `DELETE` on disable |
| | `deleteZone` | `DELETE /zones/{zone}` |

The control plane owns records (`dns_records`, `dns_zone_versions`); a commit builds the full RRset diff,
applies it atomically in one PATCH and stores the new serial. Rollback re-applies the previous version's
RRsets. System records (web A/AAAA, mail MX/SPF/DKIM/DMARC) are synchronised from services via
`DnsService::syncSystemRecords` and marked `managed_by: system`.

Errors: 422 (invalid RRset) → `VALIDATION` with the offending record; 409 → `CONFLICT`; 401 → `AUTH`;
connection errors → `TRANSIENT`. Validation runs locally first (`RecordValidator`) so the provider rarely sees
malformed data.
