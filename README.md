# MyBB Forum — Railway Deployment

MyBB 1.8.39 in a single Docker container (Apache + PHP 8.3), ready to deploy on Railway.

## Railway Deploy Steps

### 1. Create MySQL Service
- Go to your Railway project
- Click **New Service** → **Database** → **MySQL**
- Wait for it to provision (takes ~30 seconds)
- Note the connection variables it creates (MYSQL_HOST, MYSQL_PORT, etc.)

### 2. Deploy MyBB
- Click **New Service** → **GitHub Repo** → select `nicalevras/mybb`
- Railway will detect the Dockerfile and build it

### 3. Add Volume
- Go to the MyBB service → **Settings** → **Volumes**
- Click **Add Volume**
- Mount path: `/var/www/html`
- This persists uploads, avatars, and config across deploys

### 4. Set Environment Variables
On the MyBB service, add these variables:

| Variable | Value |
|----------|-------|
| `DB_HOST` | Click "Add Reference" → select MySQL service → `MYSQL_HOST` |
| `DB_PORT` | Click "Add Reference" → select MySQL service → `MYSQL_PORT` |
| `DB_NAME` | `mybb` |
| `DB_USER` | Click "Add Reference" → select MySQL service → `MYSQL_USER` |
| `DB_PASSWORD` | Click "Add Reference" → select MySQL service → `MYSQL_PASSWORD` |

### 5. Create the Database
Before running the MyBB installer, create the database:
- Go to the MySQL service → **Query** tab (or connect via any MySQL client)
- Run: `CREATE DATABASE mybb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

### 6. Run MyBB Installer
- Railway will give you a public URL for the MyBB service
- Open it in your browser
- Follow the MyBB installation wizard
- When it asks for database details, use the variables from step 4

### 7. Generate Custom Domain (Optional)
- Go to MyBB service → **Settings** → **Networking**
- Click **Generate Domain** for a `*.up.railway.app` URL
- Or add a custom domain

## Port
The container listens on **port 80**. Railway auto-detects this.

## What's Included
- MyBB 1.8.39
- PHP 8.3 with gd, mysqli, pdo_mysql, opcache, zip, mbstring, xml, curl
- Apache with mod_rewrite
- OPcache pre-configured for performance

## What's NOT Included (intentionally)
- nginx (Apache handles both PHP and static files)
- Redis (not needed for a small-medium forum)
- Adminer (use Railway's MySQL query tab instead)
- Traefik (Railway handles routing)
