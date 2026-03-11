# Migration des routes API V1 vers un scope `applicationSlug`

## Nouveau format
Toutes les routes métier v1 ciblant `crm`, `recruit`, `shop` et `school` utilisent désormais le format:

`/v1/{module}/{applicationSlug}/...`

## Compatibilité legacy (stratégie)
Pour conserver la compatibilité avec les clients existants:

1. **Phase 1 (immédiate)**: publier les nouvelles routes et annoncer la dépréciation des anciennes routes `/v1/{module}/...`.
2. **Phase 2 (2-4 semaines)**: ajouter au niveau gateway/reverse proxy des redirections 307 vers les nouveaux chemins quand un `applicationSlug` par défaut est connu.
3. **Phase 3**: journaliser les appels legacy (tag `legacy_route=true`) et contacter les clients restants.
4. **Phase 4**: supprimer les routes legacy après la fenêtre de migration.

## Recommandation technique
Quand le slug n'est pas dérivable automatiquement, retourner `400` avec un message explicite pour forcer la migration client.
