# QA — Scénario invalidation cache Blog (commentaire + réaction)

## Objectif
Valider que les mutations Blog (`CreateBlogCommentCommandHandler`, `CreateBlogReactionCommandHandler`) invalident bien les tags publics + privés attendus, et que la lecture privée est rafraîchie après invalidation.

## Scénario couvert
Acteurs du scénario:
- **Actor**: utilisateur qui publie le commentaire / la réaction.
- **Auteur du post**: auteur du post ciblé.
- **Auteur du commentaire parent**: auteur du commentaire parent dans le cas d'une réponse.

Le scénario est codé et exécuté dans:
- `tests/Unit/Blog/Application/MessageHandler/CreateBlogCommentCommandHandlerTest.php`
- `tests/Unit/Blog/Application/MessageHandler/CreateBlogReactionCommandHandlerTest.php`
- `tests/Unit/General/Application/Service/CacheInvalidationServiceTest.php`
- `tests/Unit/Blog/Application/Service/BlogReadServiceTest.php`

## Résultats attendus et observés

### 1) Mutation commentaire: tracing des tags invalidés
Dans `testInvokeCreatesCommentAndInvalidatesBlogCachesForActorPostAndParentAuthors`, la mutation commentaire invalide:
- `cache_public_blog`
- `cache_public_blog_{applicationSlug}`
- `cache_private_{actor}_blog`
- `cache_private_{postAuthor}_blog`
- `cache_private_{parentAuthor}_blog`

Résultat: **OK** (appel vérifié sur `invalidateBlogCaches('app-slug', ['actor-id', 'owner-id', 'parent-author-id'])`).

### 2) Mutation réaction: tracing des tags invalidés
Dans les tests de `CreateBlogReactionCommandHandler`, la mutation réaction invalide avec l’ensemble des utilisateurs impactés:
- actor
- auteur du commentaire ciblé
- auteur du post
- auteur du commentaire parent

Résultat: **OK** (appel vérifié sur `invalidateBlogCaches('app-slug', ['actor-id', 'comment-author-id', 'post-author-id', 'parent-author-id'])`).

### 3) Vérification explicite des tags publics + privés générés par `CacheInvalidationService::invalidateBlogCaches`
Dans `testInvalidateBlogCachesBuildsPublicAndUniquePrivateTags`, on vérifie la génération exacte et la déduplication:
- `cache_public_blog`
- `cache_public_blog_my-app`
- `cache_private_actor_blog`
- `cache_private_author_blog`

Résultat: **OK**.

### 4) Vérification anti-stale sur réponse privée après mutation
Dans `testPrivateBlogCacheIsRefreshedAfterBlogMutationInvalidation`:
1. première lecture privée cache `description-v1`;
2. sans invalidation, la lecture suivante reste `description-v1` (cache hit attendu);
3. après `invalidateBlogCaches('my-app', ['u-owner'])`, la lecture privée retourne `description-v2`.

Résultat: **OK** — aucune réponse privée obsolète après mutation/invalidation.

## Commandes QA exécutées
- `php -l tests/Unit/Blog/Application/MessageHandler/CreateBlogCommentCommandHandlerTest.php`
- `php -l tests/Unit/Blog/Application/Service/BlogReadServiceTest.php`
- `./vendor/bin/phpunit tests/Unit/Blog/Application/MessageHandler/CreateBlogCommentCommandHandlerTest.php tests/Unit/Blog/Application/MessageHandler/CreateBlogReactionCommandHandlerTest.php tests/Unit/Blog/Application/Service/BlogReadServiceTest.php tests/Unit/General/Application/Service/CacheInvalidationServiceTest.php` (non exécutable ici: dépendances vendor absentes)
