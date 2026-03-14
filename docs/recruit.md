# Recruit API – endpoints, attributs et exemples d'implémentation

Ce document centralise **toutes les routes HTTP exposées par le module `Recruit`** avec :
- les méthodes,
- les attributs attendus (path/query/body),
- des exemples d'appels `curl`.

> Base URL locale: `http://localhost/api`

## Authentification
- Endpoints publics: 
  - `GET /v1/recruit/public/{applicationSlug}/jobs`
- Endpoints privés: tous les autres endpoints de ce document (JWT requis).

Exemple d'en-tête JWT:
```bash
-H "Authorization: Bearer <JWT_TOKEN>"
```

---

## 1) Endpoints CRUD (controllers REST génériques)

Ces endpoints utilisent les actions REST génériques (`find`, `findOne`, `count`, `ids`, `create`, `patch`, `update`, `delete`) avec restriction `ROLE_ROOT` sur ces actions.

### 1.1 Badge
Base path: `/v1/recruit/badge`

- `GET /v1/recruit/badge`
- `GET /v1/recruit/badge/count`
- `GET /v1/recruit/badge/ids`
- `GET /v1/recruit/badge/{id}`
- `POST /v1/recruit/badge`
- `PATCH /v1/recruit/badge/{id}`
- `PUT /v1/recruit/badge/{id}`
- `DELETE /v1/recruit/badge/{id}`

Attributs métier du body (`POST`, `PATCH`, `PUT`):
- `label` (string)

Exemple:
```bash
curl -X POST "http://localhost/api/v1/recruit/badge" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{"label":"Remote Friendly"}'
```

### 1.2 Tag
Base path: `/v1/recruit/tag`

- `GET /v1/recruit/tag`
- `GET /v1/recruit/tag/count`
- `GET /v1/recruit/tag/ids`
- `GET /v1/recruit/tag/{id}`
- `POST /v1/recruit/tag`
- `PATCH /v1/recruit/tag/{id}`
- `PUT /v1/recruit/tag/{id}`
- `DELETE /v1/recruit/tag/{id}`

Attributs métier du body:
- `label` (string)

### 1.3 Salary
Base path: `/v1/recruit/salary`

- `GET /v1/recruit/salary`
- `GET /v1/recruit/salary/count`
- `GET /v1/recruit/salary/ids`
- `GET /v1/recruit/salary/{id}`
- `POST /v1/recruit/salary`
- `PATCH /v1/recruit/salary/{id}`
- `PUT /v1/recruit/salary/{id}`
- `DELETE /v1/recruit/salary/{id}`

Attributs métier du body:
- `min` (int)
- `max` (int)
- `currency` (string, ex: `EUR`)
- `period` (string, ex: `year`)

Exemple:
```bash
curl -X POST "http://localhost/api/v1/recruit/salary" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{"min":40000,"max":55000,"currency":"EUR","period":"year"}'
```

### 1.4 Company
Base path: `/v1/recruit/company`

- `GET /v1/recruit/company`
- `GET /v1/recruit/company/count`
- `GET /v1/recruit/company/ids`
- `GET /v1/recruit/company/{id}`
- `POST /v1/recruit/company`
- `PATCH /v1/recruit/company/{id}`
- `PUT /v1/recruit/company/{id}`
- `DELETE /v1/recruit/company/{id}`

Attributs métier du body:
- `name` (string)
- `logo` (string)
- `sector` (string)
- `size` (string)

### 1.5 Job
Base path: `/v1/recruit/job`

- `GET /v1/recruit/job`
- `GET /v1/recruit/job/count`
- `GET /v1/recruit/job/ids`
- `GET /v1/recruit/job/{id}`
- `POST /v1/recruit/job`
- `PATCH /v1/recruit/job/{id}`
- `PUT /v1/recruit/job/{id}`
- `DELETE /v1/recruit/job/{id}`

Attributs métier du body:
- `recruit` (UUID de Recruit)
- `title` (string)
- `location` (string)
- `contractType` (`CDI`, `CDD`, `Freelance`, `Internship`)
- `workMode` (`Onsite`, `Remote`, `Hybrid`)
- `schedule` (`Vollzeit`, `Teilzeit`, `Contract`)
- `experienceLevel` (`Junior`, `Mid`, `Senior`, `Lead`)
- `yearsExperienceMin` (int)
- `yearsExperienceMax` (int)
- `isPublished` (bool)
- `summary` (string)
- `matchScore` (int)
- `missionTitle` (string)
- `missionDescription` (string)
- `responsibilities` (array)
- `profile` (array)
- `benefits` (array)

---

## 2) Endpoints Recruit spécifiques (implémentations dédiées)

### 2.1 Liste publique des jobs
`GET /v1/recruit/public/{applicationSlug}/jobs`

Path params:
- `applicationSlug` (string)

