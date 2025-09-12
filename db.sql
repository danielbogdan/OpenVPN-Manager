-- Schema OpenVPN Admin (first-run, fără utilizator seed)
CREATE DATABASE IF NOT EXISTS openvpn_admin
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE openvpn_admin;

-- Utilizatori aplicație (login GUI)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clienți / tenants (o instanță Docker per rând)
CREATE TABLE IF NOT EXISTS tenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  public_ip VARCHAR(64) NOT NULL,               -- auto detect la creare
  listen_port INT NOT NULL,                     -- ex. 1194, 1195, ...
  subnet_cidr VARCHAR(32) NOT NULL,             -- ex. 10.20.0.0/26
  nat_enabled TINYINT(1) DEFAULT 1,
  status ENUM('running','paused') DEFAULT 'running', -- pentru pauză/reluare
  docker_volume VARCHAR(128) NOT NULL,          -- ex. ovpn_data_tenant_3
  docker_container VARCHAR(128) NOT NULL,       -- ex. vpn_tenant_3
  docker_network VARCHAR(128) NOT NULL,         -- ex. net_tenant_3
  status_path VARCHAR(256) DEFAULT '/etc/openvpn/openvpn-status.log',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_container (docker_container),
  UNIQUE KEY uniq_volume (docker_volume),
  UNIQUE KEY uniq_network (docker_network),
  UNIQUE KEY uniq_port (listen_port)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client users (tenant self-management access)
CREATE TABLE IF NOT EXISTS client_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  username VARCHAR(128) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(190),
  full_name VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  last_login TIMESTAMP NULL,
  last_login_ip VARCHAR(45) DEFAULT NULL,
  last_activity TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tenant_username (tenant_id, username),
  CONSTRAINT fk_client_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subrețele suplimentare pentru același tenant
CREATE TABLE IF NOT EXISTS tenant_networks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  subnet_cidr VARCHAR(32) NOT NULL,             -- ex. 10.21.0.0/26
  UNIQUE KEY uniq_tenant_subnet (tenant_id, subnet_cidr),
  CONSTRAINT fk_tnet_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Utilizatori VPN (certificate)
CREATE TABLE IF NOT EXISTS vpn_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  username VARCHAR(128) NOT NULL,
  status ENUM('active','revoked') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tenant_user (tenant_id, username),
  KEY idx_status (status),
  CONSTRAINT fk_vu_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sesiuni conectate (ultimul snapshot)
CREATE TABLE IF NOT EXISTS sessions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT DEFAULT NULL,                     -- Reference to vpn_users.id
  common_name VARCHAR(128) NOT NULL,            -- CN (user)
  real_address VARCHAR(128) NOT NULL,           -- ip:port public
  virtual_address VARCHAR(64) DEFAULT NULL,     -- IP din tun
  bytes_received BIGINT DEFAULT 0,
  bytes_sent BIGINT DEFAULT 0,
  since TIMESTAMP NULL,
  geo_country VARCHAR(64) DEFAULT NULL,
  geo_city VARCHAR(64) DEFAULT NULL,
  last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tenant_cn (tenant_id, common_name),
  KEY idx_tenant_user (tenant_id, user_id),
  KEY idx_last_seen (last_seen),
  CONSTRAINT fk_sess_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_sess_user
    FOREIGN KEY (user_id) REFERENCES vpn_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Traffic monitoring and analytics tables
CREATE TABLE IF NOT EXISTS traffic_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  bytes_in BIGINT DEFAULT 0,
  bytes_out BIGINT DEFAULT 0,
  protocol VARCHAR(16) DEFAULT NULL, -- TCP, UDP, ICMP, etc.
  destination_ip VARCHAR(45) DEFAULT NULL,
  destination_port INT DEFAULT NULL,
  source_ip VARCHAR(45) DEFAULT NULL,
  source_port INT DEFAULT NULL,
  application_type VARCHAR(64) DEFAULT NULL, -- email, youtube, google, facebook, etc.
  domain VARCHAR(255) DEFAULT NULL,
  country_code VARCHAR(2) DEFAULT NULL,
  INDEX idx_tenant_timestamp (tenant_id, timestamp),
  INDEX idx_user_timestamp (user_id, timestamp),
  INDEX idx_application (application_type),
  CONSTRAINT fk_traffic_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_traffic_user
    FOREIGN KEY (user_id) REFERENCES vpn_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Traffic statistics aggregated by hour for performance
CREATE TABLE IF NOT EXISTS traffic_stats_hourly (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  hour_timestamp TIMESTAMP NOT NULL,
  bytes_in BIGINT DEFAULT 0,
  bytes_out BIGINT DEFAULT 0,
  application_type VARCHAR(64) DEFAULT NULL,
  country_code VARCHAR(2) DEFAULT NULL,
  UNIQUE KEY uniq_tenant_user_hour_app (tenant_id, user_id, hour_timestamp, application_type),
  INDEX idx_tenant_hour (tenant_id, hour_timestamp),
  CONSTRAINT fk_stats_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_stats_user
    FOREIGN KEY (user_id) REFERENCES vpn_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email templates and settings
CREATE TABLE IF NOT EXISTS email_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  is_default TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email sending logs
CREATE TABLE IF NOT EXISTS email_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  email_to VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  status ENUM('pending','sent','failed') DEFAULT 'pending',
  error_message TEXT DEFAULT NULL,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  CONSTRAINT fk_email_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_user
    FOREIGN KEY (user_id) REFERENCES vpn_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User email addresses for certificate delivery
ALTER TABLE vpn_users ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER username;
ALTER TABLE vpn_users ADD INDEX idx_email (email);

-- Application classification rules
CREATE TABLE IF NOT EXISTS app_classification_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  pattern VARCHAR(255) NOT NULL, -- domain pattern or IP range
  application_type VARCHAR(64) NOT NULL,
  priority INT DEFAULT 100,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pattern (pattern),
  INDEX idx_application (application_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default email template
INSERT INTO email_templates (name, subject, body, is_default) VALUES 
('Certificate Delivery', 'Your OpenVPN Certificate - {tenant_name}', 
'Dear {username},

Your OpenVPN certificate has been generated and is attached to this email.

Connection Details:
- Server: {server_ip}:{server_port}
- Tenant: {tenant_name}

Please keep this certificate secure and do not share it with others.

Best regards,
OpenVPN Manager', 1);

-- Insert default application classification rules
INSERT INTO app_classification_rules (name, pattern, application_type, priority) VALUES
('Google Search', 'google.com', 'search', 10),
('YouTube', 'youtube.com', 'video', 10),
('YouTube CDN', 'googlevideo.com', 'video', 10),
('Facebook', 'facebook.com', 'social', 10),
('Instagram', 'instagram.com', 'social', 10),
('Twitter', 'twitter.com', 'social', 10),
('Gmail', 'gmail.com', 'email', 10),
('Outlook', 'outlook.com', 'email', 10),
('Hotmail', 'hotmail.com', 'email', 10),
('Netflix', 'netflix.com', 'streaming', 10),
('Amazon Prime', 'amazon.com', 'streaming', 10),
('WhatsApp', 'whatsapp.com', 'messaging', 10),
('Telegram', 'telegram.org', 'messaging', 10),
('Discord', 'discord.com', 'messaging', 10),
('GitHub', 'github.com', 'development', 10),
('Stack Overflow', 'stackoverflow.com', 'development', 10);

-- IMPORTANT:
-- Nu există niciun INSERT în tabela 'users' aici.
-- Prima autentificare se face prin /setup.php, care va crea primul admin.
