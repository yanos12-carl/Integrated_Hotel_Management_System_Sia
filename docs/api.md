# IHMS API

Base URL: `http://localhost/hotel-management-system/api/v1`. JSON in, JSON out. Session cookie `IHMS_SESSION` (HttpOnly, SameSite=Lax).
**Every POST/PUT/DELETE needs the header `X-CSRF-Token`** (get it from `GET /auth/csrf`, or from the login/me response).

Envelope: success `{"success":true,"message":"…","data":…,"meta":{"page":1,"per_page":15,"total":42,"pages":3}}`
Error `{"success":false,"message":"…","errors":{"field":"…"}}` · 401 not signed in · 403 no permission · 404 · 409 conflict · 419 bad CSRF · 422 validation · 423 locked · 429 throttled · 500 `{"error_id":"…"}` (look it up under Admin → System errors).

## Authentication
| Method & path | Notes |
|---|---|
| `GET /auth/csrf` | `{"data":{"csrf_token":"…"}}` |
| `POST /auth/login` | body `{"username","password"}` → user, roles, permissions. Locks the account after 5 failures (setting). |
| `POST /auth/logout` | |
| `GET /auth/me` | current user + permissions |
| `POST /auth/change-password` | `{"current_password","new_password"}` (min 10 chars, letters + numbers) |
| `POST /auth/forgot-password` | `{"email"}`, always the same reply. Local mode writes the link to `storage/private/mail-outbox.log`. |
| `POST /auth/reset-password` | `{"token","new_password"}`; single use, 30 minutes |

Sample:
```
POST /api/v1/auth/login   X-CSRF-Token: <token>
{"username":"sysadmin","password":"********"}
→ 200 {"success":true,"message":"Signed in.","data":{"user":{"id":1,"username":"sysadmin","department":{"code":"ADMIN"},"permissions":["user.manage",…],"must_change_password":0},"csrf_token":"…"}}
```

## Notifications (any signed-in user)
`GET /notifications?unread=1&page=1` · `GET /notifications/unread-count` · `POST /notifications/{id}/read` · `POST /notifications/read-all`

## Approvals
`GET /approvals?status=pending` (`approval.view`) · `POST /approvals/{id}/decide` `{"decision":"approved|rejected","note":""}` (`approval.decide`; you cannot decide your own request)

## Security
Security workflows are part of the IHMS JSON API and use the same authenticated session and central database as other departments. Reads require the relevant view/manage permission; every write requires a CSRF token and the appropriate permission.

| Endpoint | Permission |
|---|---|
| `GET /security/summary`, `GET /security/users`, `GET /security/incidents`, `GET /security/incidents/{id}` | security.incident.view |
| `POST /security/incidents`, `POST /security/incidents/{id}/updates`, `/status`, `/assign` | security.incident.manage |
| `GET /security/cctv-references` | security.evidence.view |
| `POST /security/cctv-references` | security.incident.manage |
| `GET /security/cameras` | security.evidence.view |
| `POST /security/cameras` `{name,location,browser_device_key}` | security.camera.manage |
| `DELETE /security/cameras/{id}` | security.camera.manage |
| `GET /security/visitors`, `POST /security/visitors`, `POST /security/visitors/{id}/checkout` | visitor.manage |
| `GET /security/shifts`, `POST /security/shifts`, `POST /security/shifts/{id}/status` | patrol.manage |
| `GET /security/handovers`, `POST /security/handovers`, `POST /security/handovers/{id}/acknowledge` | patrol.manage |
| `GET`/`POST /security/patrol-schedules`, `/checkpoints`, `/patrol-records`, `/inspections` | patrol.manage |

Security browser pages are served by IHMS at `/security` and `/security/{page}`. The historical compatibility tables from migration `013_security_app_central.sql` are retained; the native interface uses the canonical Security workflow tables defined in migration `008_security.sql`.

The Security CCTV page can enumerate and preview browser-visible cameras after the user grants camera permission. Scanning starts all saved camera feeds available to the current browser in a compact camera wall; unavailable and external cameras remain listed with their connection status. Feeds stay local and stop when the user stops them or leaves the page. Camera registration stores a name/location and a SHA-256 hash of the browser device ID; video is not recorded or uploaded. Browsers cannot discover remote phones or scan a hotel's network for cameras; phones must be connected as a camera device visible to that browser. Browser camera access requires HTTPS or localhost.

## Admin (all under `/admin`, permission in brackets)
| Endpoint | Permission |
|---|---|
| `GET /summary` | admin.dashboard |
| `GET /analytics?days=7\|14\|30\|90` (default 14, anything else is clamped) → occupancy, revenue, front office, operations, workforce and audit blocks | admin.dashboard |
| `GET /users?q=&department_id=&status=&sort=&dir=&page=` · `GET /users/{id}` | user.view |
| `POST /users` `{username,email,first_name,last_name,phone,department_id,role_ids[]}` → `{user, temporary_password}` (shown once) | user.manage |
| `PUT /users/{id}` · `POST /users/{id}/status` `{"action":"activate|deactivate|unlock"}` · `POST /users/{id}/reset-password` · `PUT /users/{id}/roles` `{"role_ids":[…]}` | user.manage |
| `GET /departments` · `PUT /departments/{id}` `{name,description,is_active}` | department.view / .manage |
| `GET /roles` · `GET /permissions` | role.view |
| `POST /roles` · `PUT /roles/{id}` · `DELETE /roles/{id}` · `PUT /roles/{id}/permissions` `{"permission_ids":[…]}` | role.manage |
| `GET /settings` · `PUT /settings` `{"settings":{"billing.vat_rate":"0.12"}}` | settings.view / .manage |
| `GET /audit-logs?q=&from=&to=` | audit.view |
| `GET /login-attempts?q=&success=` | login_activity.view |
| `GET /error-logs?q=` | error_log.view |
| `GET /sessions` · `DELETE /sessions/{handle}` (handle = SHA-256 of the session id; ids are never exposed) | session.manage |
| `GET /notifications` · `POST /notifications/broadcast` `{title,message,severity,department_id?}` | notification.manage |
| `GET /backups` · `POST /backups` · `GET /backups/{name}/download` | backup.manage |

Business rules enforced server-side: at least one active System Administrator must remain; you cannot deactivate yourself; department-scoped roles only go to that department's employees; exactly nine departments (edit/deactivate only); built-in roles cannot be deleted; the Admin role keeps user/role/settings management; deactivating or resetting a user ends all their sessions.

## Analytics
`GET /admin/analytics?days=N` is read-only and computes every figure live from the central database — it never writes and never caches. The response is one object with these blocks:

| Block | Contents |
|---|---|
| `range` | `days`, `from`, `to` |
| `kpi` | occupancy rate, rooms occupied/available/servicing, in-house stays and guests, arrivals and departures today, reservations today and in the next 7 days, revenue today and in the period, invoiced total, outstanding folio balance, open folios, employee totals, active sessions, sign-in successes/failures, errors, pending approvals, open work orders, open incidents, pending housekeeping, open orders |
| `occupancy` | rooms by status, rooms by type (total, occupied, rate) |
| `revenue` | daily series, daily counts, totals by method, total, count, average |
| `front_office` | booking channels, reservation statuses, daily arrivals and departures, nights booked, reservations on file |
| `operations` | housekeeping, maintenance, security (with severity split) and kitchen queues |
| `workforce` | employees by department, roles in use, daily successful/failed sign-ins, failure reasons |
| `audit` | top audited actions, daily audit entries, daily system errors |

The same figures drive the Admin dashboard and the **Admin → Analytics** page.
