# SyncMarks – Docker Compose Installation

This guide describes how to install SyncMarks using Docker Compose.

The installation consists of two containers:

- **SyncMarks** – web server and PHP application
- **MariaDB** – database

The application itself is provided as a Docker image. The database, configuration, and logs are stored outside the application container so that they persist across container updates and recreation.

---

# Requirements

The following are required:

- Docker
- Docker Compose v2
- A domain or reverse proxy configuration for SyncMarks
- An SMTP server for sending emails

The SMTP server can be operated by the hosting provider, an ISP, or an external mail provider.

SyncMarks does **not** require a local mail server inside the container.

---

# 1. Create the installation directory

For example:

```bash
mkdir -p /opt/docker/syncmarks
cd /opt/docker/syncmarks
```

The location can be changed if required.

---

# 2. Create the Compose file

Create a file `compose.yml` in the installation directory.

Example:

```yaml
services:
  syncmarks:
    image: ghcr.io/offerel/syncmarks-webapp:latest
    container_name: syncmarks
    restart: unless-stopped

    ports:
      - "127.0.0.1:9101:80"

    volumes:
      - ./config:/var/lib/syncmarks
      - ./logs/nginx:/var/log/nginx
      - ./logs/syncmarks:/var/log/syncmarks

    environment:
      TZ: Europe/Amsterdam
      DB_HOST: mariadb
      DB_USER: syncmarks
      DB_NAME: syncmarks
      DB_PASSWORD_FILE: /run/secrets/db_password
      SMTP_PASSWORD_FILE: /run/secrets/mail_password
      ENCKEY_FILE: /run/secrets/enckey
      SM_LOG: /var/log/syncmarks/syncmarks.log
      CONF: /var/lib/syncmarks

    secrets:
      - source: db_password
        target: /run/secrets/db_password
        uid: "65534"
        gid: "65534"
        mode: 0400

      - source: mail_password
        target: /run/secrets/mail_password
        uid: "65534"
        gid: "65534"
        mode: 0400

      - source: enckey
        target: /run/secrets/enckey
        uid: "65534"
        gid: "65534"
        mode: 0400

    depends_on:
      mariadb:
        condition: service_healthy

  mariadb:
    image: mariadb:11.8
    container_name: syncmarks-db
    restart: unless-stopped

    environment:
      MARIADB_ROOT_PASSWORD_FILE: /run/secrets/db_root_password
      MARIADB_DATABASE: syncmarks
      MARIADB_USER: syncmarks
      MARIADB_PASSWORD_FILE: /run/secrets/db_password

    secrets:
      - db_root_password
      - db_password

    volumes:
      - ./data/db:/var/lib/mysql

    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 5

secrets:
  db_password:
    environment: SM_DB_PASSWORD

  db_root_password:
    environment: SM_DB_ROOT_PASSWORD

  mail_password:
    environment: SM_SMTP_PASSWORD

  enckey:
    environment: SM_ENCKEY
```

---

# 3. Create the required directories

In the installation directory, create the directories used by Docker Compose:

```bash
mkdir -p config
mkdir -p logs/nginx
mkdir -p logs/syncmarks
mkdir -p data/db
```

The `config` directory must be writable by the PHP process.

PHP-FPM runs as UID/GID `65534` (`nobody`) inside the SyncMarks container.

Therefore:

```bash
chown 65534:65534 config
```

---

# 4. Configure the secrets

SyncMarks uses Docker Secrets for sensitive data.

The following secrets are required:

| Secret | Purpose |
|---|---|
| `SM_DB_PASSWORD` | Password of the SyncMarks database user |
| `SM_DB_ROOT_PASSWORD` | MariaDB root password |
| `SM_SMTP_PASSWORD` | SMTP password |
| `SM_ENCKEY` | Encryption key |

The values are **not stored in `compose.yml`**.

Instead, they are supplied to Docker Compose through a local `.env` file.

Example:

```dotenv
SM_DB_PASSWORD=YOUR_DATABASE_PASSWORD
SM_DB_ROOT_PASSWORD=YOUR_ROOT_PASSWORD
SM_SMTP_PASSWORD=YOUR_SMTP_PASSWORD
SM_ENCKEY=YOUR_ENCRYPTION_KEY
```

