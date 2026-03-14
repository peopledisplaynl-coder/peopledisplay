# SCHEMA_COMPARISON.md — Strato Production vs NAS Install

**Strato source:** `dbs15005199.txt` (27 tables)
**NAS install source:** `peopledisplay_1.txt` (15 tables)

---

## Summary

| Category | Count |
|----------|-------|
| Tables in Strato only (need to ADD) | 13 |
| Tables in NAS only (email_log) | 1 |
| Tables in both (need column comparison) | 14 |

---

## Section 1: Tables to ADD (in Strato, missing from NAS install)

### 1. `admins`
```
id            int(11) NOT NULL AUTO_INCREMENT PK
username      varchar(255) NOT NULL UNIQUE
password_hash varchar(255) NOT NULL
created_at    timestamp NOT NULL DEFAULT current_timestamp()
```
**Note:** Legacy admin table. Current system uses `users` table with role=superadmin. Likely a v1 artifact kept for backward compatibility.

---

### 2. `button_config`
```
id          int(11) NOT NULL AUTO_INCREMENT PK
button_key  varchar(20) NOT NULL UNIQUE
name        varchar(50) NOT NULL
color       varchar(20) NULL
enabled     tinyint(1) DEFAULT 1
updated_at  timestamp NULL DEFAULT current_timestamp()
```

---

### 3. `feature_audit`
```
id          int(11) NOT NULL AUTO_INCREMENT PK
user_id     int(11) NOT NULL
feature_key varchar(50) NOT NULL
action      enum('ENABLED','DISABLED','CREATED','DELETED') NOT NULL
changed_by  int(11) NULL
changed_at  timestamp NOT NULL DEFAULT current_timestamp()
```

---

### 4. `filters`
```
id          int(11) NOT NULL AUTO_INCREMENT PK
filter_name varchar(50) NOT NULL UNIQUE
filter_type varchar(50) NULL
options     text NULL
enabled     tinyint(1) DEFAULT 1
created_at  timestamp NOT NULL DEFAULT current_timestamp()
```

---

### 5. `labee_config`
```
id         int(11) NOT NULL AUTO_INCREMENT PK
key_name   varchar(100) NOT NULL UNIQUE
value      text NULL
updated_at timestamp NOT NULL DEFAULT current_timestamp()
```

---

### 6. `notification_settings`
```
id            int(11) NOT NULL AUTO_INCREMENT PK
setting_key   varchar(100) NOT NULL UNIQUE
setting_value text NULL
description   varchar(255) NULL
updated_at    timestamp NOT NULL DEFAULT current_timestamp()
```
**Default rows (from Strato):**
```
('email_enabled','0','Email notificaties aan/uit'),
('smtp_host','','SMTP server'),
('smtp_port','587','SMTP poort'),
('smtp_username','','SMTP gebruikersnaam'),
('smtp_password','','SMTP wachtwoord'),
('smtp_from_email','','Afzender email'),
('smtp_from_name','PeopleDisplay Bezoekers','Afzender naam'),
('notify_on_online','1','Notificeer bij online registratie'),
('notify_on_checkin','1','Notificeer bij check-in')
```

---

### 7. `roles`
```
id          int(11) NOT NULL AUTO_INCREMENT PK
role_name   varchar(50) NOT NULL UNIQUE
description text NULL
created_at  timestamp NOT NULL DEFAULT current_timestamp()
```

---

### 8. `roles_permissions`
```
id         int(11) NOT NULL AUTO_INCREMENT PK
role_id    int(11) NOT NULL
permission varchar(100) NOT NULL
created_at timestamp NOT NULL DEFAULT current_timestamp()
UNIQUE KEY (role_id, permission)
```

---

### 9. `users_roles`
```
id         int(11) NOT NULL AUTO_INCREMENT PK
user_id    int(11) NOT NULL
role_id    int(11) NOT NULL
created_at timestamp NOT NULL DEFAULT current_timestamp()
UNIQUE KEY (user_id, role_id)
```

---

