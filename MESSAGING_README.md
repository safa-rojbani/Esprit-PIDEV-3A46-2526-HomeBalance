# HomeBalance Messaging System

## Overview
A family messaging system built with Symfony 6.4 that works independently of Symfony Security using session-based authentication.

## Features Implemented

### 1. Session-Based User Management
- **Route**: `/messaging/select-user`
- Select any user from the database to act as the "active user"
- User ID is stored in session (`current_user_id`)
- All messaging operations use this session-based user

### 2. Messaging Dashboard
- **Route**: `/messaging`
- Top bar displays:
  - Active user avatar with initials
  - User name and family role
  - Online status indicator
  - Click avatar to open profile panel
- Sidebar shows all conversations for the active user
- Main area shows empty state or selected conversation

### 3. User Profile Panel
- Dropdown panel accessible from top bar
- Displays:
  - User avatar, name, and role
  - Status selection (Online, Away, Do Not Disturb, Offline)
  - Settings toggles (UI only - for future implementation)
- Status updates are saved to session

### 4. Conversation Creation
- **Route**: `/messaging/new`
- Two conversation types:
  - **Private**: One-on-one chat (auto-named with participant's name)
  - **Group**: Multi-user chat (requires custom name)
- Select participants from same family only
- After creation, redirects to conversation view

### 5. Conversation View
- **Route**: `/messaging/conversation/{id}`
- Displays all messages in chronological order
- Messages show:
  - Sender avatar with initials
  - Sender name and timestamp
  - Message content in styled bubbles
  - Different styling for own messages vs others
- Message input form at bottom
- Auto-scrolls to latest message

### 6. Message Sending
- **Route**: `/messaging/conversation/{id}/send` (POST)
- Form submission sends message
- Message saved to database with:
  - Content
  - Sender (active user)
  - Conversation
  - Timestamp
  - Read status (default: false)

## Database Entities

### User
- Has family relation and family role (PARENT/CHILD)
- Stores basic info: name, email, avatar, etc.

### Conversation
- Types: PRIVATE, GROUP, CHAT, SUPPORT
- Has name, type, family, creator, created date

### ConversationParticipant
- Links users to conversations
- Tracks when user joined

### Message
- Content, attachment URL, sent time
- Links to conversation and sender
- Has read status flag

### New Enum: OnlineStatus
- ONLINE
- AWAY
- DO_NOT_DISTURB
- OFFLINE

## Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/messaging/select-user` | GET/POST | Select active user for testing |
| `/messaging` | GET | Main messaging dashboard |
| `/messaging/conversation/{id}` | GET | View specific conversation |
| `/messaging/conversation/{id}/send` | POST | Send message in conversation |
| `/messaging/new` | GET/POST | Create new conversation |
| `/messaging/status/update` | POST | Update user online status |

## How to Use

### 1. Start the Development Server
```bash
symfony server:start
# or
php -S localhost:8000 -t public
```

### 2. Access the Messaging System
1. Navigate to `http://localhost:8000/messaging/select-user`
2. Select a user to act as (this simulates login)
3. Click "Continue to Messaging"

### 3. Create a Conversation
1. Click "➕ New Conversation" button
2. Choose conversation type (Private or Group)
3. For groups, enter a name
4. Select participants from your family
5. Click "Create Conversation"

### 4. Send Messages
1. Click on a conversation in the sidebar
2. Type your message in the input field
3. Click "Send" or press Enter
4. Messages appear in the chat area

### 5. Switch Users (for Testing)
1. Go back to `/messaging/select-user`
2. Select a different user
3. View conversations and messages from their perspective

### 6. Change Status
1. Click on your avatar in the top bar
2. Profile panel opens
3. Select a status (Online, Away, Do Not Disturb, Offline)
4. Status indicator updates

## UI Features

### Modern Design
- Gradient backgrounds
- Smooth animations and transitions
- Hover effects on interactive elements
- Color-coded status indicators
- Responsive layout

### User Experience
- Auto-scroll to latest messages
- Visual feedback on selections
- Empty states with helpful messages
- Form validation
- Flash messages for errors

### Accessibility
- Semantic HTML
- Clear visual hierarchy
- Readable fonts and colors
- Keyboard navigation support

## Technical Details

### Controller: MessagingController
- `getActiveUser()`: Helper to retrieve session-based user
- `selectUser()`: User selection page
- `index()`: Main messaging dashboard
- `show()`: Conversation view with messages
- `sendMessage()`: Handle message submission
- `new()`: Conversation creation
- `updateStatus()`: Update online status

### Session Keys
- `current_user_id`: ID of active user
- `online_status`: Current online status

### Security Notes
- **NO authentication required** (by design for testing)
- All users can access all conversations in their family
- Participant verification ensures users can only view conversations they're part of
- Family-based isolation (users only see family members)

## Future Enhancements
- Real-time updates with Mercure or WebSockets
- Message read receipts
- Typing indicators
- File attachments
- Message search
- Conversation archiving
- Push notifications
- Emoji support
- Message editing/deletion
- User blocking

## Troubleshooting

### "No active user" redirect
- Make sure you've selected a user at `/messaging/select-user`
- Check that session is working properly

### Empty conversations list
- Create a new conversation first
- Ensure users are in the same family

### Messages not appearing
- Check database connection
- Verify message was saved (check database)
- Ensure you're viewing the correct conversation

### Status not updating
- Check browser console for errors
- Verify AJAX request is successful
- Clear cache: `php bin/console cache:clear`

## Development Notes
- Built with Symfony 6.4
- Uses Doctrine ORM for database
- Twig templates for views
- Session-based state management
- No JavaScript framework (vanilla JS)
- Modern CSS with gradients and animations
