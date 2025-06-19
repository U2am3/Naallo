ALTER TABLE users ADD COLUMN reset_token VARCHAR(64);
ALTER TABLE users ADD COLUMN reset_token_expires DATETIME;
