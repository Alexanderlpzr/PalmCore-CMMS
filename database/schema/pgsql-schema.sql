--
-- PostgreSQL database dump
--

\restrict PKMgx9UGXkgVZx1OhEi944hXfqA0o3ofUtbohUiFfMaDGisexBzenBejQTv4pRr

-- Dumped from database version 18.4
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_locations (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    user_id uuid NOT NULL,
    activity_type character varying(30) NOT NULL,
    activity_id uuid NOT NULL,
    latitude numeric(10,8) NOT NULL,
    longitude numeric(11,8) NOT NULL,
    accuracy numeric(6,2) NOT NULL,
    source character varying(20) DEFAULT 'unknown'::character varying NOT NULL,
    is_low_accuracy boolean DEFAULT false NOT NULL,
    captured_at timestamp(0) with time zone NOT NULL,
    created_at timestamp(0) with time zone NOT NULL
);


--
-- Name: alerts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.alerts (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    severity character varying(20) NOT NULL,
    category character varying(30) NOT NULL,
    title character varying(150) NOT NULL,
    message text,
    entity_type character varying(50),
    entity_id uuid,
    status character varying(20) DEFAULT 'open'::character varying NOT NULL,
    closed_at timestamp(0) with time zone,
    closed_by uuid,
    metadata jsonb,
    created_at timestamp(0) with time zone NOT NULL,
    deleted_at timestamp(0) with time zone
);


--
-- Name: api_request_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.api_request_logs (
    id uuid NOT NULL,
    tenant_id uuid,
    user_id uuid,
    token_id bigint,
    method character varying(10) NOT NULL,
    path character varying(512) NOT NULL,
    status_code smallint NOT NULL,
    duration_ms integer NOT NULL,
    ip_address character varying(45) NOT NULL,
    user_agent character varying(500),
    created_at timestamp(0) with time zone NOT NULL
);


