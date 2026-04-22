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

Exemple concret:

```bash
# Avant
GET /v1/recruit/applications/bro-world/jobs

# Après
GET /v1/recruit/jobs?applicationSlug=bro-world
```
