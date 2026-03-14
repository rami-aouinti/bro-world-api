# Monitoring & alerting — Shop checkout/paiement/webhooks

## Signaux ajoutés

Les services Shop émettent maintenant :

- **Logs applicatifs structurés** (`event` + contexte JSON) pour :
  - refus d'accès scope checkout (`shop.checkout.scope_access_denied`),
  - stock insuffisant (`shop.checkout.insufficient_stock`),
  - refus d'accès scope paiement (`shop.payment.scope_access_denied`),
  - échec de confirmation paiement (`shop.payment.confirm_failed`),
  - webhook invalide (`shop.webhook.invalid`),
  - webhook replay/rejeté (`shop.webhook.replayed`).
- **Compteurs (via événement log monitoring `metric.counter.increment`)** pour :
  - `shop.checkout.failures_total`,
  - `shop.payment_confirm.failures_total`,
  - `shop.webhook.invalid_total`,
  - `shop.webhook.replayed_total`.

## Intégration stack existante

L'intégration passe par la stack de logs existante (Monolog) :

- nouveau canal `monitoring` déclaré ;
- en `prod`, émission JSON vers `stderr` (ingestion actuelle de la plateforme) ;
- en `dev`/`test`, fichier dédié `var/log/monitoring_<env>.log`.

Les métriques sont publiées comme logs structurés sur le canal `monitoring` (`metric`, `value`, `labels`) pour permettre le mapping vers les compteurs de l'outil de monitoring/alerting déjà en place.

## Seuils d'alerte recommandés

Fenêtre glissante 5 min :

- **Checkout failures** (`shop.checkout.failures_total`)
  - warning: `>= 10`
  - critical: `>= 25`
- **Payment confirm failures** (`shop.payment_confirm.failures_total`)
  - warning: `>= 5`
  - critical: `>= 15`
- **Webhook invalid** (`shop.webhook.invalid_total`)
  - warning: `>= 5`
  - critical: `>= 20`
- **Webhook replayed** (`shop.webhook.replayed_total`)
  - warning: `>= 3`
  - critical: `>= 10`

Seuil de ratio (sur 15 min) :

- **Payment confirm failure ratio**
  - warning: `payment_confirm_failures / payment_confirm_total >= 5%`
  - critical: `>= 10%`

## Exemples de labels

- `reason`: `scope_access_denied`, `insufficient_stock`, `provider_failed`, `missing_signature`, `invalid_signature_or_payload`
- `provider`: ex. `mockpay`

## Vérification opérationnelle

1. Déclencher un scénario de refus scope checkout/paiement.
2. Déclencher un checkout avec stock insuffisant.
3. Déclencher un confirm paiement en status `failed` côté provider.
4. Déclencher webhook invalide puis webhook dupliqué (même clé idempotence).
5. Vérifier présence des événements `event` et `metric.counter.increment` dans la pipeline de logs/metrics.
