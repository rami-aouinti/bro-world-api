# CRM fixtures : génération Faker, invariants et volumes

La fixture `LoadCrmData` génère un dataset réaliste avec Faker (`fr_FR`) et une seed fixe (`14021991`) pour des données reproductibles en CI/tests.

## Exécution

```bash
php bin/console doctrine:fixtures:load -n
```

## Invariants canoniques (CRM General Core)

Sur l'application `crm-general-core` et la première company générée, la fixture force des objets « canoniques » stables pour simplifier les tests de non-régression.

### 1) Les 4 identities canoniques

La fixture crée toujours les 4 employés CRM déterministes suivants (liés aux users référencés) :

- `User-john-root` → John Root (`owner`)
- `User-john-admin` → John Admin (`admin`)
- `User-john-user` → John User (`sales`)
- `User-john-api` → John Api (`api`)

Ces identités sont utilisées comme assignees canoniques, en particulier sur Bro World (sprints + tasks).

### 2) Les 3 projets fixes + owner GitHub unique

Toujours sur `crm-general-core` / company #1, les 3 premiers projets sont figés :

- `Bro World` (`PRJ-BRO`, status `ACTIVE`)
- `Shopware World` (`PRJ-SHOP`, status `PLANNED`)
- `Oro World` (`PRJ-ORO`, status `ON_HOLD`)

Le provisioning de repositories GitHub utilise un owner unique (`john-root`) pour ces projets (ex: `john-root/bro-world-api`, `john-root/shopware-world-web`, etc. selon slug projet + suffixe `-api` / `-web`).

### 3) Règle `firstName` / `lastName` + fallback

- Les employés canoniques CRM sont créés avec `firstName`/`lastName` explicitement renseignés.
- En règle générale (y compris hors fixtures), le listener `CrmEmployeeEntityEventListener` applique un fallback :
  - si `employee.firstName` est vide, il est hydraté depuis `user.firstName`
  - si `employee.lastName` est vide, il est hydraté depuis `user.lastName`

👉 Cela évite des employés partiellement nommés lors des créations/mises à jour.

### 4) Timeline Bro World (historique + sprint semaine courante)

Le projet `PRJ-BRO` suit une timeline déterministe dédiée :

- minimum **2 sprints** (même si le volume demandé est inférieur)
- les sprints « passés » sont `CLOSED`
- le dernier sprint est toujours `ACTIVE`, avec :
  - `startDate = monday this week` (00:00)
  - `endDate = startDate + 13 days`
- les tâches Bro World sont dérivées du statut sprint (`IN_PROGRESS`/`TODO`/`DONE`) et reçoivent des priorités cycliques (`CRITICAL`, `HIGH`, `MEDIUM`, `LOW`).

## Volumes disponibles

Le volume est piloté par la variable d'environnement `CRM_FIXTURE_VOLUME` :

- `small` : dataset léger pour développement rapide.
- `medium` (défaut) : dataset équilibré.
- `large` : dataset dense pour benchmarks/tests de charge locale.

Exemples :

```bash
CRM_FIXTURE_VOLUME=small php bin/console doctrine:fixtures:load -n
CRM_FIXTURE_VOLUME=medium php bin/console doctrine:fixtures:load -n
CRM_FIXTURE_VOLUME=large php bin/console doctrine:fixtures:load -n
```

### Variables d’environnement influençant le volume

- `CRM_FIXTURE_VOLUME` : unique variable dédiée au profil de volumétrie (`small|medium|large`).
- Valeur invalide/absente : fallback automatique sur `medium`.

## Agrégats générés

La fixture est structurée par agrégat :

- `companies`
- `contacts`
- `employees`
- `projects`
- `sprints`
- `tasks`
- `taskRequests`
- `billings`

Les `attachments` (`Project`, `Task`) respectent le format attendu par les contrôleurs d’upload (`url`, `originalName`, `mimeType`, `size`, `extension`, `uploadedAt`) et les `wikiPages` (`Project`) respectent la structure utilisée par l’API (`id`, `title`, `content`, `createdAt`).

## Exemple de résultat attendu (vérification manuelle rapide)

Après un `doctrine:fixtures:load -n` avec `CRM_FIXTURE_VOLUME=small`, on doit pouvoir vérifier rapidement :

- présence des employés `john-root`, `john-admin`, `john-user`, `john-api` dans le CRM
- présence des projets canoniques `PRJ-BRO`, `PRJ-SHOP`, `PRJ-ORO` (au moins sur `crm-general-core` / company #1)
- sur `PRJ-BRO` :
  - au moins un sprint `CLOSED`
  - un sprint `ACTIVE` démarrant le lundi de la semaine courante
  - des tâches intitulées `Bro World - Sprint ... task ...`

Exemple de liste courte attendue (illustrative) :

```text
Employees: John Root, John Admin, John User, John Api
Projects: PRJ-BRO (Bro World), PRJ-SHOP (Shopware World), PRJ-ORO (Oro World)
Bro World sprints: Sprint 1 - Bro World Delivery Wave (CLOSED), Sprint 2 - Bro World Delivery Wave (ACTIVE)
```
