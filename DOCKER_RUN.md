# QloApps Development Guide
*August 6, 2025*

## Tech Stack
- Docker
- QloApps Docker Image (PHP) 
- MySQL Server

## 1. Running Locally

### 1.1 Start the docker services
Run the command `sh start-dev.sh`, this will start Docker services: custom QloApps image and MySQL database, once up configure the server.

**Notes:**
Dev mode will support hot-reloading with Docker container but you will need to be careful of not committing changes in the different modules: install, mail, etc. Only commit the changes needed in the core of QloApps application.

### 1.2 Configure the server
Navigate to `http://localhost:8080/install/` and proceed with the wizard

**Database Configuration:**
- Host: mysql
- User: qloapps
- Password: qloapps123

After installation run the following command `sh post-install.sh` to remove the install and rename the admin directories. This command supports a flag for the prefix depending on the docker instance you are running, this appends a prefix -dev to match the docker name

### 1.3 Developer changes
By default the tracked changes for development are modules, themes and override. Edit the docker-compose file to support hot-reload.
