CREATE TABLE IF NOT EXISTS glpi_plugin_editcontrol_leases (
 tickets_id int unsigned NOT NULL PRIMARY KEY,
 generation bigint unsigned NOT NULL DEFAULT 0,
 revision bigint unsigned NOT NULL DEFAULT 0,
 users_id int unsigned NOT NULL DEFAULT 0,
 token_hash char(64) NOT NULL DEFAULT '',
 session_hash char(64) NOT NULL DEFAULT '',
 started bigint NOT NULL DEFAULT 0,
 heartbeat bigint NOT NULL DEFAULT 0,
 expires bigint NOT NULL DEFAULT 0,
 requester int unsigned NOT NULL DEFAULT 0,
 requested bigint NOT NULL DEFAULT 0,
 requester_token_hash char(64) NOT NULL DEFAULT '',
 requester_session_hash char(64) NOT NULL DEFAULT '',
 KEY expires (expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS glpi_plugin_editcontrol_events (
 id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tickets_id int unsigned NOT NULL,
 users_id int unsigned NOT NULL,
 previous_users_id int unsigned NOT NULL DEFAULT 0,
 event varchar(40) NOT NULL,
 occurred bigint NOT NULL,
 KEY ticket_date (tickets_id, occurred)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
