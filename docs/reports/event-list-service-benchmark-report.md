# Rapport court — Benchmark `EventListService`

## Résultats chiffrés

- Dataset: **140 000 événements**.
- `totalHits` pour le filtre texte (`conference`): **29 674** (> 1000).
- Latence globale (`count + find`):
  - **p50: 246.58 ms**
  - **p95: 298.00 ms**
- Temps DB `count`:
  - **p50: 115.46 ms**
  - **p95: 139.53 ms**
  - moyenne: **117.58 ms**
- Temps DB `find`:
  - **p50: 131.75 ms**
  - **p95: 158.98 ms**
  - moyenne: **135.16 ms**

## Points de contention observés

1. **Coût cumulé des deux requêtes**: `count` + `find` représente ~250–300 ms au p95, ce qui est élevé pour une route de listing paginé.
2. **Filtrage texte coûteux**: la forme `LOWER(column) LIKE LOWER('%...%')` empêche l'usage efficace d'index de préfixe sur `title`/`description`/`location`.
3. **Coûts de tri/déduplication**: le plan d'exécution indique l'usage de structures temporaires (`USE TEMP B-TREE FOR DISTINCT` et `ORDER BY`), ajoutant de la latence.
4. **`count(DISTINCT ...)` non trivial**: présence d'un `TEMP B-TREE FOR count(DISTINCT)`, ce qui confirme un coût non négligeable de la pagination totale.

## Recommandation

**Optimiser** (ne pas garder tel quel pour les charges élevées):

- Priorité 1: éviter `LIKE '%...%'` pour les gros volumes (rester sur Elasticsearch même au-delà de 1000 IDs, ou basculer vers recherche full-text DB).
- Priorité 2: réduire le coût du `count` (compteur approximatif/caché, ou `count` conditionnel).
- Priorité 3: revoir la requête pour limiter `DISTINCT + ORDER BY` coûteux (ex: stratégie en 2 étapes avec IDs triés puis fetch détaillé).

Conclusion: le comportement actuel est acceptable pour petit volume, mais **sous charge avec filtres texte très fréquents et `totalHits > 1000` il devient un goulot d'étranglement DB**.
