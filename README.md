<div align="center">
  <br />
  <h1>🚀 GoBeyond API</h1>
  <strong> Laravel REST API powering the GoBeyond Gmail Integration Dashboard.</strong><br />
  <br />

  [![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg?style=flat&logo=php)]()
  [![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20.svg?style=flat&logo=laravel)]()
  [![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1.svg?style=flat&logo=mysql)]()
  [![Docker](https://img.shields.io/badge/Docker-Ready-2496ED.svg?style=flat&logo=docker)]()
</div>

<br />

## 📖 Overview

The GoBeyond API serves as the robust, highly-performant backend for the GoBeyond SaaS platform. Built on Laravel 11, it provides seamless Google OAuth 2.0 authentication, robust integration with the Gmail API, and real-time asynchronous data synchronization. 

It is designed for scale—featuring aggressive caching, background queue processing for heavy sync jobs, and a fully Dockerized production environment utilizing Nginx and PHP-FPM.

---

## ✨ Key Features

- **🔐 Secure Google OAuth 2.0**: Stateless, session-free authentication integrated directly with Google.
- **⚡ Background Email Synchronization**: Long-running Gmail syncs are offloaded to background queues to guarantee instant UI response times.
- **📩 Real-time Reply Processing**: Instant injection of sent replies back into the local database without waiting for the next polling cycle.
- **📊 Metric Aggregation**: Efficient single-query dashboard statistics using optimized Laravel Eloquent scopes.
- **🐳 Production Ready Docker**: Includes a multi-process Docker architecture with `supervisord`, seamlessly handling web traffic (Nginx/PHP-FPM) and background workers simultaneously.

---

## 🛠 Tech Stack

* **Framework:** Laravel 11.x
* **Language:** PHP 8.2+
* **Database:** MySQL
* **Queue Driver:** Database 
* **Deployment Infrastructure:** Nginx, PHP-FPM, Docker Base (Alpine)
* **External APIs:** Google Cloud (Gmail API)

---

## 🚦 Local Development

### Prerequisites
- PHP 8.2+
- Composer
- MySQL Database
- Google Cloud Console Project (with Gmail API enabled and OAuth credentials)

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/codekid69/gobeyond-api.git
   cd gobeyond-api
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure the environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Fill in your database credentials and `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` in the `.env` file.*

4. **Run database migrations:**
   ```bash
   php artisan migrate
   ```

5. **Start the local server:**
   ```bash
   # Run the API server
   php artisan serve

   # In a separate terminal, run the queue worker for background email syncing
   php artisan queue:work database --timeout=120
   ```

---

## 🐳 Docker Deployment (Production)

This repository includes a highly optimized Dockerfile designed for zero-configuration deployments on platforms like Render, Railway, or AWS ECS. 

The Docker container runs **Supervisord** as the entrypoint, which simultaneously manages:
1. `nginx` (High concurrency web server)
2. `php-fpm` (PHP processor)
3. `php artisan queue:work` (Background sync queue processor)

### Building and Running
```bash
docker build -t gobeyond-api .
docker run -p 8000:80 --env-file .env gobeyond-api
```

---

## 📚 Core API Routes

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `POST` | `/api/connect-gmail` | Generates the Google OAuth 2.0 consent URL |
| `GET` | `/api/gmail-callback` | Handles the Google OAuth redirect and issues local access tokens |
| `GET` | `/api/auth-status` | Verifies the current user's session and connection state |
| `POST` | `/api/sync-emails` | Dispatches a background queue job to sync X days of emails |
| `GET` | `/api/sync-status` | Polls the current progress of the active sync job |
| `GET` | `/api/emails` | Retrieves the paginated list of synced email threads |
| `POST` | `/api/reply-email` | Sends an email reply via the Gmail API and syncs it locally |

---

<div align="center">
  <sub>Built with ❤️.</sub>
</div>
