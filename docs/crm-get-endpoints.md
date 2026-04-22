# CRM GET endpoints inventory (V1)

> `applicationSlug` est maintenant attendu en query sur tous les endpoints ci-dessous : `?applicationSlug={applicationSlug}`.

## List endpoints
- `GET /v1/crm/billings` — filters: `q`, `status`, `companyId`; pagination: `page`, `limit`.
- `GET /v1/crm/contacts` — filters: `q`; pagination: `page`, `limit`.
- `GET /v1/crm/companies` — filters: `q`; pagination: `page`, `limit`.
- `GET /v1/crm/projects` — filters: `q`, `status`; pagination: `page`, `limit`.
- `GET /v1/crm/sprints` — filters: `q`, `status`; pagination: `page`, `limit`.
- `GET /v1/crm/tasks` — filters: `q`, `title`, `status`, `priority`; pagination: `page`, `limit`.
- `GET /v1/crm/task-requests` — filters: `q`, `status`; pagination: `page`, `limit`.
- `GET /v1/crm/tasks/by-sprint/{sprint}` — business filters: board/sprint scope.
- `GET /v1/crm/sprints/{sprint}/tasks` — business filters: sprint scope.
- `GET /v1/crm/me/tasks` — business filters: current user tasks.

## Detail endpoints
- `GET /v1/crm/billings/{billing}`
- `GET /v1/crm/contacts/{id}`
- `GET /v1/crm/companies/{id}`
- `GET /v1/crm/projects/{project}`
- `GET /v1/crm/sprints/{sprint}`
- `GET /v1/crm/tasks/{task}`
- `GET /v1/crm/task-requests/{taskRequest}`

## Other GET CRM endpoints (out of current refactor scope)
- `GET /v1/crm/reports`
- `GET /v1/crm/dashboard`


## Migration (ancien endpoint -> nouveau endpoint)

| Ancien endpoint | Nouveau endpoint |
|---|---|
| `GET /v1/crm/applications/{applicationSlug}/billings` | `GET /v1/crm/billings?applicationSlug={applicationSlug}` |
| `GET /v1/crm/applications/{applicationSlug}/contacts` | `GET /v1/crm/contacts?applicationSlug={applicationSlug}` |
| `GET /v1/crm/applications/{applicationSlug}/companies` | `GET /v1/crm/companies?applicationSlug={applicationSlug}` |
| `GET /v1/crm/applications/{applicationSlug}/projects` | `GET /v1/crm/projects?applicationSlug={applicationSlug}` |
| `GET /v1/crm/applications/{applicationSlug}/tasks` | `GET /v1/crm/tasks?applicationSlug={applicationSlug}` |
