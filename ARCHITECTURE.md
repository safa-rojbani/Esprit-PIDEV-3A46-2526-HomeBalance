# Messaging System Architecture

## System Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    USER ACCESSES SYSTEM                          │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│              /messaging/select-user                              │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  • List all users from database                          │  │
│  │  • User selects one                                      │  │
│  │  • Store user ID in session: current_user_id            │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    /messaging (Dashboard)                        │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  TOP BAR                                                 │  │
│  │  • Active user avatar + name + role                      │  │
│  │  • Status indicator (online/away/dnd/offline)           │  │
│  │  • Click avatar → Profile Panel                         │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌─────────────────┐  ┌──────────────────────────────────────┐ │
│  │   SIDEBAR       │  │      MAIN AREA                       │ │
│  │                 │  │                                      │ │
│  │  Conversations  │  │  Empty State:                        │ │
│  │  List           │  │  "Select or create a conversation"  │ │
│  │                 │  │                                      │ │
│  │  [+ New]        │  │                                      │ │
│  └─────────────────┘  └──────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              ↓
                    (User clicks "New Conversation")
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                /messaging/new (Create Conversation)              │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  1. Select Type: [Private] or [Group]                   │  │
│  │  2. If Group: Enter name                                │  │
│  │  3. Select Participants (from same family)              │  │
│  │  4. Submit → Creates:                                   │  │
│  │     • Conversation entity                               │  │
│  │     • ConversationParticipant for each user             │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
                    (Redirects to conversation)
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│           /messaging/conversation/{id} (Chat View)               │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  CHAT HEADER                                             │  │
│  │  • Conversation name                                     │  │
│  │  • Type (Private/Group)                                  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  MESSAGES AREA (scrollable)                              │  │
│  │                                                          │  │
│  │  [Avatar] John: "Hello!"          10:30                  │  │
│  │                                                          │  │
│  │                  10:35  "Hi there!" :You [Avatar]        │  │
│  │                                                          │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  MESSAGE INPUT                                           │  │
│  │  [Type a message...              ] [Send]                │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

## Database Schema

```
┌─────────────────┐
│     Family      │
│─────────────────│
│ id (PK)         │
│ name            │
│ joinCode        │
│ codeExpiresAt   │
│ createdAt       │
│ createdBy (FK)  │──┐
└─────────────────┘  │
                     │
┌─────────────────┐  │
│      User       │  │
│─────────────────│  │
│ id (PK)         │←─┘
│ email           │
│ firstName       │
│ lastName        │
│ familyRole      │ (PARENT/CHILD)
│ systemRole      │
│ status          │
│ family (FK)     │──→ Family
│ avatarPath      │
│ ...             │
└─────────────────┘
        ↑
        │
        │ createdBy
        │
┌─────────────────┐
│  Conversation   │
│─────────────────│
│ id (PK)         │
│ conversationName│
│ type            │ (PRIVATE/GROUP/CHAT/SUPPORT)
│ createdAt       │
│ family (FK)     │──→ Family
│ createdBy (FK)  │──→ User
└─────────────────┘
        ↑
        │
        │ conversation
        │
┌──────────────────────┐
│ ConversationParticipant │
│──────────────────────│
│ id (PK)              │
│ conversation (FK)    │──→ Conversation
│ user (FK)            │──→ User
│ joinedAt             │
└──────────────────────┘

┌─────────────────┐
│    Message      │
│─────────────────│
│ id (PK)         │
│ content         │
│ attachmentURL   │
│ sentAt          │
│ isRead          │
│ conversation(FK)│──→ Conversation
│ sender (FK)     │──→ User
└─────────────────┘
```

## Controller Routes & Methods

```
MessagingController
├── selectUser()          GET/POST  /messaging/select-user
│   └── Stores user ID in session
│
├── index()               GET       /messaging
│   └── Shows dashboard with conversations list
│
├── show($id)             GET       /messaging/conversation/{id}
│   └── Shows conversation with messages
│
├── sendMessage($id)      POST      /messaging/conversation/{id}/send
│   └── Creates and saves new message
│
├── new()                 GET/POST  /messaging/new
│   └── Creates new conversation with participants
│
└── updateStatus()        POST      /messaging/status/update
    └── Updates online status in session
```

## Session Data

```
Session Keys:
├── current_user_id    → Active user's ID
└── online_status      → User's current status (online/away/dnd/offline)
```

## Component Hierarchy

```
Templates
├── base.html.twig
│
└── messaging/
    ├── select_user.html.twig
    │   └── User selection cards
    │
    ├── index.html.twig
    │   ├── Top bar
    │   ├── Profile panel (partial)
    │   ├── Sidebar (conversations list)
    │   └── Empty state
    │
    ├── show.html.twig
    │   ├── Top bar
    │   ├── Profile panel (partial)
    │   ├── Sidebar (conversations list)
    │   ├── Chat header
    │   ├── Messages container
    │   └── Message input form
    │
    ├── new.html.twig
    │   ├── Top bar
    │   ├── Profile panel (partial)
    │   ├── Sidebar (conversations list)
    │   └── Conversation creation form
    │       ├── Type selector
    │       ├── Group name input
    │       └── Participants selection
    │
    └── partials/
        └── _profile_panel.html.twig
            ├── User info
            ├── Status selector
            └── Settings toggles
```

## Data Flow: Sending a Message

```
1. User types message in input field
   ↓
2. Clicks "Send" button
   ↓
3. Form submits POST to /messaging/conversation/{id}/send
   ↓
4. MessagingController::sendMessage()
   ├── Gets active user from session
   ├── Validates conversation exists
   ├── Creates new Message entity
   │   ├── content = form input
   │   ├── sender = active user
   │   ├── conversation = current conversation
   │   ├── sentAt = now
   │   └── isRead = false
   ├── Persists to database
   └── Redirects back to conversation view
   ↓
5. Conversation page reloads
   ↓
6. Messages query includes new message
   ↓
7. New message appears in chat
   ↓
8. Auto-scroll to bottom
```

## Security Model (Session-Based)

```
┌─────────────────────────────────────────────────────────────┐
│  NO SYMFONY SECURITY COMPONENT                              │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Authentication: Session-based user selection               │
│  Authorization: Family-based isolation                      │
│                                                             │
│  Rules:                                                     │
│  • Users can only see family members                        │
│  • Users can only view conversations they're part of        │
│  • Messages are tied to sender                              │
│  • No password required (testing mode)                      │
└─────────────────────────────────────────────────────────────┘
```

## UI/UX Features

```
Visual Design
├── Color Scheme
│   ├── Primary: Purple gradient (#667eea → #764ba2)
│   ├── Accent: Pink gradient (#f093fb → #f5576c)
│   └── Status colors (green/orange/red/gray)
│
├── Animations
│   ├── Slide down (profile panel)
│   ├── Message slide in
│   ├── Hover effects
│   └── Button transforms
│
└── Layout
    ├── Responsive design
    ├── Fixed top bar
    ├── Scrollable sidebar
    ├── Scrollable messages
    └── Fixed input area
```
