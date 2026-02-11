# HomeBalance Messaging System - Quick Start Guide

## 🎯 What Was Built

A complete family messaging system for Symfony 6.4 with:
- ✅ Session-based user selection (no login required)
- ✅ Modern chat interface with conversations sidebar
- ✅ Private and group conversations
- ✅ Real-time message sending and display
- ✅ User profile panel with status selection
- ✅ Beautiful, modern UI with gradients and animations

## 📁 Files Created

### Controllers
- `src/Controller/MessagingController.php` - Main messaging controller

### Entities & Enums
- `src/Enum/OnlineStatus.php` - Online status enum (Online, Away, Do Not Disturb, Offline)
- Updated `src/Enum/TypeConversation.php` - Added PRIVATE and GROUP types

### Templates
- `templates/messaging/select_user.html.twig` - User selection page
- `templates/messaging/index.html.twig` - Main messaging dashboard
- `templates/messaging/show.html.twig` - Conversation view with messages
- `templates/messaging/new.html.twig` - Create new conversation
- `templates/messaging/partials/_profile_panel.html.twig` - User profile dropdown

### Fixtures (Optional)
- `src/DataFixtures/MessagingTestFixtures.php` - Test data (1 family, 4 users)

### Documentation
- `MESSAGING_README.md` - Complete documentation

## 🚀 How to Test

### Step 1: Ensure Database is Set Up
```bash
# Check if migrations are up to date
php bin/console doctrine:migrations:status

# If needed, run migrations
php bin/console doctrine:migrations:migrate
```

### Step 2: Load Test Data (Optional)
If you want test users:
```bash
php bin/console doctrine:fixtures:load --append
```

This creates:
- **John Doe** (Parent) - parent@test.com
- **Jane Doe** (Parent) - parent2@test.com
- **Alice Doe** (Child) - child1@test.com
- **Bob Doe** (Child) - child2@test.com

All passwords: `password`

### Step 3: Start the Server
```bash
symfony server:start
# or
php -S localhost:8000 -t public
```

### Step 4: Access the Messaging System
1. Open browser: `http://localhost:8000/messaging/select-user`
2. Select a user (e.g., John Doe)
3. Click "Continue to Messaging"

### Step 5: Create a Conversation
1. Click "➕ New Conversation"
2. Choose "Private Chat" or "Group Chat"
3. Select participants from your family
4. For groups, enter a name
5. Click "Create Conversation"

### Step 6: Send Messages
1. Click on the conversation in the sidebar
2. Type a message in the input field
3. Click "Send"

### Step 7: Test Multi-User (Switch Users)
1. Go to `/messaging/select-user`
2. Select a different user (e.g., Alice Doe)
3. View the same conversation from their perspective
4. Send messages as the new user

## 🎨 Features to Try

### User Profile Panel
- Click your avatar in the top bar
- Change your status (Online, Away, Do Not Disturb, Offline)
- See the status indicator update

### Conversation Types
- **Private Chat**: One-on-one with another family member
- **Group Chat**: Multiple participants with custom name

### Message Display
- Your messages appear on the right (purple gradient)
- Others' messages appear on the left (white)
- Timestamps show when messages were sent
- Auto-scrolls to latest message

## 📊 Routes Available

| URL | Description |
|-----|-------------|
| `/messaging/select-user` | Select active user |
| `/messaging` | Main dashboard |
| `/messaging/conversation/{id}` | View conversation |
| `/messaging/new` | Create conversation |
| `/messaging/conversation/{id}/send` | Send message (POST) |
| `/messaging/status/update` | Update status (POST) |

## 🔧 Troubleshooting

### "No active user" - Redirects to select-user
**Solution**: Select a user at `/messaging/select-user`

### No conversations showing
**Solution**: Create a new conversation first

### Can't see other family members
**Solution**: Ensure users are in the same family in the database

### Routes not found
**Solution**: Clear cache
```bash
php bin/console cache:clear
```

## 💡 Next Steps

You can enhance the system with:
1. **Real-time updates** - Add Mercure or WebSockets
2. **Read receipts** - Mark messages as read
3. **Typing indicators** - Show when someone is typing
4. **File uploads** - Allow attachments
5. **Message reactions** - Add emoji reactions
6. **Search** - Search messages and conversations
7. **Notifications** - Browser notifications for new messages

## 📝 Notes

- This system works **without Symfony Security** by design
- Session-based user management for easy testing
- All users in the same family can message each other
- Messages persist in the database
- Modern, responsive UI with smooth animations

## ✅ Testing Checklist

- [ ] Select a user
- [ ] View empty messaging dashboard
- [ ] Create a private conversation
- [ ] Send messages in private chat
- [ ] Create a group conversation
- [ ] Send messages in group chat
- [ ] Switch to another user
- [ ] View same conversation as different user
- [ ] Change online status
- [ ] Verify status indicator updates

Enjoy your new messaging system! 🎉
