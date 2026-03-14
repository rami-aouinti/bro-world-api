# Chat read model decision

## Décision
Le calcul métier du non-lu en conversation doit être **participant-scoped** et reposer sur `ConversationParticipant.lastReadMessageAt`.

## Audit des usages de `ChatMessage.is_read` et `ChatMessage.read_at`

### Usages métier actifs
- Aucun calcul métier de non-lu ne dépend désormais de `ChatMessage.is_read` ou `ChatMessage.read_at`.
- Le compteur de non-lu est calculé à partir de `ConversationParticipant.lastReadMessageAt` dans `ConversationListService`.
- Le statut de lecture dans la liste des messages (`read`, `readAt`) est calculé à partir de `ConversationParticipant.lastReadMessageAt` dans `ListConversationMessagesController`.

### Usages legacy restants
- `ChatMessage::$read` (`is_read`) est conservé pour rétrocompatibilité de schéma.
- `ChatMessage::$readAt` (`read_at`) est conservé pour rétrocompatibilité de schéma.
- Les accesseurs/mutateurs `isRead`, `setRead`, `getReadAt`, `setReadAt` sont annotés `@deprecated`.

### Usages retirés
- `CreateMessageCommandHandler` ne force plus `setRead(false)`.
- `PatchMessageCommand` / `PatchMessageCommandHandler` ne gèrent plus la mise à jour globale du flag `read`.
- `MessagePayloadService::extractPatchFields` n'accepte plus le champ `read`.
- `PatchMessageController` retire `read` du contrat OpenAPI de patch message.

## Tests ajoutés
- Test multi-participants vérifiant que le compteur non-lu utilise uniquement le pointeur `lastReadMessageAt` du participant connecté.
- Test du patch message aligné sur l'édition de contenu (et non plus sur un toggle de lecture global legacy).
