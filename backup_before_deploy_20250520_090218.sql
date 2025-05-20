--
-- PostgreSQL database dump
--

-- Dumped from database version 14.17 (Homebrew)
-- Dumped by pg_dump version 14.17 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: audit_log; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.audit_log (
    id integer NOT NULL,
    user_id integer,
    action character varying(100) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id integer,
    details text,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.audit_log OWNER TO postgres;

--
-- Name: audit_log_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.audit_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.audit_log_id_seq OWNER TO postgres;

--
-- Name: audit_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.audit_log_id_seq OWNED BY public.audit_log.id;


--
-- Name: backup_history; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.backup_history (
    id integer NOT NULL,
    user_id integer,
    filename character varying(255) NOT NULL,
    size_bytes bigint,
    status character varying(50) NOT NULL,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.backup_history OWNER TO postgres;

--
-- Name: backup_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.backup_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.backup_history_id_seq OWNER TO postgres;

--
-- Name: backup_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.backup_history_id_seq OWNED BY public.backup_history.id;


--
-- Name: backup_history_temp; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.backup_history_temp (
    id integer NOT NULL,
    user_id integer NOT NULL,
    filename character varying(255) NOT NULL,
    size_bytes bigint,
    status character varying(50) NOT NULL,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.backup_history_temp OWNER TO postgres;

--
-- Name: backup_history_temp_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.backup_history_temp_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.backup_history_temp_id_seq OWNER TO postgres;

--
-- Name: backup_history_temp_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.backup_history_temp_id_seq OWNED BY public.backup_history_temp.id;


--
-- Name: backup_schedules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.backup_schedules (
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


ALTER TABLE public.backup_schedules OWNER TO postgres;

--
-- Name: backup_schedules_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.backup_schedules_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.backup_schedules_id_seq OWNER TO postgres;

--
-- Name: backup_schedules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.backup_schedules_id_seq OWNED BY public.backup_schedules.id;


--
-- Name: restore_history; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.restore_history (
    id integer NOT NULL,
    user_id integer,
    backup_id integer,
    status character varying(50) NOT NULL,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.restore_history OWNER TO postgres;

--
-- Name: restore_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.restore_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.restore_history_id_seq OWNER TO postgres;

--
-- Name: restore_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.restore_history_id_seq OWNED BY public.restore_history.id;


--
-- Name: settings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.settings (
    id integer NOT NULL,
    setting_key character varying(50) NOT NULL,
    setting_value text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.settings OWNER TO postgres;

--
-- Name: settings_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.settings_id_seq OWNER TO postgres;

--
-- Name: settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.settings_id_seq OWNED BY public.settings.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    username character varying(50) NOT NULL,
    password character varying(255) NOT NULL,
    full_name character varying(100),
    email character varying(100),
    is_admin boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: audit_log id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_log ALTER COLUMN id SET DEFAULT nextval('public.audit_log_id_seq'::regclass);


--
-- Name: backup_history id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.backup_history ALTER COLUMN id SET DEFAULT nextval('public.backup_history_id_seq'::regclass);


--
-- Name: backup_history_temp id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.backup_history_temp ALTER COLUMN id SET DEFAULT nextval('public.backup_history_temp_id_seq'::regclass);


--
-- Name: backup_schedules id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.backup_schedules ALTER COLUMN id SET DEFAULT nextval('public.backup_schedules_id_seq'::regclass);


--
-- Name: restore_history id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restore_history ALTER COLUMN id SET DEFAULT nextval('public.restore_history_id_seq'::regclass);


--
-- Name: settings id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settings ALTER COLUMN id SET DEFAULT nextval('public.settings_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: audit_log; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.audit_log (id, user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at) FROM stdin;
1	1	create	backup	16	Backup dibuat: backup_test_20250519_111442.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:14:43.740299
2	1	create	backup	17	Backup dibuat: backup_test_20250519_111620.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:16:21.364432
3	1	create	backup	18	Backup dibuat: backup_test_20250519_111724.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:17:25.451735
4	1	create	backup	19	Backup dibuat: backup_test_20250519_111906.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:19:06.838323
5	1	create	backup	20	Backup dibuat: backup_test_20250519_112130.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:21:30.922845
6	1	create	backup	21	Backup dibuat: backup_tracker_backup_20250519_112327.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:23:29.169914
7	1	create	backup	22	Backup dibuat: backup_jpos_20250519_112742.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:27:44.747075
8	1	create	backup	23	Backup dibuat: backup_tes_20250519_113942.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:39:43.417078
9	1	create	backup	24	Backup dibuat: backup_sss_20250519_114058.sql (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:40:59.820076
10	1	create	backup	25	Backup dibuat: backup_test_20250519_114327.sql (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:43:28.621811
11	1	create	backup	26	Backup dibuat: backup_tets_20250519_114356.sql (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:43:57.431574
12	1	create	backup	27	Backup dibuat: backup_test_20250519_114611.sql (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:46:12.289758
13	1	create	backup	28	Backup dibuat: backup_ssss_20250519_114818.sql (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:48:20.263069
14	1	create	backup	29	Backup dibuat: backup_hhhh_20250519_115116.sql (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 11:51:17.078722
15	1	create	backup	30	Backup dibuat: backup_ssss_20250519_132749.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:27:50.487976
16	1	create	backup	31	Backup dibuat: backup_ssss_20250519_132805.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:28:06.214732
17	1	create	backup	32	Backup dibuat: backup_ssss_20250519_132832.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:28:32.499808
18	1	create	backup	33	Backup dibuat: backup_sss_20250519_133135.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:31:36.113759
19	1	create	backup	34	Backup dibuat: backup_sss_20250519_133619.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:36:20.261174
20	1	create	backup	35	Backup dibuat: backup_ssss_20250519_133914.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:39:14.505418
21	1	create	backup	36	Backup dibuat: backup_ssss_20250519_133956.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:39:56.931479
22	1	create	backup	37	Backup dibuat: backup_xxx_20250519_134004.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:40:05.167464
23	1	create	backup	38	Backup dibuat: backup_sss_20250519_134233.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:42:33.739951
24	1	create	backup	39	Backup dibuat: backup_aaa_20250519_134242.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 13:42:43.159966
25	1	create	backup	40	Backup dibuat: backup_sss_20250519_154552.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 15:45:53.093123
26	1	create	backup	41	Backup dibuat: backup_aaaa_20250519_154614.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 15:46:15.134984
27	1	create	backup	42	Backup dibuat: backup_test_20250519_163101.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 16:31:01.963656
28	1	create	backup	43	Backup dibuat: backup_klinik_mata_20250519_172935.sql.gz (success)	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 17:29:36.697861
29	1	create	restore	1	Restore dilakukan: success	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 17:34:06.64436
30	1	create	restore	2	Restore dilakukan: success	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 17:37:26.28148
31	1	create	backup	44	Backup dibuat: klinik_mata_2025-05-19_17-59-43.sql.gz (success)	0.0.0.0		2025-05-19 17:59:43.867505
32	1	create	restore	3	Restore dilakukan: success	::1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	2025-05-19 18:09:20.793347
\.


--
-- Data for Name: backup_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.backup_history (id, user_id, filename, size_bytes, status, notes, created_at) FROM stdin;
\.


--
-- Data for Name: backup_history_temp; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.backup_history_temp (id, user_id, filename, size_bytes, status, notes, created_at) FROM stdin;
\.


--
-- Data for Name: backup_schedules; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.backup_schedules (id, name, database_name, frequency, day_of_week, hour, minute, retention_days, compress, include_schema, include_data, is_active, last_run, next_run, created_at, updated_at, backup_type, selected_tables) FROM stdin;
3	klinik	klinik_mata	daily	monday	18	24	30	t	t	t	t	\N	2025-05-19 18:24:00	2025-05-19 18:22:11.349996	2025-05-19 18:22:11.349996	tables	["users"]
\.


--
-- Data for Name: restore_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.restore_history (id, user_id, backup_id, status, notes, created_at) FROM stdin;
1	1	\N	success	Restore berhasil dari backup_klinik_mata_20250519_172935.sql.gz	2025-05-19 17:34:06.640679
2	1	\N	success	Restore berhasil dari backup_klinik_mata_20250519_172935.sql.gz	2025-05-19 17:37:26.277981
3	1	\N	success	Restore berhasil dari klinik_mata_2025-05-19_17-59-43.sql.gz	2025-05-19 18:09:20.790593
\.


--
-- Data for Name: settings; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.settings (id, setting_key, setting_value, created_at, updated_at) FROM stdin;
1	app_name	PostgreSQL Backup Manager	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
2	app_description	Aplikasi manajemen backup database PostgreSQL	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
3	items_per_page	20	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
4	backup_path	/var/backups/postgresql	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
5	backup_retention_days	30	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
6	max_backup_files	10	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
7	enable_email_notification	0	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
8	smtp_host		2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
9	smtp_port	587	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
10	smtp_username		2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
11	smtp_password		2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
12	smtp_encryption	tls	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
13	from_email	noreply@example.com	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
14	from_name	PostgreSQL Backup Manager	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
15	maintenance_mode	0	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
16	enable_registration	0	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
17	default_user_role	user	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
18	enable_audit_log	1	2025-05-19 10:50:09.890387	2025-05-19 10:50:09.890387
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, username, password, full_name, email, is_admin, created_at, updated_at) FROM stdin;
1	admin	0192023a7bbd73250516f069df18b500	Administrator	admin@example.com	t	2025-05-19 09:11:56.985212	2025-05-19 17:27:17.182899
\.


--
-- Name: audit_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.audit_log_id_seq', 32, true);


--
-- Name: backup_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.backup_history_id_seq', 44, true);


--
-- Name: backup_history_temp_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.backup_history_temp_id_seq', 1, false);


--
-- Name: backup_schedules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.backup_schedules_id_seq', 3, true);


--
-- Name: restore_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.restore_history_id_seq', 3, true);


--
-- Name: settings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.settings_id_seq', 18, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 1, true);


--
-- Name: audit_log audit_log_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_log
    ADD CONSTRAINT audit_log_pkey PRIMARY KEY (id);


--
-- Name: backup_history backup_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.backup_history
    ADD CONSTRAINT backup_history_pkey PRIMARY KEY (id);


--
-- Name: backup_history_temp backup_history_temp_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.backup_history_temp
    ADD CONSTRAINT backup_history_temp_pkey PRIMARY KEY (id);


--
-- Name: backup_schedules backup_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.backup_schedules
    ADD CONSTRAINT backup_schedules_pkey PRIMARY KEY (id);


--
-- Name: restore_history restore_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restore_history
    ADD CONSTRAINT restore_history_pkey PRIMARY KEY (id);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (id);


--
-- Name: settings settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_setting_key_key UNIQUE (setting_key);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- Name: audit_log audit_log_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_log
    ADD CONSTRAINT audit_log_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: backup_history backup_history_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.backup_history
    ADD CONSTRAINT backup_history_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: restore_history restore_history_backup_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restore_history
    ADD CONSTRAINT restore_history_backup_id_fkey FOREIGN KEY (backup_id) REFERENCES public.backup_history(id) ON DELETE SET NULL;


--
-- Name: restore_history restore_history_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restore_history
    ADD CONSTRAINT restore_history_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

