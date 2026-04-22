# Swagger
This document describing how you can use [Swagger](https://swagger.io/).

## Using Swagger
* [Local Swagger service](http://localhost/api/doc) - Open next url http://localhost/api/doc in order to use Swagger.

## Convention interne OpenAPI (CRM)

Cette convention s'applique à tous les endpoints des agrégats CRM:

- `Contact`, `Company`, `Billing`, `Employee`
- `Project`, `Sprint`, `Task`, `TaskRequest`
- `Project/Github` (`repositories`, `issues`, `branches`, `pull-requests`, `webhook`)

### 1) Nomenclature `summary` (verbe + ressource)

- Format obligatoire: `Verbe Ressource`.
- Exemples valides:
  - `Create Contact`
  - `List Tasks`
  - `Replace Project`
  - `Create Task Request GitHub Branch`
- Interdits:
  - inclure le chemin HTTP (`POST /v1/...`)
  - formulations vagues (ex: `Project Github Pull Request Action`)
  - suffixes contextuels non nécessaires (`dans le CRM`)

### 2) Structure `responses`

- Déclarer les réponses **dans l'opération** (`OA\Get`, `OA\Post`, `OA\Put`, `OA\Patch`, `OA\Delete`) avec `responses: [ ... ]`.
- Éviter les `#[OA\Response(...)]` séparés pour un même endpoint.
- Même type d'endpoint => même structure:
  - `GET list`: `200` + erreurs standard
  - `GET detail`: `200` + erreurs standard
  - `POST create`: `201` + erreurs standard
  - `PUT replace`: `200` + erreurs standard
  - `PATCH update`: `200` + erreurs standard
  - `DELETE`: `204` + erreurs standard

### 3) Règles d'exemples

- Les exemples de payload request doivent utiliser des résumés cohérents:
  - `Exemple minimal valide`
  - `Exemple métier complet` (si pertinent)
- Les exemples d'erreur doivent rester explicites (`JSON invalide`, `Date invalide`, etc.).
- Préférer des exemples réalistes et stables (UUID, dates ISO 8601, slugs).

### 4) Règles de tags

- Endpoints CRM généraux: `Crm`
- Endpoints GitHub du projet CRM: `Crm Github`
- Endpoints TaskRequest (y compris GitHub branch de TaskRequest): `Crm TaskRequest`

## Contrôle de cohérence

Une commande QA dédiée vérifie la cohérence documentaire des endpoints CRM ciblés:

```bash
php bin/console qa:crm:swagger
```

La commande:

- liste les contrôleurs CRM contenant au moins une route `#[Route(...)]`
- vérifie qu'une opération OpenAPI (`OA\Get|Post|Put|Patch|Delete`) existe pour chaque route CRM
- vérifie que les endpoints avec body (`POST|PUT|PATCH`) contiennent au moins un exemple de request et de response
- échoue si un endpoint CRM est non documenté ou incomplet

Le rapport d'erreur affiche les éléments manquants avec le format:

- `fichier :: [METHODS] /route`

CI:

- le job `documentation/swagger` exécute ce contrôle automatiquement dans la pipeline.

## Application list endpoints (filters + pagination)

Les endpoints suivants sont documentés dans Swagger avec des query params:
- `GET /v1/application/public`
- `GET /v1/application/private`

### Query params disponibles
- `page` (int, default: `1`, min: `1`)
- `limit` (int, default: `20`, min: `1`, max: `100`)
- `title` (string, filtre partiel)
- `description` (string, filtre partiel)
- `platformName` (string, filtre partiel)
- `platformKey` (string, filtre exact)

### Exemples d'appels API

#### 1) Applications publiques filtrées par titre + platformKey
```bash
curl -X GET "http://localhost/api/v1/application/public?title=shop&platformKey=shop&page=1&limit=10" \
  -H "Accept: application/json"
```

#### 2) Applications publiques filtrées par description + nom de plateforme
```bash
curl -X GET "http://localhost/api/v1/application/public?description=growth&platformName=CRM&page=1&limit=5" \
  -H "Accept: application/json"
```

#### 3) Applications privées (auth requis), filtrées par platformKey
```bash
curl -X GET "http://localhost/api/v1/application/private?platformKey=crm&page=1&limit=20" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

#### 4) Applications privées filtrées par title
```bash
curl -X GET "http://localhost/api/v1/application/private?title=recruit&page=2&limit=1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

### Format de réponse
```json
{
  "items": [
    {
      "id": "...",
      "title": "Shop Ops App",
      "slug": "shop-ops-app",
      "description": "...",
      "photo": "...",
      "status": "active",
      "private": false,
      "platformId": "...",
      "platformName": "Shop",
      "platformKey": "shop",
      "pluginKeys": ["analytics"],
      "author": {
        "id": "...",
        "firstName": "John",
        "lastName": "Doe",
        "photo": "..."
      },
      "createdAt": "2026-03-06T09:00:00+00:00",
      "isOwner": false
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "totalItems": 1,
    "totalPages": 1
  },
  "filters": {
    "title": "shop",
    "platformKey": "shop"
  }
}
```

## Recruit endpoints (module Recruit)
- Voir la documentation détaillée: [docs/recruit.md](./recruit.md)


## School API (scope application)

### Règles de scope application
- Tous les endpoints School de ce bloc sont préfixés par `/v1/school/...` avec `applicationSlug` en query (`?applicationSlug=...`)`.
- `applicationSlug` détermine le **scope d'accès**: une ressource (`classes`, `students`, `teachers`, `exams`, `grades`) doit appartenir à l'école résolue pour cette application.
- Si la ressource n'appartient pas au scope de l'application, l'API retourne `404` (même si l'ID existe ailleurs).
- Les endpoints sont protégés: utilisateur non authentifié/non autorisé => `403`.
- En cas de payload invalide (création/patch) ou de paramètres invalides (pagination/filtres), l'API retourne `422`.

### Pagination et filtres (School)
- Pagination standard: `page` (min `1`, défaut `1`) et `limit` (min `1`, max `100`, défaut `20`).
- Filtres disponibles selon la ressource:
  - classes: `q` (recherche sur le nom)
  - exams: `q` (full-text) et `title` (filtre partiel)

### Exemples request/response School

#### List (classes)
```bash
curl -X GET "http://localhost/api/v1/school/classes?applicationSlug=school-crm&page=1&limit=20&q=term" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

```json
{
  "items": [{ "id": "7600e750-f92f-4f9f-883a-26404b538f66", "name": "Terminale S" }],
  "meta": {
    "pagination": { "page": 1, "limit": 20, "totalItems": 1, "totalPages": 1 },
    "filters": { "q": "term" }
  }
}
```

#### Detail (resource)
```bash
curl -X GET "http://localhost/api/v1/school/students/4cfada53-2cf2-49a7-a4fb-4a9682c3a0c0?applicationSlug=school-crm" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

```json
{ "id": "4cfada53-2cf2-49a7-a4fb-4a9682c3a0c0", "name": "Alice Martin" }
```

#### Create (student)
```bash
curl -X POST "http://localhost/api/v1/school/students?applicationSlug=school-crm" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{"name":"Alice Martin","classId":"7600e750-f92f-4f9f-883a-26404b538f66"}'
```

```json
{ "id": "4cfada53-2cf2-49a7-a4fb-4a9682c3a0c0" }
```

#### Patch (resource)
```bash
curl -X PATCH "http://localhost/api/v1/school/classes/7600e750-f92f-4f9f-883a-26404b538f66?applicationSlug=school-crm" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{"name":"Terminale S1"}'
```

```json
{ "id": "7600e750-f92f-4f9f-883a-26404b538f66", "name": "Terminale S1" }
```

#### Delete (class)
```bash
curl -X DELETE "http://localhost/api/v1/school/classes/7600e750-f92f-4f9f-883a-26404b538f66?applicationSlug=school-crm" \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

Réponse: `204 No Content`.


## Migration client (applicationSlug en query)

Exemple de bascule :
- Avant: `/v1/school/applications/school-crm/classes`
- Après: `/v1/school/classes?applicationSlug=school-crm`