Query params disponibles:
- `page` (int, défaut `1`)
- `limit` (int, défaut `20`, min `1`, max `100`)
- `company` (string)
- `salaryMin` (int)
- `salaryMax` (int)
- `contractType` (`CDI`, `CDD`, `Freelance`, `Internship`)
- `workMode` (`Onsite`, `Remote`, `Hybrid`)
- `schedule` (`Vollzeit`, `Teilzeit`, `Contract`)
- `experienceLevel` (`Junior`, `Mid`, `Senior`, `Lead`)
- `yearsExperienceMin` (int)
- `yearsExperienceMax` (int)
- `isPublished` (bool)
- `postedAtLabel` (string)
  - Formats supportés: `today`, `3d`, `7d`, `30d`
  - Rétrocompatibilité acceptée: `vor kurzem`, `vor <N> Tage`, `vor <N> Wochen`, `vor <N> Monate`, `vor <N> Jahre`
- `location` (string)
- `q` (string, recherche full-text)

Exemple:
```bash
curl -X GET "http://localhost/api/v1/recruit/public/my-app/jobs?company=Acme&workMode=Remote&page=1&limit=10" \
  -H "Accept: application/json"
```

### 2.2 Détail public d'un job (+ jobs similaires)
`GET /v1/recruit/public/{applicationSlug}/jobs/{jobSlug}`

Retourne:
- `job`: détail d'une offre
- `similarJobs`: liste des jobs similaires (indexés via Elasticsearch)

Exemple:
```bash
curl -X GET "http://localhost/api/v1/recruit/public/my-app/jobs/backend-engineer-m-w-d-symfony-api-platform" \
  -H "Accept: application/json"
```

### 2.2 Liste privée des jobs d'une application
`GET /v1/recruit/private/{applicationSlug}/jobs`

Même format que la liste publique, mais authentifiée.

Exemple:
```bash
curl -X GET "http://localhost/api/v1/recruit/private/my-app/jobs?q=php" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

### 2.3 Mes jobs (créés + postulés)
`GET /v1/recruit/private/me/jobs`

Retourne:
- `createdJobs`: offres créées par le user connecté
- `appliedJobs`: offres auxquelles le user a postulé

Exemple:
```bash
curl -X GET "http://localhost/api/v1/recruit/private/me/jobs" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

### 2.4 Créer un job via `applicationSlug`
`POST /v1/recruit/applications/{applicationSlug}/jobs`

Path params:
- `applicationSlug` (string)

Body requis:
- `title` (string non vide)

Body optionnel:
- `location` (string)
- `summary` (string)
- `missionTitle` (string)
- `missionDescription` (string)
- `matchScore` (int)
- `contractType` (`CDI`, `CDD`, `Freelance`, `Internship`)
- `workMode` (`Onsite`, `Remote`, `Hybrid`)
- `schedule` (`Vollzeit`, `Teilzeit`, `Contract`)
- `experienceLevel` (`Junior`, `Mid`, `Senior`, `Lead`)
- `yearsExperienceMin` (int)
- `yearsExperienceMax` (int)
- `isPublished` (bool)
- `responsibilities` (array)
- `profile` (array)
- `benefits` (array)

Exemple:
```bash
curl -X POST "http://localhost/api/v1/recruit/applications/my-app/jobs" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{
    "title":"Backend Developer",
    "location":"Paris",
    "contractType":"CDI",
    "workMode":"Hybrid",
    "schedule":"Vollzeit",
    "responsibilities":["Build APIs","Write tests"],
    "profile":["PHP 8.3","Symfony"],
    "benefits":["Mutuelle","Télétravail"]
  }'
```

### 2.5 Mettre à jour partiellement un job d'une application
`PATCH /v1/recruit/applications/{applicationId}/jobs/{jobId}`

Path params:
- `applicationId` (UUID)
- `jobId` (UUID)

Body patchable:
- `title`, `location`, `summary`, `missionTitle`, `missionDescription`
- `matchScore`
- `contractType`, `workMode`, `schedule`
- `responsibilities`, `profile`, `benefits`

Exemple:
```bash
curl -X PATCH "http://localhost/api/v1/recruit/applications/<applicationId>/jobs/<jobId>" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{"title":"Senior Backend Developer","matchScore":88}'
```

### 2.6 Supprimer un job d'une application
`DELETE /v1/recruit/applications/{applicationId}/jobs/{jobId}`

Path params:
- `applicationId` (UUID)
- `jobId` (UUID)

Exemple:
```bash
curl -X DELETE "http://localhost/api/v1/recruit/applications/<applicationId>/jobs/<jobId>" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

### 2.7 Créer un CV
`POST /v1/recruit/resumes`

Chaque section est un tableau d'objets `{ "title": string, "description": string }`:
- `experiences`
- `educations`
- `skills`
- `languages`
- `certifications`
- `projects`
- `references`
- `hobbies`

Exemple:
```bash
curl -X POST "http://localhost/api/v1/recruit/resumes" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{
    "experiences":[{"title":"Backend Dev","description":"API Symfony"}],
    "educations":[{"title":"Master Info","description":"Université X"}],
    "skills":[{"title":"PHP","description":"8+ ans"}]
  }'
