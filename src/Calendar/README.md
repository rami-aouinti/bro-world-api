# Calendar module guidelines

## Controllers

For controllers in `src/Calendar/Transport/Controller`:

- Do not use `private readonly` constructor property promotion.
- Use the team convention with `public readonly` promoted dependencies (or inject services without promoted properties when delegating to a dedicated service is more appropriate).
- Keep controllers thin and orchestration-only.
- Do not add `private` methods in controllers. Move reusable/complex logic to an application/domain service.


## Event endpoint scope matrix

### User-only (strictly personal, `/private/...`)

- `GET /v1/calendar/private/events`
- `POST /v1/calendar/private/events`
- `PATCH /v1/calendar/private/events/{eventId}`
- `DELETE /v1/calendar/private/events/{eventId}`
- `POST /v1/calendar/private/events/{eventId}/cancel`

These routes are reserved to personal resources only (events not linked to an application).

### Application-linked (must include `/{applicationSlug}/`)

- `GET /v1/calendar/applications/{applicationSlug}/events` (public events)
- `GET /v1/calendar/applications/{applicationSlug}/events/me` (authenticated user view)
- `POST /v1/calendar/applications/{applicationSlug}/events`
- `PATCH /v1/calendar/applications/{applicationSlug}/events/{eventId}`
- `DELETE /v1/calendar/applications/{applicationSlug}/events/{eventId}`
- `POST /v1/calendar/applications/{applicationSlug}/events/{eventId}/cancel`

## Non-régression

Checklist pré-merge (PR) :

- [ ] PATCH partiel `startAt` / `endAt` validé.
- [ ] Pagination + filtres texte vérifiés (avec Elastic et sans Elastic).
- [ ] Invalidation par tags de cache contrôlée (privé et public).
- [ ] Cohérence OpenAPI confirmée pour les endpoints de mutation.
- [ ] Cas `404` / `422` couverts pour routes `private` et `application`.
