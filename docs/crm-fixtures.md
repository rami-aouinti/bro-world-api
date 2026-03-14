# CRM fixtures : génération Faker et volumes

La fixture `LoadCrmData` prend désormais en charge une génération réaliste via Faker avec une seed fixe (`14021991`) pour garantir des données reproductibles en CI/tests.

## Exécution

```bash
php bin/console doctrine:fixtures:load -n
```

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
