-- Database Schema for Queuing Management System
-- Generated from TypeScript interfaces and UI table columns analysis

-- ============================================================================
-- ENUM TYPES
-- ============================================================================

CREATE TYPE counter_type_enum AS ENUM ('benefit', 'shib');
CREATE TYPE counter_status_enum AS ENUM ('active', 'inactive', 'maintenance');
CREATE TYPE device_type_enum AS ENUM ('kiosk', 'tv', 'desktop');
CREATE TYPE device_status_enum AS ENUM ('online', 'offline', 'maintenance');
CREATE TYPE queue_status_enum AS ENUM ('normal', 'busy', 'critical', 'paused');
CREATE TYPE ticket_status_enum AS ENUM ('waiting', 'called', 'serving', 'completed', 'skipped', 'transferred', 'cancelled');
CREATE TYPE ticket_history_status_enum AS ENUM ('completed', 'skipped', 'transferred');
CREATE TYPE counter_log_status_enum AS ENUM ('active', 'idle', 'offline');
CREATE TYPE alert_type_enum AS ENUM ('warning', 'critical', 'info');
CREATE TYPE user_role_enum AS ENUM ('counter', 'supervisor', 'admin');
CREATE TYPE service_status_enum AS ENUM ('active', 'inactive');
CREATE TYPE clerk_status_enum AS ENUM ('active', 'inactive', 'on_leave');

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- 1. Regions
CREATE TABLE regions (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Offices
CREATE TABLE offices (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    address VARCHAR(500),
    region_id VARCHAR(50) NOT NULL REFERENCES regions(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_offices_region_id (region_id)
);

-- 3. Clerks (Officers)
CREATE TABLE clerks (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(255) UNIQUE,
    department VARCHAR(100),
    phone_number VARCHAR(20),
    status clerk_status_enum DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_clerks_email (email),
    INDEX idx_clerks_status (status)
);

-- 4. Counters
CREATE TABLE counters (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    type counter_type_enum NOT NULL,
    status counter_status_enum DEFAULT 'active',
    office_id VARCHAR(50) NOT NULL REFERENCES offices(id) ON DELETE CASCADE,
    clerk_id VARCHAR(50) REFERENCES clerks(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_counters_office_id (office_id),
    INDEX idx_counters_clerk_id (clerk_id),
    INDEX idx_counters_status (status),
    INDEX idx_counters_type (type)
);

-- 5. Services
CREATE TABLE services (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    estimated_time INTEGER, -- in minutes
    status service_status_enum DEFAULT 'active',
    region_id VARCHAR(50) NOT NULL REFERENCES regions(id) ON DELETE CASCADE,
    office_id VARCHAR(50) NOT NULL REFERENCES offices(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_services_office_id (office_id),
    INDEX idx_services_region_id (region_id),
    INDEX idx_services_status (status)
);

-- 6. Service Documents
CREATE TABLE service_documents (
    id VARCHAR(50) PRIMARY KEY,
    service_id VARCHAR(50) NOT NULL REFERENCES services(id) ON DELETE CASCADE,
    document_name VARCHAR(200) NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    order_index INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service_documents_service_id (service_id)
);

-- 7. Devices
CREATE TABLE devices (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    type device_type_enum NOT NULL,
    status device_status_enum DEFAULT 'offline',
    region_id VARCHAR(50) NOT NULL REFERENCES regions(id) ON DELETE CASCADE,
    office_id VARCHAR(50) NOT NULL REFERENCES offices(id) ON DELETE CASCADE,
    serial_number VARCHAR(100) UNIQUE,
    ip_address VARCHAR(50),
    password VARCHAR(255), -- encrypted
    last_seen TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_devices_office_id (office_id),
    INDEX idx_devices_region_id (region_id),
    INDEX idx_devices_type (type),
    INDEX idx_devices_status (status),
    INDEX idx_devices_serial_number (serial_number)
);

-- 8. Queues
CREATE TABLE queues (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    service_type VARCHAR(200) NOT NULL,
    office_id VARCHAR(50) NOT NULL REFERENCES offices(id) ON DELETE CASCADE,
    service_id VARCHAR(50) REFERENCES services(id) ON DELETE SET NULL,
    status queue_status_enum DEFAULT 'normal',
    members_waiting INTEGER DEFAULT 0,
    members_being_served INTEGER DEFAULT 0,
    average_wait_time INTEGER DEFAULT 0, -- in minutes
    counters INTEGER DEFAULT 0,
    active_counters INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_queues_office_id (office_id),
    INDEX idx_queues_service_id (service_id),
    INDEX idx_queues_status (status)
);

-- 9. Tickets
CREATE TABLE tickets (
    id VARCHAR(50) PRIMARY KEY,
    ticket_number VARCHAR(50) UNIQUE NOT NULL,
    service_type VARCHAR(200) NOT NULL,
    service_id VARCHAR(50) REFERENCES services(id) ON DELETE SET NULL,
    queue_id VARCHAR(50) NOT NULL REFERENCES queues(id) ON DELETE CASCADE,
    member_number VARCHAR(50),
    member_name VARCHAR(200),
    phone_number VARCHAR(20),
    estimated_time INTEGER, -- in seconds
    priority BOOLEAN DEFAULT FALSE,
    status ticket_status_enum DEFAULT 'waiting',
    counter_id VARCHAR(50) REFERENCES counters(id) ON DELETE SET NULL,
    clerk_id VARCHAR(50) REFERENCES clerks(id) ON DELETE SET NULL,
    called_at TIMESTAMP,
    serving_started_at TIMESTAMP,
    completed_at TIMESTAMP,
    duration_seconds INTEGER,
    transferred_to_counter_id VARCHAR(50) REFERENCES counters(id) ON DELETE SET NULL,
    office_id VARCHAR(50) NOT NULL REFERENCES offices(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tickets_ticket_number (ticket_number),
    INDEX idx_tickets_queue_id (queue_id),
    INDEX idx_tickets_counter_id (counter_id),
    INDEX idx_tickets_clerk_id (clerk_id),
    INDEX idx_tickets_status (status),
    INDEX idx_tickets_created_at (created_at),
    INDEX idx_tickets_member_number (member_number),
    INDEX idx_tickets_office_id (office_id)
);

-- 10. Ticket History
CREATE TABLE ticket_history (
    id VARCHAR(50) PRIMARY KEY,
    ticket_id VARCHAR(50) REFERENCES tickets(id) ON DELETE SET NULL,
    ticket_number VARCHAR(50) NOT NULL,
    service_type VARCHAR(200) NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP,
    duration INTEGER, -- in seconds
    status ticket_history_status_enum NOT NULL,
    transferred_to VARCHAR(200),
    clerk_id VARCHAR(50) REFERENCES clerks(id) ON DELETE SET NULL,
    clerk_name VARCHAR(200),
    counter_id VARCHAR(50) REFERENCES counters(id) ON DELETE SET NULL,
    counter_name VARCHAR(200),
    office_id VARCHAR(50) REFERENCES offices(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_history_ticket_id (ticket_id),
    INDEX idx_ticket_history_clerk_id (clerk_id),
    INDEX idx_ticket_history_counter_id (counter_id),
    INDEX idx_ticket_history_start_time (start_time),
    INDEX idx_ticket_history_status (status),
    INDEX idx_ticket_history_office_id (office_id)
);

-- 11. Counter Assignments
CREATE TABLE counter_assignments (
    id VARCHAR(50) PRIMARY KEY,
    counter_id VARCHAR(50) NOT NULL REFERENCES counters(id) ON DELETE CASCADE,
    clerk_id VARCHAR(50) NOT NULL REFERENCES clerks(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unassigned_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_counter_assignments_counter_id (counter_id),
    INDEX idx_counter_assignments_clerk_id (clerk_id),
    INDEX idx_counter_assignments_is_active (is_active),
    INDEX idx_counter_assignments_assigned_at (assigned_at)
);

-- 12. Counter Status Log
CREATE TABLE counter_status_log (
    id VARCHAR(50) PRIMARY KEY,
    counter_id VARCHAR(50) NOT NULL REFERENCES counters(id) ON DELETE CASCADE,
    status counter_log_status_enum NOT NULL,
    current_ticket_number VARCHAR(50),
    time_spent INTEGER, -- in seconds
    clerk_id VARCHAR(50) REFERENCES clerks(id) ON DELETE SET NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_counter_status_log_counter_id (counter_id),
    INDEX idx_counter_status_log_logged_at (logged_at),
    INDEX idx_counter_status_log_status (status)
);

-- 13. Queue Alerts
CREATE TABLE queue_alerts (
    id VARCHAR(50) PRIMARY KEY,
    queue_id VARCHAR(50) NOT NULL REFERENCES queues(id) ON DELETE CASCADE,
    type alert_type_enum NOT NULL,
    message TEXT NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    INDEX idx_queue_alerts_queue_id (queue_id),
    INDEX idx_queue_alerts_is_resolved (is_resolved),
    INDEX idx_queue_alerts_type (type)
);

-- 14. Users (Authentication)
CREATE TABLE users (
    id VARCHAR(50) PRIMARY KEY,
    clerk_id VARCHAR(50) REFERENCES clerks(id) ON DELETE SET NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role user_role_enum NOT NULL,
    device_name VARCHAR(200),
    device_password VARCHAR(255), -- encrypted
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_username (username),
    INDEX idx_users_clerk_id (clerk_id),
    INDEX idx_users_role (role),
    INDEX idx_users_email (email)
);

-- 15. Daily Reports (Materialized View or Table)
CREATE TABLE daily_reports (
    id VARCHAR(50) PRIMARY KEY,
    report_date DATE NOT NULL,
    office_id VARCHAR(50) NOT NULL REFERENCES offices(id) ON DELETE CASCADE,
    total_tickets INTEGER DEFAULT 0,
    completed_tickets INTEGER DEFAULT 0,
    skipped_tickets INTEGER DEFAULT 0,
    transferred_tickets INTEGER DEFAULT 0,
    average_wait_time DECIMAL(10,2),
    average_service_time DECIMAL(10,2),
    peak_hour VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_report (report_date, office_id),
    INDEX idx_daily_reports_date (report_date),
    INDEX idx_daily_reports_office_id (office_id)
);

-- 16. Clerk Performance (View or Materialized Table)
CREATE TABLE clerk_performance (
    id VARCHAR(50) PRIMARY KEY,
    clerk_id VARCHAR(50) NOT NULL REFERENCES clerks(id) ON DELETE CASCADE,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    tickets_served INTEGER DEFAULT 0,
    avg_service_time DECIMAL(10,2),
    completion_rate DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_clerk_performance_clerk_id (clerk_id),
    INDEX idx_clerk_performance_period (period_start, period_end)
);

-- 17. Counter Performance (View or Materialized Table)
CREATE TABLE counter_performance (
    id VARCHAR(50) PRIMARY KEY,
    counter_id VARCHAR(50) NOT NULL REFERENCES counters(id) ON DELETE CASCADE,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    tickets_served INTEGER DEFAULT 0,
    avg_service_time DECIMAL(10,2),
    efficiency DECIMAL(10,2), -- tickets per minute
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_counter_performance_counter_id (counter_id),
    INDEX idx_counter_performance_period (period_start, period_end)
);

-- 18. Members (Optional - for storing member information)
CREATE TABLE members (
    member_number VARCHAR(50) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    phone_number VARCHAR(20),
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_members_phone_number (phone_number),
    INDEX idx_members_email (email)
);

-- 19. Device Sessions (For device authentication)
CREATE TABLE device_sessions (
    id VARCHAR(50) PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_sessions_device_id (device_id),
    INDEX idx_device_sessions_token (token),
    INDEX idx_device_sessions_expires_at (expires_at)
);

-- ============================================================================
-- TRIGGERS
-- ============================================================================

-- Auto-update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_regions_updated_at BEFORE UPDATE ON regions
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_offices_updated_at BEFORE UPDATE ON offices
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_clerks_updated_at BEFORE UPDATE ON clerks
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_counters_updated_at BEFORE UPDATE ON counters
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_services_updated_at BEFORE UPDATE ON services
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_devices_updated_at BEFORE UPDATE ON devices
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_queues_updated_at BEFORE UPDATE ON queues
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_tickets_updated_at BEFORE UPDATE ON tickets
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- VIEWS (Optional - for reporting)
-- ============================================================================

-- View for active ticket summary
CREATE OR REPLACE VIEW active_tickets_summary AS
SELECT 
    t.id,
    t.ticket_number,
    t.service_type,
    t.status,
    t.queue_id,
    q.name as queue_name,
    t.counter_id,
    c.name as counter_name,
    t.clerk_id,
    cl.name as clerk_name,
    t.created_at,
    EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - t.called_at)) / 60 as wait_time_minutes
FROM tickets t
LEFT JOIN queues q ON t.queue_id = q.id
LEFT JOIN counters c ON t.counter_id = c.id
LEFT JOIN clerks cl ON t.clerk_id = cl.id
WHERE t.status IN ('waiting', 'called', 'serving');

-- View for counter status with clerk info
CREATE OR REPLACE VIEW counter_status_view AS
SELECT 
    c.id,
    c.name as counter_name,
    c.type as counter_type,
    c.status as counter_status,
    c.office_id,
    o.name as office_name,
    c.clerk_id,
    cl.name as clerk_name,
    cl.email as clerk_email,
    c.created_at,
    c.updated_at
FROM counters c
LEFT JOIN offices o ON c.office_id = o.id
LEFT JOIN clerks cl ON c.clerk_id = cl.id;

-- ============================================================================
-- SAMPLE QUERIES (For Reference)
-- ============================================================================

-- Get all counters with assigned clerks for an office
-- SELECT * FROM counter_status_view WHERE office_id = 'office-1';

-- Get active tickets for a queue
-- SELECT * FROM active_tickets_summary WHERE queue_id = 'queue-1';

-- Get clerk performance for a date range
-- SELECT * FROM clerk_performance 
-- WHERE clerk_id = 'clerk-1' 
-- AND period_start >= '2024-01-01' 
-- AND period_end <= '2024-01-31';

-- Get tickets served by a clerk today
-- SELECT COUNT(*) as tickets_served 
-- FROM ticket_history 
-- WHERE clerk_id = 'clerk-1' 
-- AND DATE(start_time) = CURRENT_DATE 
-- AND status = 'completed';
