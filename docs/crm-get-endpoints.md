# CRM GET endpoints inventory (V1)

## List endpoints
- `GET /v1/crm/applications/{applicationSlug}/billings` — filters: `q`, `status`, `companyId`; pagination: `page`, `limit`.
- `GET /v1/crm/applications/{applicationSlug}/contacts` — filters: `q`; pagination: `page`, `limit`.
- `GET /v1/crm/applications/{applicationSlug}/companies` — filters: `q`; pagination: `page`, `limit`.
- `GET /v1/crm/applications/{applicationSlug}/projects` — filters: `q`, `status`; pagination: `page`, `limit`.
- `GET /v1/crm/applications/{applicationSlug}/sprints` — filters: `q`, `status`; pagination: `page`, `limit`.
- `GET /v1/crm/applications/{applicationSlug}/tasks` — filters: `q`, `title`, `status`, `priority`; pagination: `page`, `limit`.
- `GET /v1/crm/applications/{applicationSlug}/task-requests` — filters: `q`, `status`; pagination: `page`, `limit`.
- `GET /v1/crm/applications/{applicationSlug}/tasks/by-sprint/{sprint}` — business filters: board/sprint scope.
- `GET /v1/crm/applications/{applicationSlug}/sprints/{sprint}/tasks` — business filters: sprint scope.
- `GET /v1/crm/applications/{applicationSlug}/me/tasks` — business filters: current user tasks.

## Detail endpoints
- `GET /v1/crm/applications/{applicationSlug}/billings/{billing}`
- `GET /v1/crm/applications/{applicationSlug}/contacts/{id}`
- `GET /v1/crm/applications/{applicationSlug}/companies/{id}`
- `GET /v1/crm/applications/{applicationSlug}/projects/{project}`
- `GET /v1/crm/applications/{applicationSlug}/sprints/{sprint}`
- `GET /v1/crm/applications/{applicationSlug}/tasks/{task}`
- `GET /v1/crm/applications/{applicationSlug}/task-requests/{taskRequest}`

## Other GET CRM endpoints (out of current refactor scope)
- `GET /v1/crm/applications/{applicationSlug}/reports`
- `GET /v1/crm/applications/{applicationSlug}/dashboard`
