-- Buat ekstensi UUID jika belum ada
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Tabel audit_log
CREATE TABLE audit_log (
    id integer NOT NULL,
    user_id integer,
    action character varying(100) NOT NULL,
    table_name character varying(50),
    record_id integer,
    old_value jsonb,
    new_value jsonb,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- Sequence untuk audit_log
CREATE SEQUENCE audit_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

-- Set default value untuk id kolom
ALTER TABLE audit_log ALTER COLUMN id SET DEFAULT nextval('audit_log_id_seq');


-- Tabel backup_history
CREATE TABLE backup_history (
    id integer NOT NULL,
    user_id integer,
    backup_type character varying(20) DEFAULT 'full'::character varying,
    database_name character varying(100) NOT NULL,
    filename character varying(255) NOT NULL,
    file_path text NOT NULL,
    size_bytes bigint,
    status character varying(50) NOT NULL,
    error_message text,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp without time zone,
    tables_list text
);


-- Sequence untuk backup_history
CREATE SEQUENCE backup_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

-- Set default value untuk id kolom
ALTER TABLE backup_history ALTER COLUMN id SET DEFAULT nextval('backup_history_id_seq');


-- Tabel backup_history_temp
CREATE TABLE backup_history_temp (
    id integer NOT NULL,
    user_id integer,
    backup_type character varying(20) DEFAULT 'full'::character varying,
    database_name character varying(100) NOT NULL,
    filename character varying(255) NOT NULL,
    file_path text NOT NULL,
    size_bytes bigint,
    status character varying(50) NOT NULL,
    error_message text,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


-- Sequence untuk backup_history_temp
CREATE SEQUENCE backup_history_temp_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

-- Set default value untuk id kolom
ALTER TABLE backup_history_temp ALTER COLUMN id SET DEFAULT nextval('backup_history_temp_id_seq');


-- Tabel backup_schedules
CREATE TABLE backup_schedules (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    database_name character varying(100) NOT NULL,
    frequency character varying(50) NOT NULL,
    day_of_week character varying(20),
    hour integer NOT NULL,
    minute integer NOT NULL,
    retention_days integer DEFAULT 30,
    compress boolean DEFAULT true,
    include_schema boolean DEFAULT true,
    include_data boolean DEFAULT true,
    is_active boolean DEFAULT true,
    last_run timestamp without time zone,
    next_run timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    backup_type character varying(20) DEFAULT 'full'::character varying,
    selected_tables text
);


-- Sequence untuk backup_schedules
CREATE SEQUENCE backup_schedules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

-- Set default value untuk id kolom
ALTER TABLE backup_schedules ALTER COLUMN id SET DEFAULT nextval('backup_schedules_id_seq');


-- Tabel restore_history
CREATE TABLE restore_history (
    id integer NOT NULL,
    user_id integer,
    backup_id integer,
    status character varying(50) NOT NULL,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


-- Sequence untuk restore_history
CREATE SEQUENCE restore_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

-- Set default value untuk id kolom
ALTER TABLE restore_history ALTER COLUMN id SET DEFAULT nextval('restore_history_id_seq');


-- Tabel settings
CREATE TABLE settings (
    id integer NOT NULL,
    setting_key character varying(50) NOT NULL,
    setting_value text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


-- Sequence untuk settings
CREATE SEQUENCE settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

-- Set default value untuk id kolom
ALTER TABLE settings ALTER COLUMN id SET DEFAULT nextval('settings_id_seq');


-- Tabel users
CREATE TABLE users (
    id integer NOT NULL,
    username character varying(50) NOT NULL,
    password character varying(255) NOT NULL,
    full_name character varying(100),
    email character varying(100),
    is_admin boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


-- Sequence untuk users
CREATE SEQUENCE users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

-- Set default value untuk id kolom
ALTER TABLE users ALTER COLUMN id SET DEFAULT nextval('users_id_seq');


-- Primary keys
ALTER TABLE audit_log ADD CONSTRAINT audit_log_pkey PRIMARY KEY (id);
ALTER TABLE backup_history ADD CONSTRAINT backup_history_pkey PRIMARY KEY (id);
ALTER TABLE backup_history_temp ADD CONSTRAINT backup_history_temp_pkey PRIMARY KEY (id);
ALTER TABLE backup_schedules ADD CONSTRAINT backup_schedules_pkey PRIMARY KEY (id);
ALTER TABLE restore_history ADD CONSTRAINT restore_history_pkey PRIMARY KEY (id);
ALTER TABLE settings ADD CONSTRAINT settings_pkey PRIMARY KEY (id);
ALTER TABLE users ADD CONSTRAINT users_pkey PRIMARY KEY (id);

-- Unique constraints
ALTER TABLE settings ADD CONSTRAINT settings_setting_key_key UNIQUE (setting_key);
ALTER TABLE users ADD CONSTRAINT users_email_key UNIQUE (email);
ALTER TABLE users ADD CONSTRAINT users_username_key UNIQUE (username);


-- Foreign keys
ALTER TABLE audit_log ADD CONSTRAINT audit_log_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE backup_history ADD CONSTRAINT backup_history_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE restore_history ADD CONSTRAINT restore_history_backup_id_fkey 
    FOREIGN KEY (backup_id) REFERENCES backup_history(id) ON DELETE SET NULL;

ALTER TABLE restore_history ADD CONSTRAINT restore_history_user_id_fkey 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;


-- Buat user admin default (password: admin123)
INSERT INTO users (username, password, full_name, email, is_admin, created_at, updated_at)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@example.com', TRUE, NOW(), NOW())
ON CONFLICT (username) DO NOTHING;
CREATE TABLE IF NOT EXISTS audit_log (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INTEGER,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tambahkan pengaturan default
INSERT INTO settings (setting_key, setting_value) VALUES 
('app_name', 'PostgreSQL Backup Manager'),
('app_description', 'Aplikasi manajemen backup database PostgreSQL'),
('items_per_page', '20'),
('backup_path', '/var/backups/postgresql'),
('backup_retention_days', '30'),
('max_backup_files', '10'),
('enable_email_notification', '0'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('from_email', 'noreply@example.com'),
('from_name', 'PostgreSQL Backup Manager'),
('maintenance_mode', '0'),
('enable_registration', '0'),
('default_user_role', 'user'),
('enable_audit_log', '1')
ON CONFLICT (setting_key) DO NOTHING;