Protect the `.env` file from access by other users:

```bash
chmod 600 .env
```

The `.env` file must **never be published or committed to a Git repository**.

---

# 5. Generate the encryption key

`SM_ENCKEY` must contain a cryptographically secure random key.

On a system with openssl installed, the following command can be used:

```bash
openssl rand -hex 8
```

Copy the generated value into `.env`:

```dotenv
SM_ENCKEY=...
```

The encryption key must be kept permanently.

**Do not change the encryption key on an existing installation.** Existing encrypted data may no longer be decryptable if the key is changed.

The other secrets can be generated a similar way:

`openssl rand -base64 32 > secrets/db_root_password`  
`openssl rand -base64 32 > secrets/db_password`

The password for the used SMTP Account can be saved in `/run/secrets/mail_password`

Remember to chmod these files with `chmod 600`

---

# 6. Configure SMTP

SyncMarks send Mails on in some use cases. For this SyncMarks uses PHPMailer. TO make PHPMailer work, we save a setup, including secrets. SMTP credentials are entered during the initial SyncMarks setup.

The following information is required:

- SMTP server
- encryption method
- SMTP port
- username
- password
- sender address

Common configurations include:

### STARTTLS

```text
Encryption: STARTTLS
Port: 587
```

### SMTPS

```text
Encryption: SMTPS
Port: 465
```

The SMTP server must be reachable from the SyncMarks container.

A local mail server on the Docker host is not required.

---

# 7. Start the containers

After creating the `.env` file:

```bash
docker compose up -d
```

Check the container status:

```bash
docker compose ps
```

The following containers should be running:

```text
syncmarks
syncmarks-db
```

---

# 8. Access SyncMarks

By default, SyncMarks is only bound to localhost:

```text
127.0.0.1:9101
```

For a public installation, a reverse proxy is therefore required.

For example, a reverse proxy can forward:

```text
https://syncmarks.example.com
```

to:

```text
http://127.0.0.1:9101
```

HTTPS should be terminated at the reverse proxy. A reverse proxy to a subdirectory is also possible.

---

# 9. Initial setup

After starting the container, open the SyncMarks URL in your browser.

During the first launch, the SyncMarks installer will be displayed.

The installer collects the required database and SMTP and other settings.

After the installation has been completed, the configuration is stored in the persistent location:

```text
./config/config.inc.php
```

---

# 10. Persistent data

The following data is stored outside the SyncMarks application container:

```text
config/
    SyncMarks configuration

data/db/
    MariaDB database

logs/
    Nginx and SyncMarks logs
```

These directories **must not be deleted when updating or recreating the SyncMarks container**.

To update an image-based installation:

```bash
docker compose pull
docker compose up -d
```

---

# 11. Backups

For a complete backup, at least the following data should be backed up:

```text
config/
data/db/
```

The logs may also be backed up if required.

**The value of `SM_ENCKEY` must also be backed up.**

A backup without the corresponding encryption key may not be sufficient to restore encrypted SyncMarks data.

---

# 12. Uninstallation

The containers can be stopped and removed with:

```bash
docker compose down
```

This does **not** remove the persistent data stored in:

```text
config/
data/db/
logs/
```

If SyncMarks and all associated data should be removed completely, these directories can be deleted manually afterwards.

**This is irreversible.**

For example:

```bash
docker compose down
rm -rf config data/db logs
```

---

# 13. Troubleshooting

### Check container status

```bash
docker compose ps
```

### View SyncMarks logs

```bash
docker compose logs syncmarks
```

or:

```bash
tail -f logs/syncmarks/syncmarks.log
```

### View MariaDB logs

```bash
docker compose logs mariadb
```

### Test SMTP connectivity

Open a shell in the SyncMarks container:

```bash
docker compose exec syncmarks sh
```

The SMTP server can then be tested for network connectivity.

For example:

```bash
nc -vz SMTP-SERVER 587
```

Use the appropriate SMTP server and port for the configured mail provider.

