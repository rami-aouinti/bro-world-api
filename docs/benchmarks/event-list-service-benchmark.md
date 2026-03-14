# Benchmark protocol — `EventListService`

Objectif: mesurer le coût de la voie de repli DB dans `EventListService` lorsque les filtres texte déclenchent `totalHits > 1000` côté Elasticsearch (la limite est `ELASTIC_IDS_LIMIT = 1000`).

## Ce que le benchmark couvre

- Dataset volumineux d'événements (140k lignes).
- Filtres texte sur `title`, `description`, `location` avec un token courant (`conference`) pour obtenir `totalHits > 1000`.
- Mesures:
  - latence globale (`count + find`) en p50 / p95,
  - temps DB `count` en p50 / p95,
  - temps DB `find` en p50 / p95,
  - coût moyen `count` vs `find`.
- Plan d'exécution (`EXPLAIN QUERY PLAN`) pour identifier les contentions (index, temp B-Tree, scans/logique de scan large).

## Commandes

Depuis la racine du repo:

```bash
php tools/benchmarks/event_list_service_benchmark.php > var/benchmark/event-list-service-benchmark.json
cat var/benchmark/event-list-service-benchmark.json
```

## Notes de protocole

- Le script reconstruit une base SQLite dédiée (`var/benchmark/event-list-service.sqlite`) avec un schéma minimal `application` / `calendar` / `event` aligné sur la forme des requêtes de `EventRepository`.
- Le flux benchmarké est celui où Elasticsearch renvoie un volume > 1000 résultats, ce qui force `EventListService` à appliquer les filtres texte directement côté DB via `LIKE '%...%'`.
- 90 itérations sont exécutées pour produire des percentiles stables (p50/p95) sur `count` et `find`.
