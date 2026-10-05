# API

Basis: `https://domain.tld/api/...` (mit .htaccess) oder `?_page=api&p=...`.
Antworten: `{"ok": true, ...}` oder `{"ok": false, "error": "..."}`.

## Index

`GET /api` (ohne Auth) liefert Endpunkte, Auth-Nutzung und Gruppen.
`GET /api?page=<gruppe|kategorie>` (z.B. `health`, `heart`) liefert die
Struktur inkl. Felder – ohne Nutzerdaten.

## Auth

```
POST /api/auth/login
{"username": "flo", "password": "...", "device_name": "script"}
-> {"ok": true, "token": "liarc_<id>_<secret>", "device_id": "<id>"}
```

Danach pro Request:

```
Authorization: Bearer liarc_<id>_<secret>
X-LIARC-User: flo
```

`POST /api/auth/device` (`{"username","token"}`) stellt eine Browser-Session her (Webapp).

## Endpunkte

```
GET    /api/me
GET    /api/export                              alle eintraege als JSON
GET    /api/devices
DELETE /api/devices/{id}

GET    /api/groups                              Gruppen mit Kategorie-Keys
GET    /api/categories                          inkl. Statistik (Keys fest: contacts, phones,
                                                heart, weight, height, steps, sleep, temp,
                                                medical, passwords, certs, serials, documents, notes)
GET    /api/categories/{key}
GET    /api/categories/{key}/stats              bei series inkl. points
GET    /api/categories/{key}/entries
POST   /api/categories/{key}/entries            series {"value","at?","note?"} / records {"fields":{k:v}}
PATCH  /api/categories/{key}/entries/{eid}      {"status"} / {"note"} / {"me":true}
                                                / series {"value","at"} / records {"fields":{k:v}} (teilweise)
DELETE /api/categories/{key}/entries/{eid}      archiviert nur (status old)
```

Feldtypen: text, number, date (YYYY-MM-DD), phone, note, secret.
`at`: Unix-Timestamp oder strtotime-String.

## Beispiel

```
curl -s "https://domain.tld/?_page=api&p=categories" \
  -H "Authorization: Bearer $TOKEN" -H "X-LIARC-User: flo"
```
