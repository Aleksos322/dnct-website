ALTER TABLE chat_group_membership MODIFY state ENUM('active','left','removed','invited','ignored') NOT NULL DEFAULT 'active';
