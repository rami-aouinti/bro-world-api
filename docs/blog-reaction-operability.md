# Observabilité et engagements opérationnels — module `blog_reaction`

## Périmètre
Ce document couvre le module de réactions blog (lecture publique + handlers d’écriture) sur **staging** et **production**.

## 1) Métriques à instrumenter

### 1.1 Latence des endpoints read
Mesurer la latence HTTP des endpoints de lecture avec histogramme par route:

- `blog_reaction_read_latency_ms{env,route,method,status}`

Agrégations utilisées:

- `p50`, `p95`, `p99` (fenêtre glissante 5 min et 1 h)
- débit (requests/min)

Routes minimales:

- endpoint de listing des réactions d’un post
- endpoint de listing des réactions d’un commentaire

### 1.2 Taux d’erreurs des handlers de réaction
Mesurer les erreurs applicatives côté écriture (create/update/delete, si applicable):

- `blog_reaction_handler_requests_total{env,handler,outcome}`
  - `outcome=success|error`
- `blog_reaction_handler_errors_total{env,handler,error_type}`

KPI principal:

- `error_rate = errors / requests` par handler et global module.

### 1.3 Fréquence des conflits d’unicité SQL
Suivre explicitement les violations des index uniques SQL:

- `blog_reaction_sql_conflicts_total{env,constraint}`
  - `constraint=uniq_blog_reaction_author_comment|uniq_blog_reaction_author_post`

Source de collecte possible:

- compteur incrémenté dans la couche de gestion d’exception Doctrine DBAL (code SQLSTATE `23000` / duplicate key)
- fallback via parsing des logs structurés SQL/applicatifs.

## 2) Dashboard staging + prod et alerting

## 2.1 Dashboard
Créer un dashboard `Blog Reaction - Reliability` décliné sur:

- `env=staging`
- `env=prod`

Panneaux recommandés:

1. **Latence read p50/p95/p99** par route.
2. **Taux d’erreurs handlers** (stacked success/error + ratio).
3. **Conflits SQL unicité** (timeseries + total 24 h).
4. **Volume de trafic** read/write.
5. **Top erreurs par type**.

## 2.2 Seuils d’alerte
Seuils initiaux (à ajuster après 2 à 4 semaines d’observation):

- **Alerte warning latence read**: `p95 > 250 ms` sur 10 min.
- **Alerte critical latence read**: `p95 > 400 ms` sur 10 min.
- **Alerte warning error rate handlers**: `> 1%` sur 15 min.
- **Alerte critical error rate handlers**: `> 3%` sur 15 min.
- **Alerte warning conflits SQL**: `>= 5` conflits / 15 min.
- **Alerte critical conflits SQL**: `>= 20` conflits / 15 min.

Routage recommandé:

- warning -> canal `#ops-api`
- critical -> `#ops-api` + astreinte

## 3) Revue post-déploiement J+7

Planifier une revue **J+7** après chaque déploiement incluant la migration ou les handlers `blog_reaction`.

Checklist de revue:

1. Comparer **avant/après déploiement**:
   - latence p95 read
   - taux d’erreurs handlers
   - fréquence conflits SQL d’unicité
2. Vérifier l’évolution de volume trafic (éviter faux positifs dus à charge).
3. Identifier les régressions et décider:
   - correction immédiate,
   - tuning de seuils,
   - action technique (indexation, retry policy, validation métier en amont).
4. Archiver un compte-rendu dans le dossier runbook/release avec:
   - période de comparaison,
   - graphiques exportés,
   - décisions et owner.

Template de synthèse J+7:

- Release:
- Fenêtre “avant”:
- Fenêtre “après”:
- p95 read (avant/après):
- Error rate handlers (avant/après):
- SQL conflicts (avant/après):
- Décisions:
- Owner / ETA:

## 4) SLO/SLA opérationnels

### 4.1 SLO (objectif interne)

- **SLO disponibilité read**: `99.9%` mensuel.
- **SLO latence read**: `p95 < 250 ms` mensuel.
- **SLO fiabilité handlers**: taux d’erreurs `< 1%` mensuel.
- **SLO intégrité écriture**: conflits SQL d’unicité `< 0.2%` des écritures mensuelles.

### 4.2 SLA (engagement de service)

- **SLA disponibilité read**: `99.5%` mensuel.
- **SLA performance read**: `p95 < 400 ms` sur base mensuelle.
- **SLA incident critical**:
  - prise en charge < 15 min (heures d’astreinte)
  - communication initiale < 30 min

### 4.3 Gouvernance des objectifs

- Revue mensuelle SLO/SLA avec équipe produit + ops.
- Ajustement trimestriel des seuils/targets selon charge réelle.
- Toute modification doit être tracée dans ce document et annoncée en release notes internes.