### 10. `user_filters`
```
id           int(11) NOT NULL AUTO_INCREMENT PK
user_id      int(11) NOT NULL
filter_name  varchar(50) NOT NULL
filter_value text NULL
created_at   timestamp NOT NULL DEFAULT current_timestamp()
updated_at   timestamp NOT NULL DEFAULT current_timestamp()
UNIQUE KEY (user_id, filter_name)
```

---

### 11. `user_locations`
```
id          int(11) NOT NULL AUTO_INCREMENT PK
user_id     int(11) NOT NULL
location_id int(11) NOT NULL
created_at  timestamp NOT NULL DEFAULT current_timestamp()
UNIQUE KEY (user_id, location_id)
```

---

### 12. `user_menu`
```
id         int(11) NOT NULL AUTO_INCREMENT PK
user_id    int(11) NOT NULL UNIQUE
menu_items text NULL
updated_at timestamp NOT NULL DEFAULT current_timestamp()
```

---

### 13. `visitors_backup`
```
id                     int(11) NOT NULL DEFAULT 0
visitor_id             varchar(50) NOT NULL
voornaam               varchar(100) NULL
achternaam             varchar(100) NULL
naam                   varchar(150) NOT NULL
functie                varchar(100) NULL
bedrijf                varchar(150) NULL
email                  varchar(255) NULL
telefoon               varchar(50) NULL
bezoek_datum           date NOT NULL
start_date             date NULL
end_date               date NULL
is_multi_day           tinyint(1) DEFAULT 0
privacy_accepted       tinyint(1) DEFAULT 0
privacy_accepted_at    datetime NULL
checkin_token          varchar(64) NULL
checkout_token         varchar(64) NULL
tokens_valid_until     datetime NULL
registration_email_sent tinyint(1) DEFAULT 0
checkin_email_sent     tinyint(1) DEFAULT 0
checkout_email_sent    tinyint(1) DEFAULT 0
bezoek_tijd            time NULL
reden_bezoek           text NULL
locatie                varchar(100) NOT NULL
contactpersoon_id      int(10) NULL
contactpersoon_naam    varchar(150) NULL
status                 enum('AANGEMELD','BINNEN','VERTROKKEN','GEANNULEERD') DEFAULT 'AANGEMELD'
registratie_type       enum('ONLINE','LOCATIE') NOT NULL
checked_in_at          datetime NULL
checked_out_at         datetime NULL
notification_sent      tinyint(1) DEFAULT 0
notification_sent_at   datetime NULL
notities               text NULL
created_by             int(10) NULL
created_at             timestamp NOT NULL DEFAULT current_timestamp()
updated_at             timestamp NOT NULL DEFAULT current_timestamp()
```
**Note:** This is the OLD visitors schema from v1. The current `visitors` table is completely redesigned. This is a backup table used for migration purposes — likely safe to include as empty table for fresh installs.

---

## Section 2: Tables to KEEP as-is (NAS only)

### `email_log`
Not present in Strato. Present in NAS. Keep it — `includes/email_functions.php` or similar may log to it.
```
id         int(11) NOT NULL AUTO_INCREMENT PK
to_email   varchar(255) NOT NULL
subject    varchar(500) NULL
status     varchar(50) DEFAULT 'sent'
created_at datetime DEFAULT current_timestamp()
```

---

## Section 3: Column Differences in Shared Tables

### `afdelingen`
| Column | Strato | NAS install | Recommendation |
|--------|--------|-------------|----------------|
| afdeling_name | varchar(100) | varchar(150) | Use varchar(150) — wider is safer |
| afdeling_code | varchar(20) | varchar(50) | Use varchar(50) — wider is safer |

---

### `config`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| sheetID | text | varchar(255) | Use text to match Strato |
| presentationID | text | varchar(255) | Use text to match Strato |
| name_display_option | enum('volledig','voornaam','achternaam') | enum + 'initiaal_achternaam' | ⚠️ NAS has 4th option; use NAS version if PHP uses it |

---