```

### 2.8 Créer un candidat
`POST /v1/recruit/applicants`

Body:
- `resumeId` (UUID, requis, doit appartenir à l'utilisateur connecté)
- `coverLetter` (string, optionnel)

Exemple:
```bash
curl -X POST "http://localhost/api/v1/recruit/applicants" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{"resumeId":"4a47b89f-465e-4e2f-bf92-f64e1db99f16","coverLetter":"Je suis motivé."}'
```

### 2.9 Créer une candidature
`POST /v1/recruit/applications`

Body:
- `applicantId` (UUID, requis, doit appartenir à l'utilisateur connecté)
- `jobId` (UUID, requis)
- `status` (optionnel, si fourni doit être `WAITING`)

Exemple:
```bash
curl -X POST "http://localhost/api/v1/recruit/applications" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{"applicantId":"<applicant_uuid>","jobId":"<job_uuid>","status":"WAITING"}'
```

### 2.10 Liste des candidatures d'un job (propriétaire du job)
`GET /v1/recruit/private/job-applications`

Query params:
- `jobId` (UUID) **ou**
- `jobSlug` (string)

Exemple:
```bash
curl -X GET "http://localhost/api/v1/recruit/private/job-applications?jobSlug=backend-developer" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>"
```

### 2.11 Changer le statut d'une candidature
`PATCH|PUT /v1/recruit/private/applications/{applicationId}/status`

Path params:
- `applicationId` (UUID)

Body:
- `status` (string): `WAITING`, `REVIEWING`, `INTERVIEW`, `ACCEPTED`, `REJECTED`

Transitions autorisées:
- `WAITING` -> `REVIEWING`, `REJECTED`
- `REVIEWING` -> `INTERVIEW`, `ACCEPTED`, `REJECTED`
- `INTERVIEW` -> `ACCEPTED`, `REJECTED`
- `ACCEPTED` / `REJECTED` -> pas de transition

Exemple:
```bash
curl -X PATCH "http://localhost/api/v1/recruit/private/applications/<applicationId>/status" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <JWT_TOKEN>" \
  -d '{"status":"REVIEWING"}'
```

### 2.5 Statistiques privées des offres
`GET /v1/recruit/private/{applicationSlug}/jobs/stats`

Retourne:
- `total`
- `published`
- `draft`
- `byContractType`
- `byWorkMode`
- `byExperienceLevel`

---

## 6) Matrice des permissions métier (RBAC recrutement)

Rôles métier introduits:
- `ROLE_RECRUITER`
- `ROLE_HIRING_MANAGER`
- `ROLE_INTERVIEWER`

Permissions fonctionnelles:
- `RECRUIT_INTERVIEW_MANAGE`
- `RECRUIT_INTERVIEW_VIEW`
- `RECRUIT_APPLICATION_STATUS_TRANSITION`
- `RECRUIT_APPLICATION_STATUS_HISTORY_VIEW`
- `RECRUIT_OFFER_MANAGE`
- `RECRUIT_SENSITIVE_DATA_VIEW`

### Matrice d'accès

| Endpoint clé | Permission requise | RECRUITER | HIRING_MANAGER | INTERVIEWER |
|---|---|---:|---:|---:|
| `POST/PATCH/DELETE /v1/recruit/private/interviews/...` | `RECRUIT_INTERVIEW_MANAGE` | ✅ | ✅ | ❌ |
| `GET /v1/recruit/private/applications/{id}/interviews` | `RECRUIT_INTERVIEW_VIEW` | ✅ | ✅ | ✅ |
| `PATCH /v1/recruit/applications/{slug}/private/applications/{id}/status` | `RECRUIT_APPLICATION_STATUS_TRANSITION` | ✅ | ✅ | ❌ |
| `GET /v1/recruit/applications/{slug}/private/applications/{id}/status-history` | `RECRUIT_APPLICATION_STATUS_HISTORY_VIEW` | ✅ | ✅ | ✅ |
| `POST/PATCH/DELETE /v1/recruit/applications/{slug}/jobs/...` (offers) | `RECRUIT_OFFER_MANAGE` | ✅ | ✅ | ❌ |
| Lecture de données sensibles (`CV`, `notes`, `feedback/comment`) | `RECRUIT_SENSITIVE_DATA_VIEW` | ✅ | ✅ | ❌ |

### Règles de confidentialité appliquées

- Les champs sensibles sont masqués (`null`) lorsque l'utilisateur n'a pas `RECRUIT_SENSITIVE_DATA_VIEW`.
- Sont considérés sensibles dans le module Recruit:
  - le CV (référence de resume),
  - les notes internes d'entretien,
  - les commentaires d'historique de statut (feedback interne),
  - les données personnelles non nécessaires (`email`, `coverLetter`).

> `ROLE_ADMIN` et `ROLE_ROOT` gardent un accès complet (override) via le voter Recruit.
