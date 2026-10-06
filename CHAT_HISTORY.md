# Chat History

Selecting any category or sending from the dashboard starts a new Ben conversation.
Opening a history entry restores only that conversation. Multiple chats in the
same category remain separate; starting a chat does not erase or save over the old
one. Chats are saved when a message is sent, and the latest 100 saved conversations
are shown in history. The history search and mobile history button use that list.

For an existing deployment, apply
`database/migrations/20261002_allow_multiple_chat_sessions.sql` after
`20261002_add_chat_sessions.sql`. It preserves existing messages and conversation
IDs, adds stable keys, and replaces the single-conversation-per-category index.
It is safe to run again. This local database has already been migrated.

Run `php tests/regression.php`, `php tests/chat_sessions_regression.php`, and
`node tests/chat_regression.cjs` for the relevant regression checks.