### `employees`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| id | int(10) | int(11) | Functionally identical |
| employee_id | varchar(50) | varchar(100) | ⚠️ Use varchar(100) — Strato IDs are ~18 chars but 100 is safer |
| voornaam | varchar(75) | varchar(100) | Use varchar(100) |
| achternaam | varchar(75) | varchar(100) | Use varchar(100) |
| naam | varchar(150) | varchar(200) | Use varchar(200) |
| **status** | **enum('IN','OUT')** | **enum('IN','OUT','OVERLEG')** | **⚠️ CRITICAL: PHP code may set status='OVERLEG'. Use NAS version with OVERLEG** |
| sub_status_type | tinyint(1) | varchar(50) | ⚠️ Strato stores integer; NAS stores string. Use varchar(50) — PHP sets string values |
| foto_url | varchar(255) | varchar(500) | Use varchar(500) |
| functie | varchar(100) | varchar(150) | Use varchar(150) |
| afdeling | varchar(100) | varchar(150) | Use varchar(150) |
| updated_at | timestamp NOT NULL | datetime | Use timestamp NOT NULL DEFAULT current_timestamp() |

---

### `employee_audit`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| employee_id | varchar(50) NOT NULL | varchar(100) NULL | Use varchar(100) NULL |
| action | enum('INSERT','UPDATE','DELETE','STATUS_CHANGE') | varchar(100) | Use enum (more strict) OR varchar(100) — PHP currently inserts string values |
| **field_changed** | **varchar(50) NULL** | **MISSING** | **⚠️ Add: PHP audit_log.php displays `$r['field_changed']` (shows '-' if NULL)** |
| **ip_address** | **varchar(45) NULL** | **MISSING** | Add for Strato compatibility |
| **user_agent** | **text NULL** | **MISSING** | Add for Strato compatibility |
| changed_at | timestamp NOT NULL | — | ⚠️ NAS uses `created_at` datetime; Strato uses `changed_at` timestamp. **PHP code was FIXED to use `created_at` in admin/audit_log.php. Keep `created_at` as column name.** |

**Resolution for employee_audit:** Use NAS column name `created_at` (NOT `changed_at`) since PHP code was fixed to match. Add the three missing columns: `field_changed`, `ip_address`, `user_agent`.

---

### `feature_keys`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| **key column name** | `feature_key` varchar(50) UNIQUE | `key_name` varchar(100) UNIQUE | ⚠️ CRITICAL: PHP code (`feature_cache.php`, `admin/features_manage.php`) uses `key_name`. Keep `key_name`. |
| feature_name | varchar(100) NOT NULL | MISSING | Add as optional column |
| default_enabled | tinyint(1) DEFAULT 0 | MISSING | Add column |
| description | text | varchar(255) | Use text |

---

### `kiosk_tokens`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| is_active | tinyint(1) NOT NULL DEFAULT 1 | tinyint(1) DEFAULT 1 | Minor: use NOT NULL |

Essentially identical.

---

### `locations`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| location_name | varchar(100) | varchar(150) | Use varchar(150) |
| **primary_ip** | **varchar(45) NULL** | **MISSING** | **Add — Strato uses separate IP columns** |
| **backup_ip** | **varchar(45) NULL** | **MISSING** | **Add** |
| **ip_range_start** | **varchar(45) NULL** | **MISSING** | **Add** |
| **ip_range_end** | **varchar(45) NULL** | **MISSING** | **Add** |
| **auto_checkin_enabled** | **tinyint(1) DEFAULT 0** | **MISSING** | **Add** |
| ip_range | varchar(255) | present | ⚠️ NAS has single `ip_range` column; Strato splits into 4 columns. Recommendation: keep `ip_range` AND add Strato columns for compatibility. |

---

### `remember_tokens`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| token | varchar(64) | varchar(255) | Use varchar(255) — token could be longer |
| **selector** | **varchar(32) NOT NULL UNIQUE** | **MISSING** | **Add — used in secure remember-me token lookup** |

---

