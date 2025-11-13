# Environment Setup Reference

## Table of Contents
- [Prerequisites](#prerequisites)
- [Initial Setup](#initial-setup)
- [wp-env Configuration](#wp-env-configuration)
- [Docker Management](#docker-management)
- [Troubleshooting](#troubleshooting)

## Prerequisites

### Required Software

1. **Node.js** (v18 or later)
   ```bash
   node --version  # Check version
   ```

2. **npm** (comes with Node.js)
   ```bash
   npm --version  # Check version
   ```

3. **Docker Desktop**
   - [Download Docker Desktop](https://www.docker.com/products/docker-desktop)
   - Ensure Docker is running before using npm run env commands

4. **Composer** (for PHP dependencies)
   ```bash
   # Install via Homebrew (macOS)
   brew install composer

   # Or download directly
   curl -sS https://getcomposer.org/installer | php
   ```

5. **Git** with SSH key setup
   ```bash
   # Check SSH key
   ssh -T git@github.com
   ```

## Initial Setup

### Step-by-Step Installation

1. **Clone the repository:**
   ```bash
   git clone git@github.com:Automattic/wordpress-activitypub.git
   cd wordpress-activitypub
   ```

2. **Install JavaScript dependencies:**
   ```bash
   npm install
   ```

3. **Install PHP dependencies:**
   ```bash
   composer install
   ```

4. **Set up Git hooks:**
   ```bash
   npm run prepare
   ```

5. **Start WordPress environment:**
   ```bash
   npm run env-start
   ```

6. **Access WordPress:**
   - Frontend: http://localhost:8888
   - Admin: http://localhost:8888/wp-admin
   - Username: `admin`
   - Password: `password`

## wp-env Configuration

### Default Configuration

wp-env uses `.wp-env.json` for configuration:

```json
{
  "phpVersion": "8.0",
  "plugins": [ "." ],
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "WP_DEBUG_DISPLAY": true,
    "SCRIPT_DEBUG": true
  }
}
```

### Common Environment Commands

Available npm scripts:
- `npm run env-start` → Start environment
- `npm run env-stop` → Stop environment
- `npm run env-test` → Run PHPUnit tests
- `npm run env -- <command>` → Pass any wp-env command

```bash
# Start environment
npm run env-start

# Stop environment
npm run env-stop

# Restart environment
npm run env-stop
npm run env-start

# Run PHPUnit tests
npm run env-test

# Run WP-CLI commands
npm run env -- run cli wp user list
npm run env -- run cli wp plugin list

# Access MySQL
npm run env -- run cli wp db cli

# View logs
npm run env -- logs
```

### Passing Additional Parameters to wp-env

Use `npm run env --` to pass any wp-env command and its parameters:

```bash
# Run WP-CLI with additional arguments
npm run env -- run cli wp user create testuser test@example.com --role=editor

# View logs with follow flag
npm run env -- logs --follow

# Any wp-env command can be passed through
npm run env -- <wp-env-command> [options]
```

### Multiple WordPress Versions

Test with different WordPress versions:

```bash
# Update .wp-env.json
{
  "core": "WordPress/WordPress#6.4"
}
```

### Multiple PHP Versions

```bash
# In .wp-env.json
{
  "phpVersion": "7.4"
}
```

## Docker Management

### Container Information

```bash
# List running containers
docker ps

# View container logs
docker logs $(docker ps -q --filter name=wordpress)

# Access container shell
docker exec -it $(docker ps -q --filter name=wordpress) bash
```

### Resource Management

```bash
# Check Docker resource usage
docker system df

# Clean up unused resources
docker system prune -a
```

### Port Management

Default ports:
- WordPress: 8888
- MySQL: (mapped to random external port)

Change ports in `.wp-env.json`:
```json
{
  "port": 8080,
  "testsPort": 8081
}
```

## Troubleshooting

### Common Issues and Solutions

#### Docker Not Running

**Error:** "Docker is not running"

**Solution:**
```bash
# Start Docker Desktop application
open -a Docker  # macOS

# Wait for Docker to start, then retry
npm run env-start
```

#### Port Already in Use

**Error:** "Port 8888 is already in use"

**Solution:**
```bash
# Find process using port
lsof -i :8888  # macOS/Linux

# Kill the process
kill -9 <PID>

# Or use different port
npm run env-start -- --port=8889
```

#### Permission Denied

**Error:** "Permission denied" errors

**Solution:**
```bash
# Ensure Docker has permissions
sudo usermod -aG docker $USER  # Linux

# Restart terminal and retry
```

#### Slow Performance

**Issue:** wp-env runs slowly

**Solutions:**
1. Increase Docker resources:
   - Docker Desktop → Preferences → Resources
   - Increase CPUs and Memory

2. Use mutagen for file sync (macOS):
   ```bash
   brew install mutagen-io/mutagen/mutagen
   ```

3. Exclude node_modules from sync

#### Database Connection Errors

**Error:** "Error establishing database connection"

**Solution:**
```bash
# Restart containers
npm run env-stop
npm run env-start
```

#### Plugin Not Activated

**Issue:** ActivityPub plugin not active

**Solution:**
```bash
# Manually activate
npm run env -- run cli wp plugin activate activitypub

# Check activation
npm run env -- run cli wp plugin list --status=active
```

### Debugging wp-env

#### Enable Verbose Output

```bash
# Set debug environment variable
DEBUG=wp-env:* npm run env-start
```

### Advanced Configuration

#### Custom WordPress Configuration

Create `wp-config.php` additions:

```php
// In .wp-env.json
{
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "WP_DEBUG_DISPLAY": false,
    "SCRIPT_DEBUG": true,
    "WP_ENVIRONMENT_TYPE": "local",
    "WP_MEMORY_LIMIT": "256M"
  }
}
```

#### Mount Additional Directories

```json
{
  "mappings": {
    "wp-content/mu-plugins": "./mu-plugins",
    "wp-content/themes/custom": "./custom-theme"
  }
}
```

#### Multiple Test Sites

Run multiple instances:

```bash
# Start on different ports
WP_ENV_PORT=8888 npm run env-start  # Instance 1
WP_ENV_PORT=9999 npm run env-start  # Instance 2
```

### Performance Optimization

#### File Sync Optimization

For better performance on macOS:

1. **Use Docker Desktop's VirtioFS:**
   - Docker Desktop → Settings → General
   - Enable "Use the new Virtualization framework"
   - Enable "VirtioFS accelerated directory sharing"

2. **Limit watched files:**
   ```json
   {
     "excludePaths": [
       "node_modules",
       "vendor",
       ".git"
     ]
   }
   ```

#### Database Optimization

```bash
# Optimize database tables
npm run env -- run cli wp db optimize

# Clear transients
npm run env -- run cli wp transient delete --all
```

## Environment Variables

### Available Variables

- `WP_ENV_HOME` - wp-env home directory
- `WP_ENV_PORT` - WordPress port
- `WP_ENV_TESTS_PORT` - Tests port
- `WP_ENV_LIFECYCLE_SCRIPT` - Lifecycle script path

### Custom Environment Setup

Create `.env.local` for custom variables:

```bash
# .env.local
AP_TEST_TIMEOUT=10000
AP_DEBUG_MODE=true
```

Load in tests:
```php
if ( file_exists( '.env.local' ) ) {
    $dotenv = Dotenv\Dotenv::createImmutable( __DIR__ );
    $dotenv->load();
}
```