--
-- Name: areas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.areas (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    plant_id uuid NOT NULL,
    code character varying(30) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: automation_rule_executions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.automation_rule_executions (
    id uuid NOT NULL,
    rule_id uuid NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id uuid NOT NULL,
    action_taken character varying(80) NOT NULL,
    metadata jsonb,
    executed_at timestamp(0) with time zone NOT NULL,
    created_at timestamp(0) with time zone NOT NULL
);


--
-- Name: automation_rules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.automation_rules (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    name character varying(100) NOT NULL,
    event_type character varying(50) NOT NULL,
    mode character varying(20) DEFAULT 'disabled'::character varying NOT NULL,
    is_active boolean DEFAULT false NOT NULL,
    configuration jsonb,
    last_executed_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: equipment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    plant_id uuid NOT NULL,
    area_id uuid NOT NULL,
    category_id uuid,
    manufacturer_id uuid,
    supplier_id uuid,
    parent_equipment_id uuid,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    model character varying(255),
    serial_number character varying(255),
    asset_tag character varying(100),
    status character varying(30) DEFAULT 'active'::character varying NOT NULL,
    criticality character varying(20) DEFAULT 'medium'::character varying NOT NULL,
    priority character varying(5) DEFAULT 'p3'::character varying NOT NULL,
    purchase_date date,
    installation_date date,
    commissioning_date date,
    warranty_expiry_date date,
    useful_life_years numeric(5,2),
    purchase_price numeric(15,2),
    replacement_cost numeric(15,2),
    currency_code character(3) DEFAULT 'USD'::bpchar NOT NULL,
    location_notes character varying(500),
    technical_specs jsonb,
    notes text,
    is_active boolean DEFAULT true NOT NULL,
    retired_at timestamp(0) with time zone,
    retired_reason text,
    created_by uuid,
    updated_by uuid,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    current_meter_reading numeric(10,1),
    meter_unit character varying(20) DEFAULT 'hours'::character varying,
    last_failure_at timestamp(0) with time zone
);


--
-- Name: equipment_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_categories (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    parent_id uuid,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    icon character varying(100),
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    is_component_type boolean DEFAULT false NOT NULL,
    color character varying(20)
);


--
-- Name: equipment_documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_documents (
    id uuid NOT NULL,
    equipment_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    document_type character varying(50) NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    file_path character varying(500) NOT NULL,
    file_name character varying(255) NOT NULL,
    file_size bigint,
    mime_type character varying(100),
    version character varying(50),
    expires_at date,
    is_active boolean DEFAULT true NOT NULL,
    uploaded_by uuid,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: equipment_downtime_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_downtime_events (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    equipment_id uuid NOT NULL,
    work_order_id uuid,
    work_order_number character varying(50),
    started_at timestamp(0) with time zone NOT NULL,
    ended_at timestamp(0) with time zone,
    duration_minutes integer,
    cause_type character varying(30) NOT NULL,
    was_planned boolean DEFAULT false NOT NULL,
    failure_mode character varying(255),
    notes text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: equipment_issue_reports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_issue_reports (
    id uuid NOT NULL,
    equipment_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    qr_code_id uuid,
    description text NOT NULL,
    severity character varying(20) DEFAULT 'medium'::character varying NOT NULL,
    reporter_name character varying(255),
    reporter_phone character varying(50),
    reporter_user_id uuid,
    status character varying(20) DEFAULT 'open'::character varying NOT NULL,
    acknowledged_at timestamp(0) with time zone,
    acknowledged_by uuid,
    admin_notes text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    maintenance_request_id uuid
);


--
-- Name: equipment_kpis; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_kpis (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    equipment_id uuid NOT NULL,
    period_months smallint DEFAULT '12'::smallint NOT NULL,
    period_start date NOT NULL,
    period_end date NOT NULL,
    mtbf_hours numeric(10,2),
    mttr_hours numeric(10,2),
    unplanned_availability_percentage numeric(5,2),
    availability_percentage numeric(5,2),
    failure_count integer DEFAULT 0 NOT NULL,
    downtime_hours numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    last_failure_at timestamp(0) with time zone,
    last_calculated_at timestamp(0) with time zone NOT NULL,
    is_stale boolean DEFAULT true NOT NULL,
    deleted_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: equipment_meter_readings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_meter_readings (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    equipment_id uuid NOT NULL,
    reading_value numeric(10,1) NOT NULL,
    reading_unit character varying(20) DEFAULT 'hours'::character varying NOT NULL,
    recorded_at timestamp(0) with time zone NOT NULL,
    recorded_by uuid NOT NULL,
    notes text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: equipment_photos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_photos (
    id uuid NOT NULL,
    equipment_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    file_path character varying(500) NOT NULL,
    file_name character varying(255) NOT NULL,
    file_size bigint,
    mime_type character varying(100),
    caption character varying(500),
    is_primary boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    uploaded_by uuid,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: equipment_qr_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_qr_codes (
    id uuid NOT NULL,
    equipment_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    qr_token uuid NOT NULL,
    qr_image_path character varying(500),
    is_active boolean DEFAULT true NOT NULL,
    generated_at timestamp(0) with time zone,
    last_scanned_at timestamp(0) with time zone,
    scan_count bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: idempotency_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.idempotency_keys (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    user_id uuid NOT NULL,
    idempotency_key character varying(255) NOT NULL,
    request_fingerprint character varying(64) NOT NULL,
    response_status smallint NOT NULL,
    response_body jsonb NOT NULL,
    expires_at timestamp(0) with time zone NOT NULL,
    created_at timestamp(0) with time zone NOT NULL
);


--
-- Name: inventory_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventory_transactions (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    warehouse_id uuid NOT NULL,
    spare_part_id uuid NOT NULL,
    warehouse_spare_part_id uuid NOT NULL,
    source_warehouse_id uuid,
    destination_warehouse_id uuid,
    work_order_id uuid,
    work_order_part_id uuid,
    transaction_number character varying(30) NOT NULL,
    type character varying(20) NOT NULL,
    quantity numeric(12,4) NOT NULL,
    unit_cost numeric(12,4) NOT NULL,
    total_cost numeric(14,4),
    previous_stock numeric(12,4) NOT NULL,
    new_stock numeric(12,4) NOT NULL,
    spare_part_code_snapshot character varying(50) NOT NULL,
    spare_part_name_snapshot character varying(255) NOT NULL,
    reference_number character varying(100),
    notes text,
    performed_by uuid NOT NULL,
    performed_at timestamp(0) with time zone NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    related_transaction_id uuid
);


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: maintenance_checklist_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_checklist_items (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    maintenance_plan_task_id uuid NOT NULL,
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    label character varying(500) NOT NULL,
    item_type character varying(20) NOT NULL,
    unit character varying(30),
    expected_min numeric(10,3),
    expected_max numeric(10,3),
    is_required boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: maintenance_plan_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_plan_attachments (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    maintenance_plan_id uuid NOT NULL,
    attachment_label character varying(100),
    file_path character varying(500) NOT NULL,
    file_name character varying(255) NOT NULL,
    file_size bigint,
    mime_type character varying(100),
    uploaded_by uuid NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: maintenance_plan_tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_plan_tasks (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    maintenance_plan_id uuid NOT NULL,
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    estimated_minutes smallint,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: maintenance_plans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_plans (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    equipment_id uuid NOT NULL,
    plan_number character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    responsible_user_id uuid,
    trigger_source character varying(20) NOT NULL,
    time_frequency character varying(20),
    meter_interval integer,
    cadence_mode character varying(20) DEFAULT 'fixed'::character varying NOT NULL,
    pause_when_equipment_inactive boolean DEFAULT false NOT NULL,
    grace_period_days smallint,
    grace_meter_hours integer,
    estimated_duration_minutes smallint,
    is_active boolean DEFAULT true NOT NULL,
    last_generated_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: maintenance_request_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_request_attachments (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    maintenance_request_id uuid NOT NULL,
    file_path character varying(500) NOT NULL,
    file_name character varying(255) NOT NULL,
    file_size bigint NOT NULL,
    mime_type character varying(100) NOT NULL,
    caption character varying(500),
    uploaded_by uuid NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: maintenance_request_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_request_comments (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    maintenance_request_id uuid NOT NULL,
    user_id uuid NOT NULL,
    body text NOT NULL,
    is_internal boolean DEFAULT false NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: maintenance_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_requests (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    request_number character varying(20) NOT NULL,
    issue_report_id uuid,
    equipment_id uuid NOT NULL,
    request_type character varying(30) NOT NULL,
    priority character varying(20) NOT NULL,
    status character varying(30) NOT NULL,
    title character varying(255) NOT NULL,
    description text NOT NULL,
    requested_due_date date,
    rejection_reason text,
    created_by uuid NOT NULL,
    assigned_reviewer uuid,
    approved_by uuid,
    rejected_by uuid,
    submitted_at timestamp(0) with time zone,
    reviewed_at timestamp(0) with time zone,
    approved_at timestamp(0) with time zone,
    rejected_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    work_order_id uuid,
    preliminary_technician_id uuid
);


--
-- Name: maintenance_schedules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_schedules (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    maintenance_plan_id uuid NOT NULL,
    last_completed_at timestamp(0) with time zone,
    last_completed_meter numeric(10,1),
    next_due_at timestamp(0) with time zone,
    next_due_meter numeric(10,1),
    times_executed integer DEFAULT 0 NOT NULL,
    times_skipped integer DEFAULT 0 NOT NULL,
    last_work_order_id uuid,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: manufacturers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.manufacturers (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    country_code character(2),
    website character varying(255),
    contact_email character varying(255),
    contact_phone character varying(50),
    notes text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    team_id uuid NOT NULL,
    permission_id uuid NOT NULL,
    model_type character varying(255) NOT NULL,
    model_uuid uuid NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    team_id uuid NOT NULL,
    role_id uuid NOT NULL,
    model_type character varying(255) NOT NULL,
    model_uuid uuid NOT NULL
);


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id uuid NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: passkeys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.passkeys (
    id bigint NOT NULL,
    user_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    credential_id character varying(255) NOT NULL,
    credential json NOT NULL,
    last_used_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: passkeys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.passkeys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: passkeys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.passkeys_id_seq OWNED BY public.passkeys.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) with time zone
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id uuid NOT NULL,
    tenant_id uuid,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: plants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plants (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    code character varying(30) NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(500),
    latitude numeric(10,7),
    longitude numeric(10,7),
    city character varying(100),
    state_province character varying(100),
    country_code character(3),
    timezone character varying(50),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: push_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.push_subscriptions (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    user_id uuid NOT NULL,
    endpoint text NOT NULL,
    public_key text NOT NULL,
    auth_token text NOT NULL,
    content_encoding character varying(20) DEFAULT 'aesgcm'::character varying NOT NULL,
    device_name character varying(255),
    last_used_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id uuid NOT NULL,
    role_id uuid NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id uuid NOT NULL,
    team_id uuid,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id uuid,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: spare_parts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.spare_parts (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    manufacturer_id uuid,
    supplier_id uuid,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    category_type character varying(30) NOT NULL,
    criticality character varying(20) NOT NULL,
    abc_classification character varying(1),
    unit character varying(20) NOT NULL,
    unit_cost numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    minimum_stock numeric(12,4),
    maximum_stock numeric(12,4),
    reorder_point numeric(12,4),
    reorder_quantity numeric(12,4),
    lead_time_days smallint,
    notes text,
    is_active boolean DEFAULT true NOT NULL,
    created_by uuid NOT NULL,
    updated_by uuid,
    deleted_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: suppliers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suppliers (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    tax_id character varying(50),
    contact_name character varying(255),
    contact_email character varying(255),
    contact_phone character varying(50),
    address character varying(500),
    city character varying(100),
    country_code character(2),
    notes text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: tenant_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tenant_users (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    user_id uuid NOT NULL,
    is_primary_tenant boolean DEFAULT false NOT NULL,
    is_owner boolean DEFAULT false NOT NULL,
    joined_at timestamp(0) with time zone NOT NULL,
    invited_by uuid,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: tenants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tenants (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(100) NOT NULL,
    tax_id character varying(50),
    contact_email character varying(255),
    contact_phone character varying(30),
    country_code character(3),
    timezone character varying(50) DEFAULT 'UTC'::character varying NOT NULL,
    locale character varying(10) DEFAULT 'es_CO'::character varying NOT NULL,
    subscription_plan character varying(50) DEFAULT 'starter'::character varying NOT NULL,
    subscription_expires_at date,
    is_active boolean DEFAULT true NOT NULL,
    logo_path character varying(500),
    settings jsonb,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: user_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_profiles (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    avatar_path character varying(500),
    phone character varying(30),
    job_title character varying(100),
    preferred_language character varying(10),
    locale character varying(10),
    timezone character varying(50),
    bio text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) with time zone,
    password character varying(255),
    remember_token character varying(100),
    is_active boolean DEFAULT true NOT NULL,
    is_super_admin boolean DEFAULT false NOT NULL,
    last_login_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    last_login_ip inet
);


--
-- Name: warehouse_spare_parts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_spare_parts (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    warehouse_id uuid NOT NULL,
    spare_part_id uuid NOT NULL,
    current_stock numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    reserved_stock numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    average_unit_cost numeric(12,4),
    bin_location character varying(50),
    last_counted_by uuid,
    last_counted_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT chk_wsp_current_stock_non_negative CHECK ((current_stock >= (0)::numeric)),
    CONSTRAINT chk_wsp_reserved_stock_non_negative CHECK ((reserved_stock >= (0)::numeric))
);


--
-- Name: warehouses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouses (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    location character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    created_by uuid NOT NULL,
    updated_by uuid,
    deleted_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: webhook_delivery_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook_delivery_logs (
    id uuid NOT NULL,
    webhook_subscription_id uuid NOT NULL,
    event_id character varying(36) NOT NULL,
    event_name character varying(100) NOT NULL,
    http_status smallint,
    duration_ms smallint,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    delivered_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    response_size integer
);


--
-- Name: webhook_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook_subscriptions (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    url character varying(2048) NOT NULL,
    events jsonb NOT NULL,
    secret character varying(64) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    failure_count smallint DEFAULT '0'::smallint NOT NULL,
    last_triggered_at timestamp(0) with time zone,
    created_by uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    last_error character varying(500)
);


--
-- Name: work_order_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_order_attachments (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    work_order_id uuid NOT NULL,
    attachment_type character varying(30) NOT NULL,
    file_path character varying(500) NOT NULL,
    file_name character varying(255) NOT NULL,
    file_size bigint NOT NULL,
    mime_type character varying(100) NOT NULL,
    caption character varying(500),
    uploaded_by uuid NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


--
-- Name: work_order_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_order_comments (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    work_order_id uuid NOT NULL,
    user_id uuid NOT NULL,
    body text NOT NULL,
    is_internal boolean DEFAULT false NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: work_order_parts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_order_parts (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    work_order_id uuid NOT NULL,
    part_code character varying(100),
    description character varying(255) NOT NULL,
    quantity numeric(10,3) NOT NULL,
    unit character varying(30),
    unit_cost numeric(15,2),
    total_cost numeric(15,2),
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    spare_part_id uuid,
    warehouse_id uuid,
    status character varying(20) DEFAULT 'requested'::character varying NOT NULL,
    reserved_quantity numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    issued_quantity numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    returned_quantity numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost_snapshot numeric(12,4)
);


--
-- Name: work_order_signatures; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_order_signatures (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    work_order_id uuid NOT NULL,
    user_id uuid NOT NULL,
    signature_type character varying(30) NOT NULL,
    signed_at timestamp(0) with time zone NOT NULL,
    notes text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: work_order_technicians; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_order_technicians (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    work_order_id uuid NOT NULL,
    user_id uuid NOT NULL,
    role character varying(30) NOT NULL,
    planned_hours numeric(6,2),
    hourly_rate numeric(10,2),
    notes text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: work_order_time_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_order_time_logs (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    work_order_id uuid NOT NULL,
    user_id uuid NOT NULL,
    started_at timestamp(0) with time zone NOT NULL,
    ended_at timestamp(0) with time zone,
    hours numeric(6,2),
    description text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: work_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_orders (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    work_order_number character varying(30) NOT NULL,
    maintenance_request_id uuid,
    equipment_id uuid NOT NULL,
    plant_id uuid NOT NULL,
    area_id uuid,
    work_order_type character varying(30) NOT NULL,
    status character varying(30) NOT NULL,
    priority character varying(20) NOT NULL,
    title character varying(255) NOT NULL,
    description text NOT NULL,
    instructions text,
    failure_cause text,
    work_performed text,
    root_cause text,
    rejection_reason text,
    equipment_stopped boolean DEFAULT false NOT NULL,
    downtime_minutes integer,
    planned_start_at timestamp(0) with time zone,
    planned_end_at timestamp(0) with time zone,
    planned_labor_hours numeric(8,2),
    actual_start_at timestamp(0) with time zone,
    actual_end_at timestamp(0) with time zone,
    actual_labor_hours numeric(8,2),
    estimated_cost numeric(15,2),
    actual_cost_labor numeric(15,2),
    actual_cost_parts numeric(15,2),
    actual_cost_external numeric(15,2),
    actual_cost_total numeric(15,2),
    currency_code character(3) DEFAULT 'COP'::bpchar NOT NULL,
    created_by uuid NOT NULL,
    assigned_supervisor uuid,
    completed_by uuid,
    verified_by uuid,
    started_at timestamp(0) with time zone,
    completed_at timestamp(0) with time zone,
    verified_at timestamp(0) with time zone,
    closed_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone,
    maintenance_plan_id uuid
);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: passkeys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys ALTER COLUMN id SET DEFAULT nextval('public.passkeys_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: activity_locations activity_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_locations
    ADD CONSTRAINT activity_locations_pkey PRIMARY KEY (id);


--
-- Name: alerts alerts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerts
    ADD CONSTRAINT alerts_pkey PRIMARY KEY (id);


--
-- Name: api_request_logs api_request_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_request_logs
    ADD CONSTRAINT api_request_logs_pkey PRIMARY KEY (id);


--
-- Name: areas areas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.areas
    ADD CONSTRAINT areas_pkey PRIMARY KEY (id);


--
-- Name: areas areas_plant_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.areas
    ADD CONSTRAINT areas_plant_code_unique UNIQUE (plant_id, code);


--
-- Name: areas areas_plant_sort_order_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.areas
    ADD CONSTRAINT areas_plant_sort_order_unique UNIQUE (plant_id, sort_order);


--
-- Name: automation_rule_executions automation_rule_executions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.automation_rule_executions
    ADD CONSTRAINT automation_rule_executions_pkey PRIMARY KEY (id);


--
-- Name: automation_rule_executions automation_rule_executions_rule_id_entity_type_entity_id_action; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.automation_rule_executions
    ADD CONSTRAINT automation_rule_executions_rule_id_entity_type_entity_id_action UNIQUE (rule_id, entity_type, entity_id, action_taken);


--
-- Name: automation_rules automation_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.automation_rules
    ADD CONSTRAINT automation_rules_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: equipment_categories equipment_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_categories
    ADD CONSTRAINT equipment_categories_pkey PRIMARY KEY (id);


--
-- Name: equipment_categories equipment_categories_tenant_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_categories
    ADD CONSTRAINT equipment_categories_tenant_id_code_unique UNIQUE (tenant_id, code);


--
-- Name: equipment_documents equipment_documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_documents
    ADD CONSTRAINT equipment_documents_pkey PRIMARY KEY (id);


--
-- Name: equipment_downtime_events equipment_downtime_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_downtime_events
    ADD CONSTRAINT equipment_downtime_events_pkey PRIMARY KEY (id);


--
-- Name: equipment_downtime_events equipment_downtime_events_work_order_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_downtime_events
    ADD CONSTRAINT equipment_downtime_events_work_order_id_unique UNIQUE (work_order_id);


--
-- Name: equipment_issue_reports equipment_issue_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_issue_reports
    ADD CONSTRAINT equipment_issue_reports_pkey PRIMARY KEY (id);


--
-- Name: equipment_kpis equipment_kpis_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_kpis
    ADD CONSTRAINT equipment_kpis_pkey PRIMARY KEY (id);


--
-- Name: equipment_kpis equipment_kpis_unique_per_equipment; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_kpis
    ADD CONSTRAINT equipment_kpis_unique_per_equipment UNIQUE (tenant_id, equipment_id);


--
-- Name: equipment_meter_readings equipment_meter_readings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_meter_readings
    ADD CONSTRAINT equipment_meter_readings_pkey PRIMARY KEY (id);


--
-- Name: equipment_photos equipment_photos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_photos
    ADD CONSTRAINT equipment_photos_pkey PRIMARY KEY (id);


--
-- Name: equipment equipment_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_pkey PRIMARY KEY (id);


--
-- Name: equipment_qr_codes equipment_qr_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_qr_codes
    ADD CONSTRAINT equipment_qr_codes_pkey PRIMARY KEY (id);


--
-- Name: equipment_qr_codes equipment_qr_codes_qr_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_qr_codes
    ADD CONSTRAINT equipment_qr_codes_qr_token_unique UNIQUE (qr_token);


--
-- Name: equipment equipment_tenant_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_tenant_id_code_unique UNIQUE (tenant_id, code);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: idempotency_keys idempotency_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_keys
    ADD CONSTRAINT idempotency_keys_pkey PRIMARY KEY (id);


--
-- Name: idempotency_keys idempotency_keys_tenant_id_idempotency_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_keys
    ADD CONSTRAINT idempotency_keys_tenant_id_idempotency_key_unique UNIQUE (tenant_id, idempotency_key);


--
-- Name: inventory_transactions inventory_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_pkey PRIMARY KEY (id);


--
-- Name: inventory_transactions inventory_transactions_tenant_id_transaction_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_tenant_id_transaction_number_unique UNIQUE (tenant_id, transaction_number);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: maintenance_checklist_items maintenance_checklist_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_checklist_items
    ADD CONSTRAINT maintenance_checklist_items_pkey PRIMARY KEY (id);


--
-- Name: maintenance_plan_attachments maintenance_plan_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plan_attachments
    ADD CONSTRAINT maintenance_plan_attachments_pkey PRIMARY KEY (id);


--
-- Name: maintenance_plan_tasks maintenance_plan_tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plan_tasks
    ADD CONSTRAINT maintenance_plan_tasks_pkey PRIMARY KEY (id);


--
-- Name: maintenance_plans maintenance_plans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plans
    ADD CONSTRAINT maintenance_plans_pkey PRIMARY KEY (id);


--
-- Name: maintenance_plans maintenance_plans_tenant_id_plan_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plans
    ADD CONSTRAINT maintenance_plans_tenant_id_plan_number_unique UNIQUE (tenant_id, plan_number);


--
-- Name: maintenance_request_attachments maintenance_request_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_request_attachments
    ADD CONSTRAINT maintenance_request_attachments_pkey PRIMARY KEY (id);


--
-- Name: maintenance_request_comments maintenance_request_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_request_comments
    ADD CONSTRAINT maintenance_request_comments_pkey PRIMARY KEY (id);


--
-- Name: maintenance_requests maintenance_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_pkey PRIMARY KEY (id);


--
-- Name: maintenance_requests maintenance_requests_tenant_id_request_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_tenant_id_request_number_unique UNIQUE (tenant_id, request_number);


--
-- Name: maintenance_schedules maintenance_schedules_maintenance_plan_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_schedules
    ADD CONSTRAINT maintenance_schedules_maintenance_plan_id_unique UNIQUE (maintenance_plan_id);


--
-- Name: maintenance_schedules maintenance_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_schedules
    ADD CONSTRAINT maintenance_schedules_pkey PRIMARY KEY (id);


--
-- Name: manufacturers manufacturers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.manufacturers
    ADD CONSTRAINT manufacturers_pkey PRIMARY KEY (id);


--
-- Name: manufacturers manufacturers_tenant_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.manufacturers
    ADD CONSTRAINT manufacturers_tenant_id_code_unique UNIQUE (tenant_id, code);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (team_id, permission_id, model_uuid, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (team_id, role_id, model_uuid, model_type);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: passkeys passkeys_credential_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_credential_id_unique UNIQUE (credential_id);


--
-- Name: passkeys passkeys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: plants plants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plants
    ADD CONSTRAINT plants_pkey PRIMARY KEY (id);


--
-- Name: plants plants_tenant_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plants
    ADD CONSTRAINT plants_tenant_code_unique UNIQUE (tenant_id, code);


--
-- Name: push_subscriptions push_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: push_subscriptions push_subscriptions_user_endpoint_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_user_endpoint_unique UNIQUE (user_id, endpoint);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_team_id_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_team_id_name_guard_name_unique UNIQUE (team_id, name, guard_name);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: spare_parts spare_parts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spare_parts
    ADD CONSTRAINT spare_parts_pkey PRIMARY KEY (id);


--
-- Name: spare_parts spare_parts_tenant_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spare_parts
    ADD CONSTRAINT spare_parts_tenant_id_code_unique UNIQUE (tenant_id, code);


--
-- Name: suppliers suppliers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_pkey PRIMARY KEY (id);


--
-- Name: suppliers suppliers_tenant_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_tenant_id_code_unique UNIQUE (tenant_id, code);


--
-- Name: tenant_users tenant_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_users
    ADD CONSTRAINT tenant_users_pkey PRIMARY KEY (id);


--
-- Name: tenant_users tenant_users_tenant_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_users
    ADD CONSTRAINT tenant_users_tenant_id_user_id_unique UNIQUE (tenant_id, user_id);


--
-- Name: tenants tenants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_slug_unique UNIQUE (slug);


--
-- Name: user_profiles user_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_pkey PRIMARY KEY (id);


--
-- Name: user_profiles user_profiles_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_user_id_unique UNIQUE (user_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: warehouse_spare_parts warehouse_spare_parts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_spare_parts
    ADD CONSTRAINT warehouse_spare_parts_pkey PRIMARY KEY (id);


--
-- Name: warehouse_spare_parts warehouse_spare_parts_warehouse_id_spare_part_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_spare_parts
    ADD CONSTRAINT warehouse_spare_parts_warehouse_id_spare_part_id_unique UNIQUE (warehouse_id, spare_part_id);


--
-- Name: warehouses warehouses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouses
    ADD CONSTRAINT warehouses_pkey PRIMARY KEY (id);


--
-- Name: warehouses warehouses_tenant_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouses
    ADD CONSTRAINT warehouses_tenant_id_code_unique UNIQUE (tenant_id, code);


--
-- Name: warehouses warehouses_tenant_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouses
    ADD CONSTRAINT warehouses_tenant_id_name_unique UNIQUE (tenant_id, name);


--
-- Name: webhook_delivery_logs webhook_delivery_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_delivery_logs
    ADD CONSTRAINT webhook_delivery_logs_pkey PRIMARY KEY (id);


--
-- Name: webhook_subscriptions webhook_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_subscriptions
    ADD CONSTRAINT webhook_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: work_order_attachments work_order_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_attachments
    ADD CONSTRAINT work_order_attachments_pkey PRIMARY KEY (id);


--
-- Name: work_order_comments work_order_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_comments
    ADD CONSTRAINT work_order_comments_pkey PRIMARY KEY (id);


--
-- Name: work_order_parts work_order_parts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_parts
    ADD CONSTRAINT work_order_parts_pkey PRIMARY KEY (id);


--
-- Name: work_order_signatures work_order_signatures_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_signatures
    ADD CONSTRAINT work_order_signatures_pkey PRIMARY KEY (id);


--
-- Name: work_order_signatures work_order_signatures_work_order_id_signature_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_signatures
    ADD CONSTRAINT work_order_signatures_work_order_id_signature_type_unique UNIQUE (work_order_id, signature_type);


--
-- Name: work_order_technicians work_order_technicians_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_technicians
    ADD CONSTRAINT work_order_technicians_pkey PRIMARY KEY (id);


--
-- Name: work_order_technicians work_order_technicians_work_order_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_technicians
    ADD CONSTRAINT work_order_technicians_work_order_id_user_id_unique UNIQUE (work_order_id, user_id);


--
-- Name: work_order_time_logs work_order_time_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_time_logs
    ADD CONSTRAINT work_order_time_logs_pkey PRIMARY KEY (id);


--
-- Name: work_orders work_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_pkey PRIMARY KEY (id);


--
-- Name: work_orders work_orders_tenant_id_work_order_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_tenant_id_work_order_number_unique UNIQUE (tenant_id, work_order_number);


--
-- Name: activity_locations_activity_type_activity_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_locations_activity_type_activity_id_index ON public.activity_locations USING btree (activity_type, activity_id);


--
-- Name: activity_locations_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_locations_tenant_id_index ON public.activity_locations USING btree (tenant_id);


--
-- Name: activity_locations_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_locations_user_id_index ON public.activity_locations USING btree (user_id);


--
-- Name: alerts_open_idempotency; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX alerts_open_idempotency ON public.alerts USING btree (tenant_id, entity_type, entity_id, category) WHERE (((status)::text = 'open'::text) AND (deleted_at IS NULL));


--
-- Name: alerts_tenant_id_entity_type_entity_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX alerts_tenant_id_entity_type_entity_id_index ON public.alerts USING btree (tenant_id, entity_type, entity_id);


--
-- Name: alerts_tenant_id_status_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX alerts_tenant_id_status_category_index ON public.alerts USING btree (tenant_id, status, category);


--
-- Name: alerts_tenant_id_status_severity_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX alerts_tenant_id_status_severity_created_at_index ON public.alerts USING btree (tenant_id, status, severity, created_at);


--
-- Name: api_request_logs_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX api_request_logs_created_at_index ON public.api_request_logs USING btree (created_at);


--
-- Name: api_request_logs_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX api_request_logs_tenant_id_index ON public.api_request_logs USING btree (tenant_id);


--
-- Name: api_request_logs_token_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX api_request_logs_token_id_index ON public.api_request_logs USING btree (token_id);


--
-- Name: areas_plant_active_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX areas_plant_active_idx ON public.areas USING btree (plant_id, is_active);


--
-- Name: areas_plant_sort_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX areas_plant_sort_idx ON public.areas USING btree (plant_id, sort_order);


--
-- Name: areas_tenant_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX areas_tenant_id_idx ON public.areas USING btree (tenant_id);


--
-- Name: automation_rule_executions_entity_type_entity_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX automation_rule_executions_entity_type_entity_id_index ON public.automation_rule_executions USING btree (entity_type, entity_id);


--
-- Name: automation_rule_executions_executed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX automation_rule_executions_executed_at_index ON public.automation_rule_executions USING btree (executed_at);


--
-- Name: automation_rule_executions_rule_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX automation_rule_executions_rule_id_index ON public.automation_rule_executions USING btree (rule_id);


--
-- Name: automation_rules_tenant_id_event_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX automation_rules_tenant_id_event_type_index ON public.automation_rules USING btree (tenant_id, event_type);


--
-- Name: automation_rules_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX automation_rules_tenant_id_index ON public.automation_rules USING btree (tenant_id);


--
-- Name: automation_rules_tenant_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX automation_rules_tenant_id_is_active_index ON public.automation_rules USING btree (tenant_id, is_active);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: equipment_active_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_active_idx ON public.equipment USING btree (tenant_id, status, priority) WHERE (deleted_at IS NULL);


--
-- Name: equipment_categories_tenant_id_is_active_sort_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_categories_tenant_id_is_active_sort_order_index ON public.equipment_categories USING btree (tenant_id, is_active, sort_order);


--
-- Name: equipment_categories_tenant_id_parent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_categories_tenant_id_parent_id_index ON public.equipment_categories USING btree (tenant_id, parent_id);


--
-- Name: equipment_category_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_category_id_index ON public.equipment USING btree (category_id);


--
-- Name: equipment_documents_equipment_id_document_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_documents_equipment_id_document_type_index ON public.equipment_documents USING btree (equipment_id, document_type);


--
-- Name: equipment_documents_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_documents_expires_at_index ON public.equipment_documents USING btree (expires_at);


--
-- Name: equipment_documents_tenant_id_document_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_documents_tenant_id_document_type_index ON public.equipment_documents USING btree (tenant_id, document_type);


--
-- Name: equipment_documents_title_version_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX equipment_documents_title_version_unique ON public.equipment_documents USING btree (equipment_id, title, version) WHERE (deleted_at IS NULL);


--
-- Name: equipment_downtime_events_equipment_id_started_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_downtime_events_equipment_id_started_at_index ON public.equipment_downtime_events USING btree (equipment_id, started_at);


--
-- Name: equipment_downtime_events_tenant_id_equipment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_downtime_events_tenant_id_equipment_id_index ON public.equipment_downtime_events USING btree (tenant_id, equipment_id);


--
-- Name: equipment_issue_reports_equipment_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_issue_reports_equipment_id_status_index ON public.equipment_issue_reports USING btree (equipment_id, status);


--
-- Name: equipment_issue_reports_tenant_id_severity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_issue_reports_tenant_id_severity_index ON public.equipment_issue_reports USING btree (tenant_id, severity);


--
-- Name: equipment_issue_reports_tenant_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_issue_reports_tenant_id_status_index ON public.equipment_issue_reports USING btree (tenant_id, status);


--
-- Name: equipment_kpis_equipment_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_kpis_equipment_idx ON public.equipment_kpis USING btree (equipment_id) WHERE (deleted_at IS NULL);


--
-- Name: equipment_kpis_stale_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_kpis_stale_idx ON public.equipment_kpis USING btree (tenant_id, is_stale) WHERE (deleted_at IS NULL);


--
-- Name: equipment_manufacturer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_manufacturer_id_index ON public.equipment USING btree (manufacturer_id);


--
-- Name: equipment_meter_readings_equipment_id_recorded_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_meter_readings_equipment_id_recorded_at_index ON public.equipment_meter_readings USING btree (equipment_id, recorded_at);


--
-- Name: equipment_meter_readings_tenant_id_equipment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_meter_readings_tenant_id_equipment_id_index ON public.equipment_meter_readings USING btree (tenant_id, equipment_id);


--
-- Name: equipment_parent_equipment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_parent_equipment_id_index ON public.equipment USING btree (parent_equipment_id);


--
-- Name: equipment_photos_equipment_id_is_primary_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_photos_equipment_id_is_primary_index ON public.equipment_photos USING btree (equipment_id, is_primary);


--
-- Name: equipment_photos_equipment_id_sort_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_photos_equipment_id_sort_order_index ON public.equipment_photos USING btree (equipment_id, sort_order);


--
-- Name: equipment_qr_codes_active_token_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_qr_codes_active_token_idx ON public.equipment_qr_codes USING btree (qr_token) WHERE ((deleted_at IS NULL) AND (is_active = true));


--
-- Name: equipment_qr_codes_equipment_id_active_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX equipment_qr_codes_equipment_id_active_unique ON public.equipment_qr_codes USING btree (equipment_id) WHERE (deleted_at IS NULL);


--
-- Name: equipment_qr_codes_tenant_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_qr_codes_tenant_id_is_active_index ON public.equipment_qr_codes USING btree (tenant_id, is_active);


--
-- Name: equipment_supplier_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_supplier_id_index ON public.equipment USING btree (supplier_id);


--
-- Name: equipment_tenant_area_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_tenant_area_idx ON public.equipment USING btree (tenant_id, area_id);


--
-- Name: equipment_tenant_category_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_tenant_category_idx ON public.equipment USING btree (tenant_id, category_id);


--
-- Name: equipment_tenant_plant_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_tenant_plant_idx ON public.equipment USING btree (tenant_id, plant_id);


--
-- Name: equipment_tenant_status_criticality_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_tenant_status_criticality_idx ON public.equipment USING btree (tenant_id, status, criticality);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: idempotency_keys_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idempotency_keys_expires_at_index ON public.idempotency_keys USING btree (expires_at);


--
-- Name: idempotency_keys_tenant_id_idempotency_key_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idempotency_keys_tenant_id_idempotency_key_expires_at_index ON public.idempotency_keys USING btree (tenant_id, idempotency_key, expires_at);


--
-- Name: idx_ede_tenant_started_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ede_tenant_started_at ON public.equipment_downtime_events USING btree (tenant_id, started_at);


--
-- Name: idx_inventory_transactions_related; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_inventory_transactions_related ON public.inventory_transactions USING btree (related_transaction_id) WHERE (related_transaction_id IS NOT NULL);


--
-- Name: idx_inventory_transactions_wo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_inventory_transactions_wo ON public.inventory_transactions USING btree (work_order_id) WHERE (work_order_id IS NOT NULL);


--
-- Name: idx_webhook_subscriptions_events_gin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_webhook_subscriptions_events_gin ON public.webhook_subscriptions USING gin (events jsonb_path_ops);


--
-- Name: idx_wo_tenant_equipment; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_wo_tenant_equipment ON public.work_orders USING btree (tenant_id, equipment_id);


--
-- Name: inventory_transactions_tenant_id_performed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventory_transactions_tenant_id_performed_at_index ON public.inventory_transactions USING btree (tenant_id, performed_at);


--
-- Name: inventory_transactions_tenant_id_type_performed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventory_transactions_tenant_id_type_performed_at_index ON public.inventory_transactions USING btree (tenant_id, type, performed_at);


--
-- Name: inventory_transactions_warehouse_id_spare_part_id_performed_at_; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventory_transactions_warehouse_id_spare_part_id_performed_at_ ON public.inventory_transactions USING btree (warehouse_id, spare_part_id, performed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: maintenance_checklist_items_maintenance_plan_task_id_sort_order; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_checklist_items_maintenance_plan_task_id_sort_order ON public.maintenance_checklist_items USING btree (maintenance_plan_task_id, sort_order);


--
-- Name: maintenance_plan_attachments_maintenance_plan_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_plan_attachments_maintenance_plan_id_index ON public.maintenance_plan_attachments USING btree (maintenance_plan_id);


--
-- Name: maintenance_plan_tasks_maintenance_plan_id_sort_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_plan_tasks_maintenance_plan_id_sort_order_index ON public.maintenance_plan_tasks USING btree (maintenance_plan_id, sort_order);


--
-- Name: maintenance_plans_tenant_id_equipment_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_plans_tenant_id_equipment_id_is_active_index ON public.maintenance_plans USING btree (tenant_id, equipment_id, is_active);


--
-- Name: maintenance_request_attachments_maintenance_request_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_request_attachments_maintenance_request_id_index ON public.maintenance_request_attachments USING btree (maintenance_request_id);


--
-- Name: maintenance_request_comments_maintenance_request_id_created_at_; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_request_comments_maintenance_request_id_created_at_ ON public.maintenance_request_comments USING btree (maintenance_request_id, created_at);


--
-- Name: maintenance_requests_approved_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_requests_approved_by_index ON public.maintenance_requests USING btree (approved_by);


--
-- Name: maintenance_requests_assigned_reviewer_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_requests_assigned_reviewer_index ON public.maintenance_requests USING btree (assigned_reviewer);


--
-- Name: maintenance_requests_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_requests_created_by_index ON public.maintenance_requests USING btree (created_by);


--
-- Name: maintenance_requests_equipment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_requests_equipment_id_index ON public.maintenance_requests USING btree (equipment_id);


--
-- Name: maintenance_requests_rejected_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_requests_rejected_by_index ON public.maintenance_requests USING btree (rejected_by);


--
-- Name: maintenance_requests_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_requests_status_index ON public.maintenance_requests USING btree (status);


--
-- Name: maintenance_requests_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_requests_tenant_id_index ON public.maintenance_requests USING btree (tenant_id);


--
-- Name: maintenance_schedules_tenant_id_next_due_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_schedules_tenant_id_next_due_at_index ON public.maintenance_schedules USING btree (tenant_id, next_due_at);


--
-- Name: maintenance_schedules_tenant_id_next_due_meter_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_schedules_tenant_id_next_due_meter_index ON public.maintenance_schedules USING btree (tenant_id, next_due_meter);


--
-- Name: manufacturers_tenant_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX manufacturers_tenant_id_is_active_index ON public.manufacturers USING btree (tenant_id, is_active);


--
-- Name: model_has_permissions_model_uuid_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_uuid_model_type_index ON public.model_has_permissions USING btree (model_uuid, model_type);


--
-- Name: model_has_roles_model_uuid_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_uuid_model_type_index ON public.model_has_roles USING btree (model_uuid, model_type);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: passkeys_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX passkeys_user_id_index ON public.passkeys USING btree (user_id);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tenant_id_index ON public.personal_access_tokens USING btree (tenant_id);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: plants_tenant_active_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX plants_tenant_active_idx ON public.plants USING btree (tenant_id, is_active);


--
-- Name: push_subscriptions_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_subscriptions_tenant_id_index ON public.push_subscriptions USING btree (tenant_id);


--
-- Name: push_subscriptions_user_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_subscriptions_user_id_is_active_index ON public.push_subscriptions USING btree (user_id, is_active);


--
-- Name: roles_team_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX roles_team_id_index ON public.roles USING btree (team_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: spare_parts_tenant_id_category_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX spare_parts_tenant_id_category_type_index ON public.spare_parts USING btree (tenant_id, category_type);


--
-- Name: spare_parts_tenant_id_criticality_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX spare_parts_tenant_id_criticality_is_active_index ON public.spare_parts USING btree (tenant_id, criticality, is_active);


--
-- Name: suppliers_tenant_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX suppliers_tenant_id_is_active_index ON public.suppliers USING btree (tenant_id, is_active);


--
-- Name: tenant_users_owner_per_tenant_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX tenant_users_owner_per_tenant_idx ON public.tenant_users USING btree (tenant_id) WHERE (is_owner = true);


--
-- Name: tenant_users_primary_per_user_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX tenant_users_primary_per_user_idx ON public.tenant_users USING btree (user_id) WHERE (is_primary_tenant = true);


--
-- Name: tenant_users_user_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tenant_users_user_id_idx ON public.tenant_users USING btree (user_id);


--
-- Name: tenants_active_slug_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tenants_active_slug_idx ON public.tenants USING btree (slug) WHERE (is_active = true);


--
-- Name: users_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_is_active_index ON public.users USING btree (is_active);


--
-- Name: warehouse_spare_parts_tenant_id_spare_part_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_spare_parts_tenant_id_spare_part_id_index ON public.warehouse_spare_parts USING btree (tenant_id, spare_part_id);


--
-- Name: warehouse_spare_parts_tenant_id_warehouse_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_spare_parts_tenant_id_warehouse_id_index ON public.warehouse_spare_parts USING btree (tenant_id, warehouse_id);


--
-- Name: webhook_delivery_logs_webhook_subscription_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_delivery_logs_webhook_subscription_id_created_at_index ON public.webhook_delivery_logs USING btree (webhook_subscription_id, created_at);


--
-- Name: webhook_subscriptions_events_gin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_subscriptions_events_gin ON public.webhook_subscriptions USING gin (events);


--
-- Name: webhook_subscriptions_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_subscriptions_tenant_id_index ON public.webhook_subscriptions USING btree (tenant_id);


--
-- Name: work_order_attachments_work_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_attachments_work_order_id_index ON public.work_order_attachments USING btree (work_order_id);


--
-- Name: work_order_comments_work_order_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_comments_work_order_id_created_at_index ON public.work_order_comments USING btree (work_order_id, created_at);


--
-- Name: work_order_parts_spare_part_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_parts_spare_part_id_status_index ON public.work_order_parts USING btree (spare_part_id, status);


--
-- Name: work_order_parts_warehouse_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_parts_warehouse_id_index ON public.work_order_parts USING btree (warehouse_id);


--
-- Name: work_order_parts_work_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_parts_work_order_id_index ON public.work_order_parts USING btree (work_order_id);


--
-- Name: work_order_parts_work_order_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_parts_work_order_id_status_index ON public.work_order_parts USING btree (work_order_id, status);


--
-- Name: work_order_signatures_work_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_signatures_work_order_id_index ON public.work_order_signatures USING btree (work_order_id);


--
-- Name: work_order_technicians_work_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_technicians_work_order_id_index ON public.work_order_technicians USING btree (work_order_id);


--
-- Name: work_order_time_logs_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_time_logs_user_id_index ON public.work_order_time_logs USING btree (user_id);


--
-- Name: work_order_time_logs_work_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_order_time_logs_work_order_id_index ON public.work_order_time_logs USING btree (work_order_id);


--
-- Name: work_orders_area_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_orders_area_id_index ON public.work_orders USING btree (area_id);


--
-- Name: work_orders_assigned_supervisor_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_orders_assigned_supervisor_index ON public.work_orders USING btree (assigned_supervisor);


--
-- Name: work_orders_completed_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_orders_completed_by_index ON public.work_orders USING btree (completed_by);


--
-- Name: work_orders_equipment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_orders_equipment_id_index ON public.work_orders USING btree (equipment_id);


--
-- Name: work_orders_maintenance_plan_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_orders_maintenance_plan_id_index ON public.work_orders USING btree (maintenance_plan_id);


--
-- Name: work_orders_maintenance_request_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_orders_maintenance_request_id_index ON public.work_orders USING btree (maintenance_request_id);


--
-- Name: work_orders_plant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_orders_plant_id_index ON public.work_orders USING btree (plant_id);


--
-- Name: work_orders_tenant_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_orders_tenant_id_status_index ON public.work_orders USING btree (tenant_id, status);


--
-- Name: work_orders_verified_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX work_orders_verified_by_index ON public.work_orders USING btree (verified_by);


--
-- Name: activity_locations activity_locations_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_locations
    ADD CONSTRAINT activity_locations_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: activity_locations activity_locations_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_locations
    ADD CONSTRAINT activity_locations_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: alerts alerts_closed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerts
    ADD CONSTRAINT alerts_closed_by_foreign FOREIGN KEY (closed_by) REFERENCES public.users(id);


--
-- Name: alerts alerts_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alerts
    ADD CONSTRAINT alerts_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: api_request_logs api_request_logs_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_request_logs
    ADD CONSTRAINT api_request_logs_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: api_request_logs api_request_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.api_request_logs
    ADD CONSTRAINT api_request_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: areas areas_plant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.areas
    ADD CONSTRAINT areas_plant_id_foreign FOREIGN KEY (plant_id) REFERENCES public.plants(id) ON DELETE RESTRICT;


--
-- Name: areas areas_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.areas
    ADD CONSTRAINT areas_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: automation_rule_executions automation_rule_executions_rule_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.automation_rule_executions
    ADD CONSTRAINT automation_rule_executions_rule_id_foreign FOREIGN KEY (rule_id) REFERENCES public.automation_rules(id);


--
-- Name: automation_rules automation_rules_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.automation_rules
    ADD CONSTRAINT automation_rules_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: equipment equipment_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_area_id_foreign FOREIGN KEY (area_id) REFERENCES public.areas(id) ON DELETE RESTRICT;


--
-- Name: equipment_categories equipment_categories_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_categories
    ADD CONSTRAINT equipment_categories_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;


--
-- Name: equipment_categories equipment_categories_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_categories
    ADD CONSTRAINT equipment_categories_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: equipment equipment_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;


--
-- Name: equipment equipment_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_documents equipment_documents_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_documents
    ADD CONSTRAINT equipment_documents_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_documents equipment_documents_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_documents
    ADD CONSTRAINT equipment_documents_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: equipment_documents equipment_documents_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_documents
    ADD CONSTRAINT equipment_documents_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_downtime_events equipment_downtime_events_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_downtime_events
    ADD CONSTRAINT equipment_downtime_events_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_downtime_events equipment_downtime_events_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_downtime_events
    ADD CONSTRAINT equipment_downtime_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: equipment_downtime_events equipment_downtime_events_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_downtime_events
    ADD CONSTRAINT equipment_downtime_events_work_order_id_foreign FOREIGN KEY (work_order_id) REFERENCES public.work_orders(id) ON DELETE SET NULL;


--
-- Name: equipment_issue_reports equipment_issue_reports_acknowledged_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_issue_reports
    ADD CONSTRAINT equipment_issue_reports_acknowledged_by_foreign FOREIGN KEY (acknowledged_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_issue_reports equipment_issue_reports_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_issue_reports
    ADD CONSTRAINT equipment_issue_reports_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_issue_reports equipment_issue_reports_maintenance_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_issue_reports
    ADD CONSTRAINT equipment_issue_reports_maintenance_request_id_foreign FOREIGN KEY (maintenance_request_id) REFERENCES public.maintenance_requests(id) ON DELETE SET NULL;


--
-- Name: equipment_issue_reports equipment_issue_reports_qr_code_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_issue_reports
    ADD CONSTRAINT equipment_issue_reports_qr_code_id_foreign FOREIGN KEY (qr_code_id) REFERENCES public.equipment_qr_codes(id) ON DELETE SET NULL;


--
-- Name: equipment_issue_reports equipment_issue_reports_reporter_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_issue_reports
    ADD CONSTRAINT equipment_issue_reports_reporter_user_id_foreign FOREIGN KEY (reporter_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_issue_reports equipment_issue_reports_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_issue_reports
    ADD CONSTRAINT equipment_issue_reports_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: equipment_kpis equipment_kpis_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_kpis
    ADD CONSTRAINT equipment_kpis_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_kpis equipment_kpis_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_kpis
    ADD CONSTRAINT equipment_kpis_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: equipment equipment_manufacturer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_manufacturer_id_foreign FOREIGN KEY (manufacturer_id) REFERENCES public.manufacturers(id) ON DELETE SET NULL;


--
-- Name: equipment_meter_readings equipment_meter_readings_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_meter_readings
    ADD CONSTRAINT equipment_meter_readings_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_meter_readings equipment_meter_readings_recorded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_meter_readings
    ADD CONSTRAINT equipment_meter_readings_recorded_by_foreign FOREIGN KEY (recorded_by) REFERENCES public.users(id);


--
-- Name: equipment_meter_readings equipment_meter_readings_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_meter_readings
    ADD CONSTRAINT equipment_meter_readings_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: equipment equipment_parent_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_parent_equipment_id_foreign FOREIGN KEY (parent_equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: equipment_photos equipment_photos_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_photos
    ADD CONSTRAINT equipment_photos_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_photos equipment_photos_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_photos
    ADD CONSTRAINT equipment_photos_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: equipment_photos equipment_photos_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_photos
    ADD CONSTRAINT equipment_photos_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment equipment_plant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_plant_id_foreign FOREIGN KEY (plant_id) REFERENCES public.plants(id) ON DELETE RESTRICT;


--
-- Name: equipment_qr_codes equipment_qr_codes_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_qr_codes
    ADD CONSTRAINT equipment_qr_codes_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_qr_codes equipment_qr_codes_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_qr_codes
    ADD CONSTRAINT equipment_qr_codes_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: equipment equipment_supplier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES public.suppliers(id) ON DELETE SET NULL;


--
-- Name: equipment equipment_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: equipment equipment_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: idempotency_keys idempotency_keys_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_keys
    ADD CONSTRAINT idempotency_keys_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: idempotency_keys idempotency_keys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_keys
    ADD CONSTRAINT idempotency_keys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: inventory_transactions inventory_transactions_destination_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_destination_warehouse_id_foreign FOREIGN KEY (destination_warehouse_id) REFERENCES public.warehouses(id) ON DELETE SET NULL;


--
-- Name: inventory_transactions inventory_transactions_performed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_performed_by_foreign FOREIGN KEY (performed_by) REFERENCES public.users(id);


--
-- Name: inventory_transactions inventory_transactions_related_transaction_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_related_transaction_id_foreign FOREIGN KEY (related_transaction_id) REFERENCES public.inventory_transactions(id) ON DELETE SET NULL;


--
-- Name: inventory_transactions inventory_transactions_source_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_source_warehouse_id_foreign FOREIGN KEY (source_warehouse_id) REFERENCES public.warehouses(id) ON DELETE SET NULL;


--
-- Name: inventory_transactions inventory_transactions_spare_part_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_spare_part_id_foreign FOREIGN KEY (spare_part_id) REFERENCES public.spare_parts(id) ON DELETE CASCADE;


--
-- Name: inventory_transactions inventory_transactions_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: inventory_transactions inventory_transactions_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_warehouse_id_foreign FOREIGN KEY (warehouse_id) REFERENCES public.warehouses(id) ON DELETE CASCADE;


--
-- Name: inventory_transactions inventory_transactions_warehouse_spare_part_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_warehouse_spare_part_id_foreign FOREIGN KEY (warehouse_spare_part_id) REFERENCES public.warehouse_spare_parts(id) ON DELETE CASCADE;


--
-- Name: inventory_transactions inventory_transactions_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_work_order_id_foreign FOREIGN KEY (work_order_id) REFERENCES public.work_orders(id) ON DELETE SET NULL;


--
-- Name: inventory_transactions inventory_transactions_work_order_part_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_transactions
    ADD CONSTRAINT inventory_transactions_work_order_part_id_foreign FOREIGN KEY (work_order_part_id) REFERENCES public.work_order_parts(id) ON DELETE SET NULL;


--
-- Name: maintenance_checklist_items maintenance_checklist_items_maintenance_plan_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_checklist_items
    ADD CONSTRAINT maintenance_checklist_items_maintenance_plan_task_id_foreign FOREIGN KEY (maintenance_plan_task_id) REFERENCES public.maintenance_plan_tasks(id) ON DELETE CASCADE;


--
-- Name: maintenance_checklist_items maintenance_checklist_items_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_checklist_items
    ADD CONSTRAINT maintenance_checklist_items_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: maintenance_plan_attachments maintenance_plan_attachments_maintenance_plan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plan_attachments
    ADD CONSTRAINT maintenance_plan_attachments_maintenance_plan_id_foreign FOREIGN KEY (maintenance_plan_id) REFERENCES public.maintenance_plans(id) ON DELETE CASCADE;


--
-- Name: maintenance_plan_attachments maintenance_plan_attachments_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plan_attachments
    ADD CONSTRAINT maintenance_plan_attachments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: maintenance_plan_attachments maintenance_plan_attachments_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plan_attachments
    ADD CONSTRAINT maintenance_plan_attachments_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id);


--
-- Name: maintenance_plan_tasks maintenance_plan_tasks_maintenance_plan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plan_tasks
    ADD CONSTRAINT maintenance_plan_tasks_maintenance_plan_id_foreign FOREIGN KEY (maintenance_plan_id) REFERENCES public.maintenance_plans(id) ON DELETE CASCADE;


--
-- Name: maintenance_plan_tasks maintenance_plan_tasks_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plan_tasks
    ADD CONSTRAINT maintenance_plan_tasks_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: maintenance_plans maintenance_plans_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plans
    ADD CONSTRAINT maintenance_plans_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: maintenance_plans maintenance_plans_responsible_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plans
    ADD CONSTRAINT maintenance_plans_responsible_user_id_foreign FOREIGN KEY (responsible_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: maintenance_plans maintenance_plans_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_plans
    ADD CONSTRAINT maintenance_plans_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: maintenance_request_attachments maintenance_request_attachments_maintenance_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_request_attachments
    ADD CONSTRAINT maintenance_request_attachments_maintenance_request_id_foreign FOREIGN KEY (maintenance_request_id) REFERENCES public.maintenance_requests(id) ON DELETE CASCADE;


--
-- Name: maintenance_request_attachments maintenance_request_attachments_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_request_attachments
    ADD CONSTRAINT maintenance_request_attachments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: maintenance_request_attachments maintenance_request_attachments_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_request_attachments
    ADD CONSTRAINT maintenance_request_attachments_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id);


--
-- Name: maintenance_request_comments maintenance_request_comments_maintenance_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_request_comments
    ADD CONSTRAINT maintenance_request_comments_maintenance_request_id_foreign FOREIGN KEY (maintenance_request_id) REFERENCES public.maintenance_requests(id) ON DELETE CASCADE;


--
-- Name: maintenance_request_comments maintenance_request_comments_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_request_comments
    ADD CONSTRAINT maintenance_request_comments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: maintenance_request_comments maintenance_request_comments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_request_comments
    ADD CONSTRAINT maintenance_request_comments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: maintenance_requests maintenance_requests_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id);


--
-- Name: maintenance_requests maintenance_requests_assigned_reviewer_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_assigned_reviewer_foreign FOREIGN KEY (assigned_reviewer) REFERENCES public.users(id);


--
-- Name: maintenance_requests maintenance_requests_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: maintenance_requests maintenance_requests_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: maintenance_requests maintenance_requests_issue_report_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_issue_report_id_foreign FOREIGN KEY (issue_report_id) REFERENCES public.equipment_issue_reports(id) ON DELETE SET NULL;


--
-- Name: maintenance_requests maintenance_requests_preliminary_technician_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_preliminary_technician_id_foreign FOREIGN KEY (preliminary_technician_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: maintenance_requests maintenance_requests_rejected_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_rejected_by_foreign FOREIGN KEY (rejected_by) REFERENCES public.users(id);


--
-- Name: maintenance_requests maintenance_requests_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: maintenance_requests maintenance_requests_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_requests
    ADD CONSTRAINT maintenance_requests_work_order_id_foreign FOREIGN KEY (work_order_id) REFERENCES public.work_orders(id) ON DELETE SET NULL;


--
-- Name: maintenance_schedules maintenance_schedules_last_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_schedules
    ADD CONSTRAINT maintenance_schedules_last_work_order_id_foreign FOREIGN KEY (last_work_order_id) REFERENCES public.work_orders(id) ON DELETE SET NULL;


--
-- Name: maintenance_schedules maintenance_schedules_maintenance_plan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_schedules
    ADD CONSTRAINT maintenance_schedules_maintenance_plan_id_foreign FOREIGN KEY (maintenance_plan_id) REFERENCES public.maintenance_plans(id) ON DELETE CASCADE;


--
-- Name: maintenance_schedules maintenance_schedules_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_schedules
    ADD CONSTRAINT maintenance_schedules_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: manufacturers manufacturers_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.manufacturers
    ADD CONSTRAINT manufacturers_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: passkeys passkeys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: personal_access_tokens personal_access_tokens_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: plants plants_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plants
    ADD CONSTRAINT plants_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: push_subscriptions push_subscriptions_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: push_subscriptions push_subscriptions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: spare_parts spare_parts_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spare_parts
    ADD CONSTRAINT spare_parts_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: spare_parts spare_parts_manufacturer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spare_parts
    ADD CONSTRAINT spare_parts_manufacturer_id_foreign FOREIGN KEY (manufacturer_id) REFERENCES public.manufacturers(id) ON DELETE SET NULL;


--
-- Name: spare_parts spare_parts_supplier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spare_parts
    ADD CONSTRAINT spare_parts_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES public.suppliers(id) ON DELETE SET NULL;


--
-- Name: spare_parts spare_parts_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spare_parts
    ADD CONSTRAINT spare_parts_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: spare_parts spare_parts_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spare_parts
    ADD CONSTRAINT spare_parts_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id);


--
-- Name: suppliers suppliers_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: tenant_users tenant_users_invited_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_users
    ADD CONSTRAINT tenant_users_invited_by_foreign FOREIGN KEY (invited_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tenant_users tenant_users_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_users
    ADD CONSTRAINT tenant_users_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: tenant_users tenant_users_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_users
    ADD CONSTRAINT tenant_users_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_profiles user_profiles_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: warehouse_spare_parts warehouse_spare_parts_last_counted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_spare_parts
    ADD CONSTRAINT warehouse_spare_parts_last_counted_by_foreign FOREIGN KEY (last_counted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: warehouse_spare_parts warehouse_spare_parts_spare_part_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_spare_parts
    ADD CONSTRAINT warehouse_spare_parts_spare_part_id_foreign FOREIGN KEY (spare_part_id) REFERENCES public.spare_parts(id) ON DELETE CASCADE;


--
-- Name: warehouse_spare_parts warehouse_spare_parts_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_spare_parts
    ADD CONSTRAINT warehouse_spare_parts_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: warehouse_spare_parts warehouse_spare_parts_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_spare_parts
    ADD CONSTRAINT warehouse_spare_parts_warehouse_id_foreign FOREIGN KEY (warehouse_id) REFERENCES public.warehouses(id) ON DELETE CASCADE;


--
-- Name: warehouses warehouses_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouses
    ADD CONSTRAINT warehouses_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: warehouses warehouses_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouses
    ADD CONSTRAINT warehouses_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: warehouses warehouses_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouses
    ADD CONSTRAINT warehouses_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id);


--
-- Name: webhook_delivery_logs webhook_delivery_logs_webhook_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_delivery_logs
    ADD CONSTRAINT webhook_delivery_logs_webhook_subscription_id_foreign FOREIGN KEY (webhook_subscription_id) REFERENCES public.webhook_subscriptions(id) ON DELETE CASCADE;


--
-- Name: webhook_subscriptions webhook_subscriptions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_subscriptions
    ADD CONSTRAINT webhook_subscriptions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: webhook_subscriptions webhook_subscriptions_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_subscriptions
    ADD CONSTRAINT webhook_subscriptions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: work_order_attachments work_order_attachments_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_attachments
    ADD CONSTRAINT work_order_attachments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: work_order_attachments work_order_attachments_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_attachments
    ADD CONSTRAINT work_order_attachments_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id);


--
-- Name: work_order_attachments work_order_attachments_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_attachments
    ADD CONSTRAINT work_order_attachments_work_order_id_foreign FOREIGN KEY (work_order_id) REFERENCES public.work_orders(id) ON DELETE CASCADE;


--
-- Name: work_order_comments work_order_comments_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_comments
    ADD CONSTRAINT work_order_comments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: work_order_comments work_order_comments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_comments
    ADD CONSTRAINT work_order_comments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: work_order_comments work_order_comments_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_comments
    ADD CONSTRAINT work_order_comments_work_order_id_foreign FOREIGN KEY (work_order_id) REFERENCES public.work_orders(id) ON DELETE CASCADE;


--
-- Name: work_order_parts work_order_parts_spare_part_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_parts
    ADD CONSTRAINT work_order_parts_spare_part_id_foreign FOREIGN KEY (spare_part_id) REFERENCES public.spare_parts(id);


--
-- Name: work_order_parts work_order_parts_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_parts
    ADD CONSTRAINT work_order_parts_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: work_order_parts work_order_parts_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_parts
    ADD CONSTRAINT work_order_parts_warehouse_id_foreign FOREIGN KEY (warehouse_id) REFERENCES public.warehouses(id);


--
-- Name: work_order_parts work_order_parts_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_parts
    ADD CONSTRAINT work_order_parts_work_order_id_foreign FOREIGN KEY (work_order_id) REFERENCES public.work_orders(id) ON DELETE CASCADE;


--
-- Name: work_order_signatures work_order_signatures_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_signatures
    ADD CONSTRAINT work_order_signatures_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: work_order_signatures work_order_signatures_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_signatures
    ADD CONSTRAINT work_order_signatures_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: work_order_signatures work_order_signatures_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_signatures
    ADD CONSTRAINT work_order_signatures_work_order_id_foreign FOREIGN KEY (work_order_id) REFERENCES public.work_orders(id) ON DELETE CASCADE;


--
-- Name: work_order_technicians work_order_technicians_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_technicians
    ADD CONSTRAINT work_order_technicians_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: work_order_technicians work_order_technicians_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_technicians
    ADD CONSTRAINT work_order_technicians_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: work_order_technicians work_order_technicians_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_technicians
    ADD CONSTRAINT work_order_technicians_work_order_id_foreign FOREIGN KEY (work_order_id) REFERENCES public.work_orders(id) ON DELETE CASCADE;


--
-- Name: work_order_time_logs work_order_time_logs_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_time_logs
    ADD CONSTRAINT work_order_time_logs_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: work_order_time_logs work_order_time_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_time_logs
    ADD CONSTRAINT work_order_time_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: work_order_time_logs work_order_time_logs_work_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_order_time_logs
    ADD CONSTRAINT work_order_time_logs_work_order_id_foreign FOREIGN KEY (work_order_id) REFERENCES public.work_orders(id) ON DELETE CASCADE;


--
-- Name: work_orders work_orders_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_area_id_foreign FOREIGN KEY (area_id) REFERENCES public.areas(id) ON DELETE SET NULL;


--
-- Name: work_orders work_orders_assigned_supervisor_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_assigned_supervisor_foreign FOREIGN KEY (assigned_supervisor) REFERENCES public.users(id);


--
-- Name: work_orders work_orders_completed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_completed_by_foreign FOREIGN KEY (completed_by) REFERENCES public.users(id);


--
-- Name: work_orders work_orders_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: work_orders work_orders_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: work_orders work_orders_maintenance_plan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_maintenance_plan_id_foreign FOREIGN KEY (maintenance_plan_id) REFERENCES public.maintenance_plans(id) ON DELETE SET NULL;


--
-- Name: work_orders work_orders_maintenance_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_maintenance_request_id_foreign FOREIGN KEY (maintenance_request_id) REFERENCES public.maintenance_requests(id) ON DELETE SET NULL;


--
-- Name: work_orders work_orders_plant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_plant_id_foreign FOREIGN KEY (plant_id) REFERENCES public.plants(id) ON DELETE CASCADE;


--
-- Name: work_orders work_orders_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: work_orders work_orders_verified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_orders
    ADD CONSTRAINT work_orders_verified_by_foreign FOREIGN KEY (verified_by) REFERENCES public.users(id);


--
-- PostgreSQL database dump complete
--

\unrestrict PKMgx9UGXkgVZx1OhEi944hXfqA0o3ofUtbohUiFfMaDGisexBzenBejQTv4pRr

--
-- PostgreSQL database dump
--

\restrict uIrNtMoJGtijkEcqe10XtGLGtf7yT1xgxNPXzDlkYzK5Sb12OS9s0iykzBphcCE

-- Dumped from database version 18.4
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2024_01_01_000000_create_passkeys_table	1
5	2026_06_07_224713_create_permission_tables	1
6	2026_06_08_024445_create_tenants_table	1
7	2026_06_08_031313_create_user_profiles_table	1
8	2026_06_08_031548_create_tenant_users_table	1
9	2026_06_08_031942_create_plants_table	1
10	2026_06_08_032000_create_areas_table	1
11	2026_06_08_174808_create_equipment_categories_table	1
12	2026_06_08_174809_create_manufacturers_table	1
13	2026_06_08_174810_create_suppliers_table	1
14	2026_06_08_174811_create_equipment_table	1
15	2026_06_08_183149_create_equipment_documents_table	1
16	2026_06_08_183150_create_equipment_photos_table	1
17	2026_06_08_184306_create_equipment_qr_codes_table	1
18	2026_06_08_184307_create_equipment_issue_reports_table	1
19	2026_06_08_203047_fix_equipment_qr_codes_equipment_id_partial_unique	1
20	2026_06_08_205749_create_maintenance_requests_table	1
21	2026_06_08_205750_create_maintenance_request_comments_table	1
22	2026_06_08_205751_create_maintenance_request_attachments_table	1
23	2026_06_08_205752_add_maintenance_request_id_to_equipment_issue_reports	1
24	2026_06_08_213319_create_work_orders_table	1
25	2026_06_08_213320_create_work_order_technicians_table	1
26	2026_06_08_213321_create_work_order_time_logs_table	1
27	2026_06_08_213322_create_work_order_parts_table	1
28	2026_06_08_213323_create_work_order_comments_table	1
29	2026_06_08_213324_create_work_order_attachments_table	1
30	2026_06_08_213326_create_work_order_signatures_table	1
31	2026_06_08_213327_add_work_order_id_to_maintenance_requests	1
32	2026_06_08_233253_add_current_meter_reading_to_equipment	1
33	2026_06_08_233254_create_equipment_meter_readings_table	1
34	2026_06_08_233255_create_maintenance_plans_table	1
35	2026_06_08_233256_create_maintenance_plan_tasks_table	1
36	2026_06_08_233257_create_maintenance_checklist_items_table	1
37	2026_06_08_233258_create_maintenance_schedules_table	1
38	2026_06_08_233259_create_maintenance_plan_attachments_table	1
39	2026_06_08_233300_add_maintenance_plan_id_to_work_orders	1
40	2026_06_09_044719_add_last_failure_at_to_equipment	1
41	2026_06_09_044721_create_equipment_downtime_events_table	1
42	2026_06_09_052545_create_spare_parts_table	1
43	2026_06_09_052546_create_warehouses_table	1
44	2026_06_09_052547_create_warehouse_spare_parts_table	1
45	2026_06_09_052548_create_inventory_transactions_table	1
46	2026_06_09_053946_fix_inventory_transactions_transaction_number_unique	1
47	2026_06_09_060624_add_inventory_fields_to_work_order_parts_table	1
48	2026_06_09_135241_create_equipment_kpis_table	1
49	2026_06_09_151305_create_notifications_table	1
50	2026_06_09_155619_add_analytics_indexes_to_event_and_order_tables	2
51	2026_06_09_161643_create_personal_access_tokens_table	3
52	2026_06_09_183652_create_api_request_logs_table	4
53	2026_06_09_183653_create_idempotency_keys_table	4
54	2026_06_09_183654_create_webhook_subscriptions_table	4
55	2026_06_10_034624_create_push_subscriptions_table	5
56	2026_06_10_052756_create_activity_locations_table	6
57	2026_06_10_155404_create_automation_rules_table	7
58	2026_06_10_155405_create_automation_rule_executions_table	7
59	2026_06_10_170859_create_alerts_table	7
60	2026_06_10_182342_add_last_error_to_webhook_subscriptions_table	8
61	2026_06_10_182342_create_webhook_delivery_logs_table	8
62	2026_06_11_040316_add_preliminary_technician_to_maintenance_requests	9
63	2026_06_11_062325_replace_response_body_with_response_size_in_webhook_delivery_logs	10
64	2026_06_11_071658_add_missing_fk_indexes_for_performance	11
65	2026_06_11_074202_add_gin_index_to_webhook_subscriptions_events	12
66	2026_06_14_201444_add_is_component_type_to_equipment_categories_table	13
67	2026_06_15_012125_add_color_to_equipment_categories_table	14
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 67, true);


--
-- PostgreSQL database dump complete
--

\unrestrict uIrNtMoJGtijkEcqe10XtGLGtf7yT1xgxNPXzDlkYzK5Sb12OS9s0iykzBphcCE

