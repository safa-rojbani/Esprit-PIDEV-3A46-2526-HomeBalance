# Documentation Technique — Module Messagerie HomeBalance

**Projet :** HomeBalance  
**Module :** Messagerie temps réel avec IA et SMS Fallback  
**Date :** 24 février 2026  
**Version :** 1.0  

---

## Table des matières

1. [Vue d'ensemble de l'architecture technique](#section-1--vue-densemble-de-larchitecture-technique)
2. [Schéma entités / base de données](#section-2--schéma-entités--base-de-données)
3. [Référence des endpoints API](#section-3--référence-des-endpoints-api)
4. [Documentation des services et classes](#section-4--documentation-des-services-et-classes)
5. [Flux Messenger & async](#section-5--flux-messenger--async)
6. [Guide d'installation et de configuration](#section-6--guide-dinstallation-et-de-configuration)

---

# SECTION 1 — Vue d'ensemble de L'architecture technique

## 1.1 Stack technique utilisée

Le module messagerie de HomeBalance utilise la stack suivante :

| Composant | Technologie | Version |
|-----------|-------------|---------|
| Framework | Symfony | 6/7 |
| Serveur HTTP | Nginx + PHP-FPM | - |
| Base de données | PostgreSQL | 14+ |
| ORM | Doctrine | 2.x |
| Queue async | Symfony Messenger | - |
| Temps réel | Mercure (SSE) | - |
| Templates | Twig | - |
| AI | Hugging Face Inference API | REST |
| SMS | Twilio REST API | - |

## 1.2 Schéma global du module messagerie

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              FRONTEND (Twig/JS)                            │
│  ┌─────────────────┐  ┌──────────────────┐  ┌────────────────────────────┐ │
│  │  index.html.twig │  │ messaging.js     │  │  Turbo Stream Responses   │ │
│  │  (liste conv.)  │  │ (Mercure SSE)   │  │  (updates temps réel)    │ │
│  └────────┬────────┘  └────────┬─────────┘  └────────────┬─────────────┘ │
└───────────┼───────────────────┼──────────────────────┼──────────────────┘
            │                   │                      │
            │ HTTP/REST         │ SSE (Mercure)        │
            ▼                   ▼                      ▼
┌───────────────────────────────────────────────────────────────────────────────┐
│                           CONTROLLERS (Symfony)                             │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │  MessagingController                                                    ││
│  │  - GET/POST /portal/messaging                                          ││
│  │  - GET/POST /portal/messaging/{id}                                     ││
│  │  - POST /portal/messaging/{id}/typing                                  ││
│  │  - POST /portal/messaging/{id}/ai/suggest-replies                     ││
│  │  - POST /portal/messaging/{id}/ai/summarize                            ││
│  │  - POST /portal/messaging/presence/ping                                ││
│  └─────────────────────────────────────────────────────────────────────────┘│
└───────────┬─────────────────────────────────────────────────────────────────┘
            │
            │ Appelle les Services
            ▼
┌───────────────────────────────────────────────────────────────────────────────┐
│                           SERVICES (Business Logic)                         │
│  ┌──────────────────┐  ┌───────────────────┐  ┌─────────────────────────┐ │
│  │ MercurePublisher│  │ ReactionService   │  │ SmartReplyService       │ │
│  │ - publishNewMsg │  │ - addReaction     │  │ - suggestReplies()      │ │
│  │ - publishTyping │  │ - removeReaction  │  │                         │ │
│  │ - publishRead   │  │ - getReactions    │  │ SummarizationService    │ │
│  │ - publishPresence│  │                   │  │ - summarize()           │ │
│  └────────┬─────────┘  └─────────┬──────────┘  └───────────┬─────────────┘ │
│           │                      │                          │               │
│           │          ┌──────────┴──────────┐              │               │
│           │          │                       │              │               │
│           ▼          ▼                       ▼              ▼               │
│  ┌──────────────────┐  ┌───────────────────┐  ┌─────────────────────────┐ │
│  │ MercureTokenFac. │  │ AuditTrailService  │  │ HuggingFaceClient       │ │
│  │ - buildToken()   │  │ - record()        │  │ - query()              │ │
│  └──────────────────┘  └─────────────────────┘  └─────────────────────────┘ │
│                                                                             │
│  ┌──────────────────┐  ┌───────────────────┐                              │
│  │ TwilioClient     │  │ ActivityPatternS. │                              │
│  │ - send()         │  │ - calculatePeak() │                              │
│  │ - validatePhone()│  │ - getNextOptima() │                              │
│  └──────────────────┘  └───────────────────────┘                          │
└───────────┬─────────────────────────────────────────────────────────────────┘
            │
            │ Dispatch Messages (Async)
            ▼
┌───────────────────────────────────────────────────────────────────────────────┐
│                        SYMFONY MESSENGER (Async Queue)                      │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ Transports: async (Doctrine), failed                                      ││
│  │                                                                          ││
│  │ Messages:                                                                ││
│  │ - SendSmsFallbackMessage         → SendSmsFallbackHandler                ││
│  │ - GenerateSmartRepliesMessage    → GenerateSmartRepliesHandler           ││
│  │ - SummarizeConversationMessage  → SummarizeConversationHandler          ││
│  │ - RecalculateActivityPatternMsg → RecalculateActivityPatternHandler     ││
│  └─────────────────────────────────────────────────────────────────────────┘│
└───────────┬─────────────────────────────────────────────────────────────────┘
            │
            │ Execute handlers
            ▼
┌───────────────────────────────────────────────────────────────────────────────┐
│                           EXTERNAL APIS                                     │
│  ┌──────────────────┐  ┌───────────────────┐  ┌─────────────────────────┐ │
│  │ Mercure Hub      │  │ Hugging Face      │  │ Twilio                  │ │
│  │ (SSE streaming)  │  │ (AI inference)    │  │ (SMS sending)           │ │
│  └──────────────────┘  └───────────────────┘  └─────────────────────────┘ │
└───────────────────────────────────────────────────────────────────────────────┘
```

## 1.3 Rôle de chaque couche

### 1.3.1 Controllers (Couche présentation)
- **Responsabilité** : Recevoir les requêtes HTTP, valider les entrées, appeler les services appropriés, retourner les réponses
- **Location** : `src/Controller/MessagingController.php`
- **Patterns** : REST, 返回 JSON ou Twig templates

### 1.3.2 Services (Business Logic)
- **Responsabilité** : Implémenter la logique métier, gérer les transactions, orchestrer les operations
- **Types** :
  - `MercurePublisher` : Publier les événements temps réel
  - `ReactionService` : Gérer les réactions aux messages
  - `SmartReplyService` / `SummarizationService` : Appels IA
  - `TwilioClient` : Envoi SMS
  - `ActivityPatternService` : Calcul des patterns d'activité

### 1.3.3 Messages & Handlers (Async)
- **Responsabilité** : Traitement asynchrone des tâches lourdes (IA, SMS)
- **Pattern** : Command/Query Handler
- **Transport** : Doctrine (async)

### 1.3.4 Mercure (Temps réel)
- **Responsabilité** : Streaming bidirectionnel via Server-Sent Events
- **Topics** :
  - `messaging/conversation/{id}` — Messages d'une conversation
  - `messaging/user/{id}` — Messages personnels (smart replies, summaries)
  - `messaging/user/{id}/presence` — Présence en ligne

## 1.4 Flux de données — Envoi d'un message (bout en bout)

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│ Utilisateur │     │ Controller   │     │   Entity     │     │   Mercure   │
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │                    │                    │                    │
       │ 1. POST message    │                    │                    │
       │───────────────────▶│                    │                    │
       │                    │                    │                    │
       │               2. Validate & Create    │                    │
       │               Message entity            │                    │
       │                    │                    │                    │
       │                    │ 3. persist()      │                    │
       │                    │───────────────────▶│                    │
       │                    │                    │                    │
       │                    │               4. flush()              │
       │                    │◀──────────────────│                    │
       │                    │                    │                    │
       │               5. publishNewMessage()   │                    │
       │                    │────────────────────────────────────────▶│
       │                    │                    │                    │
       │               6. dispatch SMS async    │                    │
       │                    │───────────────────▶│ (Messenger)         │
       │                    │                    │                    │
       │ 7. Redirect/Turbo  │                    │                    │
       │◀───────────────────│                    │                    │
       │                    │                    │                    │
       │                    │                    │ 8. SSE broadcast  │
       │                    │                    │◀───────────────────│
       │                    │                    │                    │
       │ 9. Update UI       │                    │                    │
       │◀───────────────────┼────────────────────┴────────────────────┘
       │
```

---

# SECTION 2 — Schéma entités / base de données

## 2.1 Message

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| content | TEXT | Yes | null | Corps du message |
| attachmentURL | VARCHAR(255) | Yes | null | URL de la pièce jointe |
| sentAt | DATETIME | No | - | Date d'envoi |
| isRead | BOOL | No | false | Lu par le destinataire |
| conversation_id | INT | No | - | FK vers Conversation |
| sender_id | VARCHAR(36) | No | - | FK vers User (UUID) |
| parent_message_id | INT | Yes | null | FK vers Message (threading) |
| isEdited | BOOL | No | false | Message edited |
| isDeleted | BOOL | No | false | Soft delete |
| editedAt | DATETIME | Yes | null | Date de dernière modification |

**Relations :**
- ManyToOne → Conversation (conversation)
- ManyToOne → User (sender)
- ManyToOne → Message (parentMessage)
- OneToMany → MessageReaction
- OneToMany → MessageEditHistory

**Contraintes :**
- FK: conversation_id NOT NULL
- FK: sender_id NOT NULL

---

## 2.2 Conversation

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| conversationName | VARCHAR(255) | No | - | Nom de la conversation |
| type | VARCHAR(255) | No | - | TypeConversation enum (PRIVATE, GROUP) |
| createdAt | DATETIME | No | - | Date de création |
| family_id | INT | No | - | FK vers Family |
| createdBy_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- OneToMany → ConversationParticipant
- OneToMany → Message
- ManyToOne → Family
- ManyToOne → User (createdBy)

---

## 2.3 ConversationParticipant

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| joinedAt | DATETIME | No | - | Date de join |
| conversation_id | INT | No | - | FK vers Conversation |
| user_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- ManyToOne → Conversation
- ManyToOne → User

---

## 2.4 MessageReaction

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| emoji | VARCHAR(10) | No | - | Emoji de la réaction |
| message_id | INT | No | - | FK vers Message |
| user_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- ManyToOne → Message
- ManyToOne → User

**Contraintes :**
- Unique: (message_id, user_id, emoji)

---

## 2.5 MessageReadReceipt

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| readAt | DATETIME | No | - | Date de lecture |
| message_id | INT | No | - | FK vers Message |
| user_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- ManyToOne → Message
- ManyToOne → User

**Contraintes :**
- Unique: (message_id, user_id)

---

## 2.6 MessageEditHistory

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| oldContent | TEXT | No | - | Ancien contenu |
| editedAt | DATETIME | No | - | Date de l'édition |
| message_id | INT | No | - | FK vers Message |
| editedBy_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- ManyToOne → Message
- ManyToOne → User (editedBy)

---

## 2.7 PinnedMessage

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| pinnedAt | DATETIME | No | - | Date d'épinglage |
| conversation_id | INT | No | - | FK vers Conversation |
| message_id | INT | No | - | FK vers Message |
| pinnedBy_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- ManyToOne → Conversation
- ManyToOne → Message
- ManyToOne → User (pinnedBy)

**Contraintes :**
- Max 3 épinglés par conversation (validation métier)

---

## 2.8 ConversationUserState

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| isArchived | BOOL | No | false | Conversation archivée |
| isMuted | BOOL | No | false | Conversation en sourdine |
| conversation_id | INT | No | - | FK vers Conversation |
| user_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- ManyToOne → Conversation
- ManyToOne → User

**Contraintes :**
- Unique: (conversation_id, user_id)

---

## 2.9 AiSmartReply

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| suggestions | JSON | No | [] | Tableau de suggestions |
| generatedAt | DATETIME | No | - | Date de génération |
| isUsed | BOOL | No | false | Suggestion utilisée |
| conversation_id | INT | No | - | FK vers Conversation |
| user_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- ManyToOne → Conversation
- ManyToOne → User

---

## 2.10 AiConversationSummary

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| summary | LONGTEXT | No | - | Texte du résumé |
| messageCount | INT | No | 0 | Nombre de messages |
| generatedAt | DATETIME | No | - | Date de génération |
| conversation_id | INT | No | - | FK vers Conversation |
| requestedBy_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- ManyToOne → Conversation
- ManyToOne → User (requestedBy)

---

## 2.11 UserPresence

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| lastSeenAt | DATETIME | Yes | null | Dernière activité |
| isOnline | BOOL | No | false | En ligne |
| user_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- OneToOne → User

---

## 2.12 UserActivityPattern

| Champ | Type | Nullable | Défaut | Description |
|-------|------|----------|--------|-------------|
| id | INT | No | AUTO | Primary key |
| peakHours | JSON | Yes | null | Heures de pointe [8, 18, 20] |
| lastCalculatedAt | DATETIME | Yes | null | Dernier calcul |
| user_id | VARCHAR(36) | No | - | FK vers User |

**Relations :**
- OneToOne → User

---

# SECTION 3 — Référence des endpoints API

## 3.1 Endpoints de base

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | /portal/messaging | Liste des conversations |
| GET | /portal/messaging/{id} | Afficher une conversation |
| POST | /portal/messaging/create | Créer une conversation |

---

## 3.2 Messages

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | /portal/messaging/conversation/{id}/message/send | Envoyer un message |
| PUT | /portal/messaging/message/{id}/edit | Éditer un message |
| DELETE | /portal/messaging/message/{id} | Supprimer (soft delete) |

### POST /portal/messaging/conversation/{id}/message/send

**Description** : Envoyer un nouveau message dans une conversation

**Paramètres path** :
- `id` (int) : ID de la conversation

**Body** :
```json
{
  "message[content]": "Contenu du message",
  "message[parentMessageId]": 123,
  "message[attachment]": File
}
```

**Réponse** : Redirect vers la conversation (Turbo Stream)

**Guard** : Utilisateur connecté, participant de la conversation

---

## 3.3 Réactions

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | /portal/messaging/message/{id}/react | Ajouter/enlever réaction |

### POST /portal/messaging/message/{id}/react

**Description** : Ajouter ou supprimer une réaction à un message

**Paramètres path** :
- `id` (int) : ID du message

**Body** :
```json
{
  "emoji": "👍"
}
```

**Réponse** :
```json
{
  "ok": true,
  "messageId": 123,
  "reactions": {
    "👍": {"count": 2, "users": {"user1": true, "user2": true}}
  }
}
```

**Guard** : Utilisateur connecté

**Mercure** : Événement `reaction_update` broadcasté

---

## 3.4 Lecture

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | /portal/messaging/{id}/read | Marquer messages comme lus |

### POST /portal/messaging/{id}/read

**Description** : Marquer des messages comme lus

**Paramètres path** :
- `id` (int) : ID de la conversation

**Body** :
```json
{
  "messageIds": [1, 2, 3, 4, 5]
}
```

**Réponse** :
```json
{
  "ok": true,
  "marked": 5
}
```

**Guard** : Participant de la conversation

**Mercure** : Événement `read_receipt` broadcasté

---

## 3.5 Épinglage

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | /portal/messaging/conversation/{id}/pin/{messageId} | Épingler |
| DELETE | /portal/messaging/conversation/{id}/pin/{messageId} | Désépingler |

### POST /portal/messaging/conversation/{id}/pin/{messageId}

**Description** : Épingler un message (max 3 par conversation)

**Paramètres path** :
- `id` (int) : ID de la conversation
- `messageId` (int) : ID du message

**Réponse** :
```json
{
  "ok": true
}
```

**Guard** : Participant de la conversation

---

## 3.6 Indicateur de frappe

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | /portal/messaging/{id}/typing | Indicateur frappe |

### POST /portal/messaging/{id}/typing

**Description** : Envoyer l'indicateur de frappe

**Paramètres path** :
- `id` (int) : ID de la conversation

**Body** :
```json
{
  "isTyping": true
}
```

**Réponse** :
```json
{
  "ok": true
}
```

**Guard** : Participant de la conversation

**Mercure** : Événement `typing` broadcasté (éphémère, expire après 3s)

---

## 3.7 Archivage / Sourdine

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | /portal/messaging/conversation/{id}/archive | Archiver |
| POST | /portal/messaging/conversation/{id}/mute | Activer sourdine |

### POST /portal/messaging/conversation/{id}/archive

**Description** : Archiver une conversation

**Réponse** : Redirect vers /portal/messaging

**Guard** : Participant de la conversation

---

## 3.8 Galerie

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | /portal/messaging/conversation/{id}/gallery | Médias partagés |

### GET /portal/messaging/conversation/{id}/gallery

**Description** : Liste des médias partagés dans la conversation

**Paramètres path** :
- `id` (int) : ID de la conversation

**Réponse** : Twig template `gallery.html.twig`

**Guard** : Participant de la conversation

---

## 3.9 Recherche

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | /portal/messaging/conversation/{id}/search?q= | Recherche |

### GET /portal/messaging/conversation/{id}/search?q=

**Description** : Recherche full-text dans les messages

**Paramètres query** :
- `q` (string) : Requête de recherche

**Réponse** : Twig template avec résultats

**Guard** : Participant de la conversation

---

## 3.10 Compteur non lus

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | /portal/messaging/unread-count | Badge non lus |

### GET /portal/messaging/unread-count

**Description** : Nombre de messages non lus global

**Réponse** :
```json
{
  "unread": 12,
  "byConversation": {
    "1": 5,
    "2": 3,
    "3": 4
  }
}
```

**Guard** : Utilisateur connecté

---

## 3.11 Intelligence Artificielle

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | /portal/messaging/conversation/{id}/ai/suggest-replies | Smart replies |
| POST | /portal/messaging/conversation/{id}/ai/summarize | Résumé IA |

### POST /portal/messaging/conversation/{id}/ai/suggest-replies

**Description** : Générer des suggestions de réponse via IA

**Condition** : Au moins 1 message non lu

**Réponse** :
```json
{
  "status": "accepted"
}
```

**Guard** : Participant de la conversation

**Flux async** : GenerateSmartRepliesMessage → Handler → Mercure `smart_replies`

---

### POST /portal/messaging/conversation/{id}/ai/summarize

**Description** : Générer un résumé de conversation via IA

**Condition** : Plus de 10 messages

**Body** (optionnel) :
```json
{
  "limit": 50
}
```

**Réponse** :
```json
{
  "status": "accepted"
}
```

**Guard** : Participant de la conversation

**Flux async** : SummarizeConversationMessage → Handler → Mercure `conversation_summary`

---

## 3.12 Présence

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | /portal/messaging/presence/ping | Ping présence |

### POST /portal/messaging/presence/ping

**Description** : Mettre à jour la présence utilisateur

**Réponse** :
```json
{
  "ok": true
}
```

**Guard** : Utilisateur connecté

**Mercure** : Événement `presence` broadcasté

---

# SECTION 4 — Documentation des services et classes

## 4.1 Services Messaging

### MercurePublisher

**Namespace** : `App\Service\Messaging\MercurePublisher`

**Responsabilité** : Publier les événements temps réel via Mercure (SSE)

**Méthodes publiques** :

| Méthode | Signature | Description |
|---------|-----------|-------------|
| publishNewMessage | `publishNewMessage(Message $message): void` | Broadcast nouveau message |
| publishTyping | `publishTyping(Conversation $conversation, User $user, bool $isTyping): void` | Broadcast indicateur frappe |
| publishReadReceipt | `publishReadReceipt(Message $message, User $reader): void` | Broadcast accuse lecture |
| publishReactionUpdate | `publishReactionUpdate(Message $message, array $reactions): void` | Broadcast reactions |
| publishPresence | `publishPresence(User $user, bool $online): void` | Broadcast presence |

**Dépendances** :
- `HubInterface $hub`

---

### MercureTokenFactory

**Namespace** : `App\Service\Messaging\MercureTokenFactory`

**Responsabilité** : Générer les tokens JWT pour Mercure

**Méthodes publiques** :

| Méthode | Signature | Description |
|---------|-----------|-------------|
| buildSubscriberToken | `buildSubscriberToken(User $user, ?Conversation $conversation = null): string` | Génère token JWT |

**Dépendances** :
- `$jwtSecret` (paramètre)

---

### ReactionService

**Namespace** : `App\Service\Messaging\ReactionService`

**Responsabilité** : Gérer les réactions aux messages

**Méthodes publiques** :

| Méthode | Signature | Description |
|---------|-----------|-------------|
| addReaction | `addReaction(Message $message, User $user, string $emoji): array` | Ajoute réaction |
| removeReaction | `removeReaction(Message $message, User $user, string $emoji): array` | Supprime réaction |
| getGroupedReactions | `getGroupedReactions(array $messageIds): array` | Reactions groupées |

**Dépendances** :
- `EntityManagerInterface $em`
- `MercurePublisher $publisher`

---

## 4.2 Services AI

### HuggingFaceClient

**Namespace** : `App\Service\AI\HuggingFaceClient`

**Responsabilité** : Appeler l'API Hugging Face pour l'inférence AI

**Méthodes publiques** :

| Méthode | Signature | Description |
|---------|-----------|-------------|
| query | `query(string $model, array $payload): array` | Requête vers HF |

**Constructeur** :
```php
public function __construct(
    ?HttpClientInterface $httpClient = null
)
```

**Gestion des erreurs** :
- Retry 3x sur 503 (model loading)
- Lance `HuggingFaceException` sur erreur

**Dépendances** :
- `HttpClientInterface`

---

### SmartReplyService

**Namespace** : `App\Service\AI\SmartReplyService`

**Responsabilité** : Générer des suggestions de réponse via IA

**Méthodes publiques** :

| Méthode | Signature | Description |
|---------|-----------|-------------|
| suggestReplies | `suggestReplies(Conversation $conversation, User $currentUser): array` | Retourne 2-3 suggestions |

**Modèle** : `facebook/blenderbot-400M-distill`

**Dépendances** :
- `HuggingFaceClient`
- `MessageRepository`

**Comportement** :
- Retourne tableau vide silencieusement en cas d'erreur
- Max 100 caractères par suggestion

---

### SummarizationService

**Namespace** : `App\Service\AI\SummarizationService`

**Responsabilité** : Résumer les messages d'une conversation

**Méthodes publiques** :

| Méthode | Signature | Description |
|---------|-----------|-------------|
| summarize | `summarize(Conversation $conversation, int $limit = 50): string` | Résumé de la conversation |

**Modèle** : `facebook/bart-large-cnn`

**Dépendances** :
- `HuggingFaceClient`
- `MessageRepository`

**Comportement** :
- Jette `HuggingFaceException` sur erreur (pour retry Messenger)
- Exclut les messages supprimés

---

## 4.3 Services SMS

### TwilioClient

**Namespace** : `App\Service\SMS\TwilioClient`

**Responsabilité** : Envoyer des SMS via Twilio

**Méthodes publiques** :

| Méthode | Signature | Description |
|---------|-----------|-------------|
| send | `send(string $toNumber, string $message): bool` | Envoie SMS |
| validatePhoneNumber | `validatePhoneNumber(string $phoneNumber): bool` | Valide format E.164 |

**Constructeur** :
```php
public function __construct(
    ?HttpClientInterface $httpClient = null,
    ?LoggerInterface $logger = null
)
```

**Comportement** :
- Retourne `true`/`false` (pas d'exception)
- Log les erreurs via Logger

**Dépendances** :
- `HttpClientInterface`
- `LoggerInterface`

---

### ActivityPatternService

**Namespace** : `App\Service\SMS\ActivityPatternService`

**Responsabilité** : Calculer les patterns d'activité utilisateur pour smart timing

**Méthodes publiques** :

| Méthode | Signature | Description |
|---------|-----------|-------------|
| calculatePeakHours | `calculatePeakHours(User $user): array` | Calcule heures de pointe |
| getNextOptimalSendTime | `getNextOptimalSendTime(User $user): DateTimeImmutable` | Prochaine fenêtre optimale |

**Dépendances** :
- `AuditTrailRepository`
- `UserActivityPatternRepository`
- `EntityManagerInterface`
- `AuditTrailService`

---

## 4.4 Message Handlers AI

### GenerateSmartRepliesHandler

**Namespace** : `App\MessageHandler\AI\GenerateSmartRepliesHandler`

**Responsabilité** : Générer et persister les smart replies

**Méthode** :
```php
public function __invoke(GenerateSmartRepliesMessage $message): void
```

**Flux** :
1. Récupère conversation et utilisateur
2. Appelle SmartReplyService
3. Persiste AiSmartReply
4. Broadcast via Mercure (topic user)

**Dépendances** :
- `SmartReplyService`
- `ConversationRepository`
- `UserRepository`
- `EntityManagerInterface`
- `HubInterface`

---

### SummarizeConversationHandler

**Namespace** : `App\MessageHandler\AI\SummarizeConversationHandler`

**Responsabilité** : Générer et persister le résumé de conversation

**Méthode** :
```php
public function __invoke(SummarizeConversationMessage $message): void
```

**Flux** :
1. Récupère conversation et utilisateur
2. Appelle SummarizationService
3. Persiste AiConversationSummary
4. Record audit trail (ai.summary.generated)
5. Broadcast via Mercure

**Dépendances** :
- `SummarizationService`
- `ConversationRepository`
- `UserRepository`
- `EntityManagerInterface`
- `HubInterface`
- `AuditTrailService`

---

## 4.5 Message Handlers SMS

### SendSmsFallbackHandler

**Namespace** : `App\MessageHandler\SMS\SendSmsFallbackHandler`

**Responsabilité** : Envoyer SMS fallback si utilisateur offline

**Méthode** :
```php
public function __invoke(SendSmsFallbackMessage $message): void
```

**Checks avant envoi** :
1. Telephone existant?
2. Utilisateur online? (seuil 5 min)
3. Canal SMS activé dans matrice?
4. Quiet hours?
5. Conversation mute?

**Audit trail** :
- `sms.sent` si succes
- `sms.failed` si echec
- `sms.skipped` + reason si skip

**Dépendances** :
- `UserRepository`
- `TwilioClient`
- `EntityManagerInterface`
- `AuditTrailService`
- `RequestStack`

---

### RecalculateActivityPatternHandler

**Namespace** : `App\MessageHandler\SMS\RecalculateActivityPatternHandler`

**Responsabilité** : Recalculer les patterns d'activité

**Méthode** :
```php
public function __invoke(RecalculateActivityPatternMessage $message): void
```

**Flux** :
1. Récupère utilisateur
2. Appelle ActivityPatternService::calculatePeakHours()
3. Persist UserActivityPattern
4. Record audit trail

**Dépendances** :
- `UserRepository`
- `ActivityPatternService`

---

# SECTION 5 — Flux Messenger & async (diagrammes)

## Flux 1 — Envoi d'un message et SMS fallback

```
┌──────────────────────┬──────────────────────────────────────────────────────────┐
│ Composant            │ Action                                                  │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ Frontend             │ 1. Soumet formulaire message                            │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ MessagingController   │ 2. Créer Message entity                                  │
│                      │ 3. persist() + flush()                                  │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ MercurePublisher     │ 4. broadcast new_message (conversation topic)            │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ MessagingController   │ 5. Pour chaque participant (except sender):             │
│                      │    dispatch SendSmsFallbackMessage                       │
│                      │    avec DelayStamp(300000ms = 5min)                     │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ MessageBus           │ 6. Route vers transport async                            │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ SendSmsFallbackHandler│ 7. Load recipient                                      │
│                      │ 8. Check: phone? online? matrix? quiet? muted?        │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│                      │ Si offline + tous checks OK:                             │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ ActivityPatternService│ 9. getNextOptimalSendTime()                            │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│                      │ Si optimal time > 2min:                                 │
│                      │ 10. Redispatch avec nouveau DelayStamp                 │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│                      │ Si send now:                                            │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ TwilioClient         │ 11. POST to Twilio API                                   │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ AuditTrailService    │ 12. record(sms.sent) ou record(sms.failed)               │
└──────────────────────┴──────────────────────────────────────────────────────────┘
```

---

## Flux 2 — Smart Reply Suggestions

```
┌──────────────────────┬──────────────────────────────────────────────────────────┐
│ Composant            │ Action                                                  │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ Frontend             │ 1. Conversation ouverte avec messages non lus           │
│                      │ 2. POST /ai/suggest-replies                            │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ MessagingController   │ 3. Vérifie participant                                 │
│                      │ 4. dispatch GenerateSmartRepliesMessage                 │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ MessageBus           │ 5. Route vers async                                     │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ GenerateSmartReplies │ 6. Load last 5 messages                                │
│ Handler              │ 7. Format conversation context                          │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ SmartReplyService    │ 8. Call HuggingFaceClient (blenderbot-400M-distill)     │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ HuggingFaceClient    │ 9. POST to HF API                                      │
│                      │ 10. Parse response (2-3 suggestions)                   │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ EntityManager        │ 11. Persist AiSmartReply                               │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ HubInterface         │ 12. Mercure broadcast (user topic)                      │
│                      │    Event: smart_replies                                │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ Frontend (JS)        │ 13. Listen SSE                                         │
│                      │ 14. Display suggestion pills above input                 │
└──────────────────────┴──────────────────────────────────────────────────────────┘
```

---

## Flux 3 — Summarization

```
┌──────────────────────┬──────────────────────────────────────────────────────────┐
│ Composant            │ Action                                                  │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ Frontend             │ 1. Click "Summarize" button                            │
│                      │ 2. POST /ai/summarize (limit: 50)                     │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ MessagingController   │ 3. Check >10 messages                                  │
│                      │ 4. dispatch SummarizeConversationMessage              │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ MessageBus           │ 5. Route vers async                                    │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ SummarizeConversation │ 6. Load last 50 messages (exclude deleted)              │
│ Handler              │ 7. Format: "SenderName: content\n"                     │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ SummarizationService │ 8. Call HuggingFaceClient (bart-large-cnn)             │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ HuggingFaceClient    │ 9. POST to HF API                                      │
│                      │ 10. Return summary_text                                 │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ EntityManager        │ 11. Persist AiConversationSummary                      │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ AuditTrailService    │ 12. record(ai.summary.generated)                        │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ HubInterface         │ 13. Mercure broadcast (user topic)                      │
│                      │    Event: conversation_summary                          │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ Frontend (JS)        │ 14. Listen SSE                                          │
│                      │ 15. Display summary in modal/panel                      │
└──────────────────────┴──────────────────────────────────────────────────────────┘
```

---

## Flux 4 — Recalcul des patterns d'activité

```
┌──────────────────────┬──────────────────────────────────────────────────────────┐
│ Composant            │ Action                                                  │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ Utilisateur          │ 1. Login interactif                                     │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ LoginActivitySubscriber│ 2. onInteractiveLogin() event                         │
│                      │ 3. Update lastLogin, record audit                      │
│                      │ 4. dispatch RecalculateActivityPatternMessage          │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ MessageBus           │ 5. Route vers async                                     │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ RecalculateActivity  │ 6. Load user                                            │
│ PatternHandler       │ 7. Query AuditTrail (last 30 days)                      │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ ActivityPatternService│ 8. Group by hour (user timezone)                        │
│                      │ 9. Count per hour                                       │
│                      │ 10. Sort and get top 3 peak hours                       │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ EntityManager        │ 11. Persist UserActivityPattern                         │
├──────────────────────┼──────────────────────────────────────────────────────────┤
│ AuditTrailService    │ 12. record(sms.pattern.recalculated)                     │
└──────────────────────┴──────────────────────────────────────────────────────────┘
```

---

# SECTION 6 — Guide d'installation et de configuration

## 6.1 Prérequis

| Logiciel | Version minimale |
|----------|------------------|
| PHP | 8.2+ |
| PostgreSQL | 14+ |
| Composer | 2.x |
| Docker | Latest |
| Symfony CLI | Latest |

---

## 6.2 Installation du projet

```bash
# 1. Cloner le projet
git clone git@github.com:homebalance/homebalance.git
cd homebalance

# 2. Installer les dépendances
composer install

# 3. Installer les assets
npm install
```

---

## 6.3 Configuration du fichier .env

### Variables Base de données

```bash
# .env.dev (développement)
DATABASE_URL="postgresql://app:app@127.0.0.1:5432/homebalance?serverVersion=14"
```

### Variables Mercure

```bash
# .env.dev
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
```

### Variables Hugging Face (Phase 4)

```bash
# .env.dev
HUGGINGFACE_API_TOKEN=hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Obtention du token** :
1. Se connecter sur https://huggingface.co
2. Settings → Access Tokens
3. Créer un nouveau token avec permission "Read"

### Variables Twilio (Phase 5)

```bash
# .env.dev
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM_NUMBER=+1234567890
APP_URL=http://localhost
```

**Obtention des credentials Twilio** :
1. Créer un compte trial sur https://www.twilio.com
2. Récupérer Account SID et Auth Token dans la console
3. Vérifier le numéro From dans la console (numéro trial)

---

## 6.4 Lancer les migrations Doctrine

```bash
# Appliquer toutes les migrations
php bin/console doctrine:migrations:migrate

# Vérifier le statut
php bin/console doctrine:migrations:status
```

---

## 6.5 Démarrer les services

### Démarrer Mercure (Docker)

```bash
# Via Docker Compose
docker-compose up -d mercure

# Ou via Symfony
symfony server:mercure
```

### Démarrer le worker Messenger

```bash
# Mode développement (sync)
php bin/console messenger:consume async -vv

# Production
php bin/console messenger:consume async --time-limit=3600
```

### Démarrer le serveur web

```bash
# Développement
symfony serve

# Ou via Docker
docker-compose up -d php nginx
```

---

## 6.6 Checklist de vérification

### Phase 1 — Mercure temps réel

- [ ] Mercure hub démarré sur port 3000
- [ ] Token JWT configuré dans .env
- [ ] Events SSE reçus côté frontend
- [ ] Topic subscribe fonctionne

### Phase 3 — Fonctionnalités avancées

- [ ] Réactions ajoutables
- [ ] Threading fonctionnel
- [ ] Accusés de lecture OK
- [ ] Édition message OK
- [ ] Suppression douce OK
- [ ] Épinglage OK (max 3)
- [ ] Galerie OK

### Phase 4 — Intelligence Artificielle

- [ ] Token Hugging Face configuré
- [ ] Model blenderbot chargé (20s premiere requete)
- [ ] Model bart-large-cnn chargé
- [ ] Smart replies retournés
- [ ] Summary retourné
- [ ] Async processing OK

### Phase 5 — SMS Fallback

- [ ] Credentials Twilio configurés
- [ ] Numéro From vérifié
- [ ] Ping présence fonctionne
- [ ] SMS envoyé (test offline)
- [ ] Quiet hours respecté
- [ ] Smart timing fonctionnel

---

## 6.7 Erreurs fréquentes et solutions

### Erreur : "Connection refused" Mercure

**Cause** : Hub Mercure pas démarré

**Solution** :
```bash
docker-compose up -d mercure
```

### Erreur : "Model loading" Hugging Face (503)

**Cause** : Model pas encore chargé en mémoire

**Solution** : Attendre 20-30 secondes, réessayer (le handler fait retry automatiquement)

### Erreur : "Twilio 21611" (Invalid Phone Number)

**Cause** : Format numéro incorrect

**Solution** : Utiliser format E.164 (ex: +33123456789)

### Erreur : "Messenger transport failed"

**Cause** : Database pas prête ou transport mal configuré

**Solution** :
```bash
php bin/console doctrine:schema:update --force
php bin/console messenger:setup-transports
```

### Erreur : "JWT Token invalid"

**Cause** : Secret Mercure pas configuré ou expiré

**Solution** : Vérifier MERCURE_JWT_SECRET dans .env

---

*Fin du document — Version 1.0 — 24 février 2026*
