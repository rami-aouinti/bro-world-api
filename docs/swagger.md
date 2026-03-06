# Swagger
This document describing how you can use [Swagger](https://swagger.io/).

## Using Swagger
* [Local Swagger service](http://localhost/api/doc) - Open next url http://localhost/api/doc in order to use Swagger.

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