### `users`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| features | longtext | text | Use longtext |
| active | tinyint(1) NOT NULL | tinyint(1) | Use NOT NULL DEFAULT 1 |
| created_at | timestamp NOT NULL | datetime | Use timestamp NOT NULL |
| **last_login** | **MISSING** | **datetime** | **⚠️ KEEP: login.php has `UPDATE users SET last_login=NOW()`. NAS is correct.** |

---

### `user_features`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| **feature_key** | varchar(50) FK to feature_keys.feature_key | **feature_key_id** int FK to feature_keys.id | **⚠️ CRITICAL: NAS PHP code uses `feature_key_id` int FK. Keep NAS structure.** |
| **enabled** | tinyint(1) | **visible** | **⚠️ NAS PHP uses `visible`. Keep `visible`.** |
| created_at | present | MISSING | Add |
| updated_at | present | MISSING | Add |

---

### `user_sessions`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| device | varchar(100) | varchar(50) | Use varchar(100) |
| last_activity | datetime NOT NULL | datetime | Use NOT NULL DEFAULT current_timestamp() |
| login_time | datetime NOT NULL | datetime | Use NOT NULL DEFAULT current_timestamp() |
| page_url | varchar(255) | varchar(500) | Use varchar(500) |

---

### `visitors`
| Column | Strato | NAS install | Notes |
|--------|--------|-------------|-------|
| bedrijf | varchar(255) | varchar(200) | Use varchar(255) |
| locatie | NOT NULL | NULL | Use NOT NULL (Strato) |
| **status** | **enum('AANGEMELD','BINNEN','VERTROKKEN')** | **enum + 'GEANNULEERD'** | **⚠️ NAS has GEANNULEERD. PHP visitor code likely sets GEANNULEERD. Keep 4 values.** |
| **notes** | **MISSING** | **text** | **KEEP — NAS has notes column, may be used by PHP** |
| created_at | timestamp NOT NULL | datetime | Use timestamp NOT NULL |

---

## Section 4: Critical Conflicts Summary

These are cases where Strato and NAS disagree AND PHP code has a specific expectation:

| # | Table | Column | Strato | NAS | PHP Code Uses | Decision |
|---|-------|--------|--------|-----|---------------|----------|
| 1 | employees | status enum | IN, OUT | IN, OUT, OVERLEG | `OVERLEG` is valid status | **Use NAS (3 values)** |
| 2 | employee_audit | timestamp col name | `changed_at` | `created_at` | `created_at` (fixed in audit_log.php) | **Use NAS (`created_at`)** |
| 3 | feature_keys | key column | `feature_key` | `key_name` | `key_name` (feature_cache.php) | **Use NAS (`key_name`)** |
| 4 | user_features | FK type | `feature_key` varchar | `feature_key_id` int | integer FK | **Use NAS (`feature_key_id`)** |
| 5 | user_features | enabled col | `enabled` | `visible` | `visible` | **Use NAS (`visible`)** |
| 6 | users | last_login | MISSING | datetime | `UPDATE SET last_login=NOW()` | **Keep NAS column** |
| 7 | visitors | status enum | 3 values | 4 values (GEANNULEERD) | GEANNULEERD used | **Use NAS (4 values)** |
| 8 | remember_tokens | selector | varchar(32) UNIQUE | MISSING | unknown | **Add from Strato (safe to add)** |

---

## Section 5: Recommended install.sql Strategy

Build the install.sql with:
1. **All 27 Strato tables** (add the 13 missing ones)
2. **Keep email_log** (NAS-only, total = 28 tables)
3. **For shared tables:** use the PHP-compatible version when there's a conflict (see column decisions above)
4. **Use wider varchar sizes** when NAS and Strato differ (wider = safer)
5. **Add missing columns** from Strato to shared tables (field_changed, ip_address, user_agent in employee_audit; selector in remember_tokens; primary_ip etc. in locations)

**Final table count: 28 tables**

---

## AWAIT APPROVAL before building install.sql

Review this comparison and confirm before proceeding.
