# Runbook — Warmup des endpoints publics

## Commande manuelle

Exécuter la commande suivante depuis le conteneur/app:

```bash
php bin/console app:warmup:public-endpoints
```

Comportement attendu:
- invalide les caches publics ciblés,
- relance le reindex Elasticsearch,
- chauffe les endpoints HTTP publics (critiques puis secondaires),
- affiche un résumé (succès/échecs/endpoints critiques).

Code retour:
- `0` si aucun endpoint **critique** n’échoue,
- non-zéro si lock déjà pris, reindex ES en échec, ou endpoint critique en échec.

## Interprétation des logs

Pendant l’exécution, la sortie console affiche:
- les sections `1/4` à `4/4`,
- une ligne par endpoint: `[OK]` ou `[FAIL]` avec status HTTP, tentatives et latence,
- un résumé final (succès, échecs, critical failures, latence moyenne, durée totale).

Logs observabilité utiles:
- `warmup.public_endpoints.attempt`: un log par tentative endpoint,
- `warmup.public_endpoints.run_completed`: résumé agrégé du run,
- `warmup.public_endpoints.consecutive_critical_failures`: seuil d’alerte atteint sur échecs critiques consécutifs.

## Procédure de relance

1. Vérifier la cause de l’échec (lock/ES/HTTP) dans la sortie console et les logs.
2. Corriger le problème bloquant (cluster ES indisponible, endpoint en erreur, saturation réseau, etc.).
3. Relancer manuellement:

```bash
php bin/console app:warmup:public-endpoints
```

4. Vérifier que:
- le code retour est `0`,
- `Critical failures = 0`,
- les endpoints critiques sont en `[OK]`.

## Diagnostics rapides (lock / ES / HTTP)

### Lock
Symptôme:
- message `Another warmup process is already running.`
- code retour non-zéro.

Actions:
- vérifier qu’un run est déjà en cours (scheduler/cron),
- éviter les lancements concurrents,
- relancer une fois le run actif terminé.

### Elasticsearch
Symptôme:
- message `Elasticsearch reindex step failed.`
- aucun warmup HTTP lancé ensuite.

Actions:
- valider la santé du cluster Elasticsearch,
- vérifier connectivité, credentials et index cibles,
- corriger puis relancer la commande.

### HTTP endpoints
Symptômes:
- endpoint critique en 4xx/5xx/timeout => run en échec,
- endpoint secondaire en échec/timeout => run peut rester OK, mais résumé avec échecs.

Actions:
- reproduire l’appel endpoint (curl),
- vérifier gateway/reverse-proxy, logs applicatifs et délais de réponse,
- ajuster timeout/retry/config endpoint si nécessaire,
- relancer après correction.
