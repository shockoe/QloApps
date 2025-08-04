# QloApps Docker Setup

This Docker setup provides a complete QloApps hotel booking system with your custom code changes, including the new availability search API functionality.

## ✅ Problem Fixed: Install Directory

The "install directory missing" error has been resolved. The Docker image now includes the `install/` directory required for QloApps installation.

## Quick Start

### Start Services
```bash
./start-docker.sh
```

### Stop Services
```bash
./stop-docker.sh
```

## Services

### 🌐 QloApps Web Application
- **URL**: http://localhost:8080
- **Container**: `qloapps-booking-system`
- **Image**: Custom built from your project code
- **Features**: All your custom changes including the new availability API

### 🗄️ MySQL Database
- **Host**: localhost:3307
- **Container**: `qloapps-mysql`  
- **Image**: mysql:5.7
- **Database**: `qloapps_hotels`
- **Username**: `qloapps`
- **Password**: `qloapps123`
- **Root Password**: `qloapps_root`

### 🔧 SSH Access
- **Host**: localhost:2222
- **Username**: `qloapps`
- **Password**: `qloapps123`

## Installation Process

1. **Start Services**: Run `./start-docker.sh`
2. **Access QloApps**: Go to http://localhost:8080
3. **Install QloApps**: Follow the installation wizard
4. **Database Configuration**:
   - Server: `mysql` (container name)
   - Port: `3306` (internal port)
   - Database: `qloapps_hotels`
   - Username: `qloapps`
   - Password: `qloapps123`

## Manual Docker Commands

### Build and Start
```bash
docker compose build
docker compose up -d
```

### Check Status
```bash
docker compose ps
docker compose logs -f
```

### Stop and Clean
```bash
docker compose down
```

### Database Access
```bash
# Access MySQL via command line
docker exec -it qloapps-mysql mysql -u qloapps -pqloapps123 qloapps_hotels

# Access as root
docker exec -it qloapps-mysql mysql -u root -pqloapps_root
```

### SSH into Container
```bash
# SSH into QloApps container
docker exec -it qloapps-booking-system bash

# SSH via external port
ssh qloapps@localhost -p 2222
```

## File Structure

```
qloapps-prestashop/
├── docker-compose.yml          # Service definitions
├── Dockerfile                  # QloApps container build
├── .env                        # Environment variables
├── .dockerignore              # Files to exclude from build
├── docker/
│   ├── supervisord.conf       # Process management
│   └── credentials.sh         # Setup script
├── start-docker.sh           # Convenience start script
├── stop-docker.sh            # Convenience stop script
└── DOCKER_README.md          # This file
```

## Data Persistence

### Volumes
- `qloapps_mysql_data`: MySQL database files
- `qloapps_uploads`: QloApps uploaded files
- `qloapps_cache`: QloApps cache files

### Backup Database
```bash
docker exec qloapps-mysql mysqldump -u root -pqloapps_root qloapps_hotels > backup.sql
```

### Restore Database
```bash
docker exec -i qloapps-mysql mysql -u root -pqloapps_root qloapps_hotels < backup.sql
```

## Troubleshooting

### Services Won't Start
```bash
# Check Docker is running
docker info

# Check for port conflicts
lsof -i :8080
lsof -i :3307
lsof -i :2222

# View detailed logs
docker compose logs qloapps
docker compose logs mysql
```

### Reset Everything
```bash
# Stop and remove all containers, networks, and volumes
docker compose down -v

# Remove images (forces rebuild)
docker rmi qloapps-prestashop-qloapps mysql:5.7

# Restart from scratch
./start-docker.sh
```

### Permission Issues
```bash
# Fix file permissions inside container
docker exec qloapps-booking-system chown -R qloapps:qloapps /home/qloapps/www/hotelcommerce
```

## Development Workflow

1. **Make Code Changes**: Edit files in your project
2. **Rebuild**: `docker compose build qloapps`
3. **Restart**: `docker compose up -d`
4. **Test**: Visit http://localhost:8080

## Environment Variables

Create a `.env` file to customize settings:

```bash
# Database
QLOAPPS_MYSQL_ROOT_PASSWORD=your_root_password
QLOAPPS_DATABASE=your_database_name
MYSQL_USER=your_username
MYSQL_PASSWORD=your_password

# Ports
QLOAPPS_WEB_PORT=8080
QLOAPPS_MYSQL_PORT=3307
QLOAPPS_SSH_PORT=2222

# QloApps
QLOAPPS_USER_PASSWORD=your_ssh_password
```

## Next Steps

After installation:
1. Remove the `install/` directory for security
2. Configure your hotel settings
3. Test the new availability search API
4. Set up SSL certificates for production
5. Configure backups and monitoring

## Support

For QloApps specific issues: https://qloapps.com/support/
For Docker issues: Check logs with `docker compose logs -f`