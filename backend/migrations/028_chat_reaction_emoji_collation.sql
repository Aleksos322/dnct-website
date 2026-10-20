-- Apply support_message_reactions to both support databases.
-- chat_message_reactions exists in beta (also used by admin direct chat).
-- Legacy unicode_ci treats many distinct emoji as equal, breaking toggles.
ALTER TABLE support_message_reactions MODIFY emoji VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
ALTER TABLE chat_message_reactions MODIFY emoji VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;

-- Closed tickets must not occupy the active-ticket uniqueness slot.
UPDATE support_tickets SET active_owner_key=NULL WHERE status IN ('closed','archived') AND active_owner_key IS NOT NULL;
