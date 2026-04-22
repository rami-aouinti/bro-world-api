# Migration des routes API V1 vers un scope `applicationSlug`

## Convention officielle
Pour tous les modules métier ciblés (`crm`, `recruit`, `shop`, `school`, `calendar`, `chat`, `quiz`, `blog`), la convention unique est :

`/v1/{module}/...` avec `applicationSlug` transmis en query (`?applicationSlug=...`) (ou header dédié).

Règles associées :
- ne plus injecter `applicationSlug` dans le path ;
- transmettre `applicationSlug` en query (`?applicationSlug=...`) ;
- conserver les segments métier (`private`, `public`, etc.) sans préfixe `applications/{applicationSlug}`.

## Compatibilité legacy (stratégie)
Pour conserver la compatibilité avec les clients existants:

1. **Phase 1 (immédiate)**: publier les nouvelles routes et annoncer la dépréciation des anciennes routes.
2. **Phase 2 (2-4 semaines)**: ajouter au niveau gateway/reverse proxy des redirections 307 vers les nouveaux chemins quand un `applicationSlug` par défaut est connu.
3. **Phase 3**: journaliser les appels legacy (tag `legacy_route=true`) et contacter les clients restants.
4. **Phase 4**: supprimer les routes legacy après la fenêtre de migration.

## Décisions opérationnelles (validées)

1. **Anciennes routes conservées temporairement (pas de suppression immédiate).**
   - Statut: dépréciées.
   - Signalement: en-têtes HTTP `Deprecation`, `Sunset`, `Warning`.
   - Date de fin de support annoncée: **31 décembre 2026 à 23:59:59 GMT**.
2. **Suivi d'usage obligatoire des endpoints legacy.**
   - Logs structurés avec `event=shop.legacy_route.used` et `legacy_route=true`.
   - Compteur monitoring `shop.legacy_route.usage_total` avec labels (`module`, `route`, `method`).
3. **Communication client planifiée par lot de consommateurs.**
   - Front web: annonce sprint N, rappel sprint N+1, bascule avant freeze release.
   - Mobile: annonce au planning release mobile, suivi adoption par version.
   - Intégrations externes: email + changelog + relance ciblée selon logs d'usage.
4. **Suppression définitive après adoption.**
   - Pré-requis: 0 appel legacy observé pendant 14 jours glissants.
   - Action: suppression des routes legacy + retrait des couches de compatibilité + nettoyage documentation.

## Plan de communication client

### Cibles
- Front web
- Mobile (iOS/Android)
- Intégrations externes / partenaires API

### Cadence
- **T0 (annonce)**: publication note de migration + date de sunset.
- **T0 + 2 semaines**: rappel avec top endpoints legacy encore utilisés (basé logs/metrics).
- **T0 + 6 semaines**: notification de pré-coupure.
- **T0 + 8 semaines**: revue go/no-go sur la suppression (selon adoption mesurée).

### Canaux
- Changelog API
- Message Slack/Teams équipes internes
- Email aux intégrateurs externes
- Ticket de suivi par client consommateur

## Recommandation technique
Quand le slug n'est pas dérivable automatiquement, retourner `400` avec un message explicite pour forcer la migration client.

## Note de migration API – Shop Products

### Shop products (`/v1/shop/products`)
- Les routes legacy `GET /v1/shop/products` et `POST /v1/shop/products` sont **dépréciées**.
- Les consommateurs externes doivent migrer vers les routes canoniques :
  - `GET /v1/shop/products?applicationSlug={applicationSlug}`
  - `POST /v1/shop/products?applicationSlug={applicationSlug}`
  - `GET|PATCH|DELETE /v1/shop/products/{id}?applicationSlug={applicationSlug}`
- Pendant la fenêtre de migration, les routes legacy restent accessibles mais répondent avec les en-têtes HTTP:
  - `Deprecation: true`
  - `Sunset: Wed, 31 Dec 2026 23:59:59 GMT`
  - `Warning: 299 - "Deprecated endpoint: use /v1/shop/products?applicationSlug={applicationSlug} instead."`

Action attendue côté clients: renseigner explicitement `applicationSlug` en query string et retirer l'usage des anciennes URLs avec `/applications/{applicationSlug}`.


## Migration client (ancienne URL → nouvelle URL)

| Ancienne URL | Nouvelle URL |
|---|---|
| `/v1/recruit/applications/{applicationSlug}/jobs` | `/v1/recruit/jobs?applicationSlug={applicationSlug}` |
| `/v1/recruit/applications/{applicationSlug}/private/jobs` | `/v1/recruit/private/jobs?applicationSlug={applicationSlug}` |
| `/v1/recruit/public/{applicationSlug}/jobs` | `/v1/recruit/public/jobs?applicationSlug={applicationSlug}` |
| `/v1/recruit/private/{applicationSlug}/jobs` | `/v1/recruit/private/jobs?applicationSlug={applicationSlug}` |
| `/v1/recruit/applications/{applicationId}/jobs/{jobId}` | `/v1/recruit/jobs/{jobId}?applicationSlug={applicationSlug}` |
| `/v1/crm/applications/{applicationSlug}/companies` | `/v1/crm/companies?applicationSlug={applicationSlug}` |
| `/v1/crm/applications/{applicationSlug}/tasks` | `/v1/crm/tasks?applicationSlug={applicationSlug}` |

Exemple concret:

```bash
# Avant
GET /v1/recruit/applications/bro-world/jobs

# Après
GET /v1/recruit/jobs?applicationSlug=bro-world
```
