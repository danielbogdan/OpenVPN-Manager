# OpenVPN Manager

A comprehensive OpenVPN management system with multi-tenant support, client portal, and real-time analytics.

## Features

- **Multi-tenant VPN Management**: Create and manage multiple OpenVPN instances
- **Client Portal**: Self-service portal for tenants to manage their VPN users
- **Real-time Analytics**: Live connection monitoring and traffic analytics
- **User Management**: Create, revoke, and manage VPN certificates
- **Email Integration**: Send VPN configurations via email
- **Modern UI**: Dark-themed, responsive interface
- **Real-time Status**: Live updates of user connections and activity

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Git

### Installation

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd openvpn-manager
   ```

2. **Configure environment (optional):**
   ```bash
   cp .env.example .env
   # Edit .env file with your preferred settings
   ```

3. **Deploy with Docker Compose:**
   ```bash
   docker compose up -d --build
   ```

4. **Access the application:**
   - **Admin Panel**: http://localhost:808 (auto-detects admin IP)
   - **Client Portal**: http://localhost:808/client/login.php

### Default Configuration

- **Database**: MariaDB 11 with automatic schema initialization
- **Web Server**: Apache with PHP 8.2
- **Port**: 808 (maps to container port 80)
- **Timezone**: Europe/Bucharest (configurable via TZ environment variable)

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_ROOT_PASSWORD` | `rootpass` | MariaDB root password |
| `DB_NAME` | `openvpn_admin` | Database name |
| `DB_USER` | `openvpn` | Database user |
| `DB_PASS` | `openvpnpass` | Database password |
| `APP_KEY` | `change_me` | Application encryption key |
| `TZ` | `Europe/Bucharest` | Timezone |
| `OVPN_IMAGE` | `kylemanna/openvpn:latest` | OpenVPN Docker image |
| `DEFAULT_DNS1` | `1.1.1.1` | Primary DNS server |
| `DEFAULT_DNS2` | `9.9.9.9` | Secondary DNS server |

## Architecture

### Services

- **Web Container**: PHP 8.2 + Apache + Composer dependencies
- **Database Container**: MariaDB 11 with persistent storage
- **OpenVPN Containers**: Dynamic tenant-specific VPN instances

### Database Schema

- `users`: Admin users for the management panel
- `tenants`: VPN tenant configurations
- `client_users`: Client portal users (tenant-specific)
- `vpn_users`: VPN certificate users
- `sessions`: Active VPN connections
- `traffic_logs`: Traffic monitoring data
- `email_templates`: Email configuration templates

### File Structure

```
├── actions/           # API endpoints
├── lib/              # PHP classes and libraries
├── public/           # Web-accessible files
│   ├── assets/       # CSS, JS, and static files
│   ├── client/       # Client portal pages
│   └── *.php         # Admin panel pages
├── scripts/          # Utility scripts
├── vendor/           # Composer dependencies
├── docker-compose.yml
├── Dockerfile
├── db.sql           # Database schema
└── config.php       # Application configuration
```

## Usage

### Admin Panel

1. **Access**: Navigate to http://localhost:808
2. **Login**: Use admin credentials (create via setup)
3. **Create Tenants**: Add new VPN instances
4. **Manage Users**: Create VPN certificates and client accounts
5. **Monitor**: View real-time connections and analytics

### Client Portal

1. **Access**: Navigate to http://localhost:808/client/login.php
2. **Login**: Use tenant-specific credentials
3. **Manage VPN Users**: Create and manage VPN certificates
4. **View Analytics**: Monitor connection statistics
5. **Account Settings**: Update profile information

## Development

### Local Development

The application uses Docker bind mounts for development:

```bash
# Start development environment
docker compose up -d

# View logs
docker compose logs -f web

# Access container shell
docker exec -it ovpnadmin_web bash
```

### Dependencies

- **PHP 8.2+** with extensions: pdo, pdo_mysql, zip
- **Composer** for dependency management
- **Docker CLI** for OpenVPN container management
- **MariaDB 11** for data persistence

### Key Libraries

- **PHPMailer**: Email functionality
- **Monolog**: Logging
- **vlucas/phpdotenv**: Environment variable management

## Troubleshooting

### Common Issues

1. **Database Connection Errors**:
   - Check if MariaDB container is healthy: `docker ps`
   - Verify database credentials in `.env`

2. **OpenVPN Container Issues**:
   - Ensure Docker socket is mounted: `/var/run/docker.sock`
   - Check container logs: `docker logs <container-name>`

3. **Permission Issues**:
   - Verify file permissions: `chown -R www-data:www-data /var/www/html`
   - Check Docker socket permissions

### Logs

```bash
# Application logs
docker compose logs web

# Database logs
docker compose logs db

# Specific container logs
docker logs ovpnadmin_web
docker logs ovpnadmin_db
```

## Security

- Change default passwords in production
- Use strong `APP_KEY` for encryption
- Configure proper firewall rules
- Regular security updates
- Monitor access logs

## License

[Add your license information here]

## Support

[Add support information here]