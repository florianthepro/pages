# API

Basis: `https://domain.tld/api/...` (mit .htaccess) oder `?_page=api&p=...`.
Antworten: `{"ok": true, ...}` oder `{"ok": false, "error": "..."}`.

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
GET    /api/devices
DELETE /api/devices/{id}

GET    /api/categories                          inkl. Statistik
POST   /api/categories                          {"name","kind":"series|records","unit","fields":[{"label","type"}]}
GET    /api/categories/{id}
DELETE /api/categories/{id}                     nur eigene Kategorien (Defaults: 403)
GET    /api/categories/{id}/stats               bei series inkl. points
GET    /api/categories/{id}/entries
POST   /api/categories/{id}/entries             series {"value","at?","note?"} / records {"fields":{k:v}}
PATCH  /api/categories/{id}/entries/{eid}       {"status":"active|old"} / {"note"}
DELETE /api/categories/{id}/entries/{eid}
```

Feldtypen: text, number, date (YYYY-MM-DD), phone, note.
`at`: Unix-Timestamp oder strtotime-String.

## Beispiel

```
curl -s "https://domain.tld/?_page=api&p=categories" \
  -H "Authorization: Bearer $TOKEN" -H "X-LIARC-User: flo"
```
