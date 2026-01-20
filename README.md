# Blog CMS Platform

<div align="center">

![CMS Version](https://img.shields.io/badge/version-0.1.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.3-purple.svg)
![Laravel](https://img.shields.io/badge/Laravel-11.0-red.svg)
![React](https://img.shields.io/badge/React-18-blue.svg)
![License](https://img.shields.io/badge/license-Polyform%20NonCommercial-green.svg)

**A modern, full-featured Content Management System built with Laravel 11, React 18, and TypeScript**

[Features](#-features) • [Quick Start](#-quick-start) • [Documentation](#-documentation) • [License](#-license) • [Support](#-support)

[Deutsch](#deutsche-dokumentation)

</div>

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Quick Start](#-quick-start)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [API Documentation](#-api-documentation)
- [Development](#-development)
- [Deployment](#-deployment)
- [License](#-license)
- [Support](#-support)
- [Changelog](#-changelog)

---

## 🎯 Overview

This Blog CMS is a professional, modular content management platform designed for modern web applications. It provides a complete backend API with Laravel 11 and a responsive React frontend with TypeScript.

### Key Highlights

- 🚀 **Modern Stack**: Built with the latest technologies (Laravel 11, React 18, TypeScript)
- 🔒 **Secure**: JWT-based authentication with Laravel Sanctum
- 📱 **Responsive**: Mobile-first design with Ant Design
- 🎨 **Customizable**: Extensible plugin system and theming
- 📊 **Analytics**: Built-in analytics and activity logging
- 🤖 **AI Integration**: Optional AI-powered content assistance
- 🌍 **Multi-language**: Support for multilingual content
- 🐳 **Docker Ready**: Complete containerization with Docker Compose

---

## ✨ Features

### Content Management
- ✅ **Posts & Pages**: Full CRUD with rich text editor (TinyMCE)
- ✅ **Categories & Tags**: Organize content with hierarchical categories and tags
- ✅ **Media Library**: Upload and manage images, videos, and files
- ✅ **Scheduled Publishing**: Schedule posts for future publication
- ✅ **Hidden Posts**: Hide content from public view while keeping it in database
- ✅ **Post Sharing**: Generate shareable links with analytics tracking

### User Management
- ✅ **Role-Based Access Control**: Admin, Editor, Author, and Contributor roles
- ✅ **Role Hierarchy**: Granular permissions and user management
- ✅ **Two-Factor Authentication**: Enhanced security with 2FA support
- ✅ **User Profiles**: Customizable user profiles and settings

### SEO & Marketing
- ✅ **SEO Optimization**: Meta tags, sitemap generation, robots.txt management
- ✅ **Newsletter System**: Built-in newsletter management and subscriber tracking
- ✅ **Analytics**: Track page views, user engagement, and content performance
- ✅ **Comments**: Manage user comments with moderation tools

### System Features
- ✅ **Plugin System**: Extensible architecture for custom functionality
- ✅ **Activity Logging**: Comprehensive audit trail for all actions
- ✅ **System Health Monitoring**: Real-time system status and performance metrics
- ✅ **Backup Management**: Automated backup and restore functionality
- ✅ **Search**: Full-text search with advanced filtering
- ✅ **Downloads**: Manage downloadable files with access control

### AI Integration (Optional)
- ✅ **Content Generation**: AI-powered content suggestions and generation
- ✅ **Smart Summaries**: Automatic content summarization
- ✅ **SEO Optimization**: AI-generated meta descriptions and keywords
- ✅ **Content Ideas**: AI-powered topic and content suggestions

---

## 🛠 Tech Stack

### Backend
- **Framework**: Laravel 11 (PHP 8.3)
- **Database**: PostgreSQL 16
- **Cache/Queue**: Redis 7
- **Authentication**: Laravel Sanctum (JWT)
- **API**: RESTful

### Frontend
- **Framework**: React 18.3 with TypeScript
- **State Management**: Zustand
- **UI Library**: Ant Design 5.x
- **Build Tool**: Vite 5.x
- **Rich Text Editor**: TinyMCE
- **Routing**: React Router 6.x
- **HTTP Client**: Axios

### DevOps
- **Containerization**: Docker & Docker Compose
- **Web Server**: nginx (Alpine)
- **Reverse Proxy**: nginx for API and frontend routing
- **Process Manager**: PHP-FPM

---

## 🚀 Quick Start

### Prerequisites

- Docker and Docker Compose
- Git

### One-Line Setup

```bash
git clone <repository-url>
cd cms-php
docker compose up -d
docker exec cms-backend php artisan migrate --force
docker exec cms-backend php artisan db:seed --force
```

### Access the Application

- **Frontend**: http://localhost/
- **Admin Login**: http://localhost/login
- **API Health**: http://localhost/api/v1/health

### Default Credentials

```
Email: admin@example.com
Password: password
```

> ⚠️ **Important**: Change the default password after first login!

---

## 📦 Installation

### Step 1: Clone Repository

```bash
git clone <repository-url>
cd cms-php
```

### Step 2: Environment Configuration

The backend `.env` file is pre-configured for Docker. Adjust if needed:

```env
# Application
APP_NAME="Blog CMS"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=pgsql
DB_HOST=database
DB_PORT=5432
DB_DATABASE=cms_db
DB_USERNAME=cms_user
DB_PASSWORD=cms_password
```

### Step 3: Build and Start Containers

```bash
docker compose build
docker compose up -d
```

### Step 4: Run Migrations

```bash
docker exec cms-backend php artisan migrate --force
docker exec cms-backend php artisan db:seed --force
```

---

## ⚙️ Configuration

### Environment Variables

See `.env` file in the backend directory for all available configuration options.

### Frontend Configuration

The frontend uses Vite with environment variables. See `vite.config.ts` for proxy configuration.

---

## 📖 API Documentation

### Authentication Endpoints

```http
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

### Example Request

```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

### Main Endpoints

- **Posts**: `GET|POST|PUT|DELETE /api/v1/posts`
- **Categories**: `GET|POST|PUT|DELETE /api/v1/categories`
- **Tags**: `GET|POST|PUT|DELETE /api/v1/tags`
- **Media**: `GET|POST|PUT|DELETE /api/v1/media`
- **Users**: `GET|POST|PUT|DELETE /api/v1/users`
- **Settings**: `GET|PUT /api/v1/settings`
- **And many more...**

---

## 💻 Development

### Local Development Setup

```bash
# Frontend (Terminal 1)
cd frontend
npm install
npm run dev

# Backend (Terminal 2)
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### Code Structure

```
cms-php/
├── backend/                 # Laravel Backend
│   ├── app/
│   │   ├── Http/           # Controllers, Middleware
│   │   ├── Models/         # Eloquent Models
│   │   └── Services/       # Business Logic
│   ├── database/
│   │   ├── migrations/     # Database Migrations
│   │   └── seeders/        # Database Seeders
│   └── routes/             # API Routes
│
├── frontend/               # React Frontend
│   ├── src/
│   │   ├── components/     # Reusable Components
│   │   ├── pages/          # Page Components
│   │   ├── services/       # API Services
│   │   ├── store/          # State Management
│   │   └── types/          # TypeScript Types
│
├── nginx/                  # nginx Configuration
└── docker-compose.yml     # Docker Services
```

---

## 🧪 Testing

```bash
# Backend Tests
docker exec cms-backend php artisan test

# Frontend Tests
cd frontend
npm test
```

---

## 🚀 Deployment

### Production Deployment

1. Update `.env` for production
2. Build containers: `docker compose build`
3. Start services: `docker compose up -d`
4. Run optimizations: `php artisan config:cache`

---

## 📄 License

This project is licensed under the **Polyform NonCommercial 1.0.0 License**.

### What this means

**✅ ALLOWED without permission:**
- Personal use
- Educational use
- Open source development
- Modification and customization
- Distribution for free

**❌ REQUIRES COMMERCIAL LICENSE:**
- Selling the software or services
- Using in revenue-generating applications
- SaaS integration
- Enterprise production use
- Reselling for profit

See [LICENSE](LICENSE) for the full license text.

### Commercial License

For commercial use licenses, please contact:
- Email: [your-email@example.com]
- Website: [https://your-website.com]

---

## 🆘 Support

- **Issues**: Report bugs on GitHub Issues
- **Discussions**: Join community discussions
- **Email**: [support@example.com]

---

## 📝 Changelog

### Version 0.1.0 (2026-01-20)

**Added:**
- Initial release
- Complete CMS backend with Laravel 11
- React 18 frontend with TypeScript
- User authentication with JWT
- Posts, pages, categories, tags management
- Media library
- Newsletter system
- SEO tools
- Plugin system
- Activity logging
- AI integration capabilities
- Docker-based deployment

---

## 🙏 Acknowledgments

Built with amazing open-source tools:
- [Laravel](https://laravel.com)
- [React](https://react.dev)
- [Ant Design](https://ant.design)
- [Vite](https://vitejs.dev)
- [PostgreSQL](https://www.postgresql.org)

---

<div align="center">

**Built with ❤️ using Laravel & React**

[⬆ Back to Top](#blog-cms-platform)

</div>

---

## 📚 Deutsche Dokumentation

### Übersicht

Eine selbst-gehostete, moderne Blog/CMS-Plattform mit API-First-Architektur.

### Schnellstart mit Docker

```bash
git clone <repository-url>
cd cms-php
docker compose up -d
docker exec cms-backend php artisan migrate --force
docker exec cms-backend php artisan db:seed --force
```

### Zugriff

- **Frontend**: http://localhost/
- **Admin Login**: http://localhost/login
- **Standard-Zugang**:
  - Email: `admin@example.com`
  - Passwort: `password`

### Docker Services

| Service | Port | Beschreibung |
|---------|------|-------------|
| Frontend | 80 | React App (via nginx) |
| Backend | 9000 | Laravel PHP-FPM |
| Database | 5432 | PostgreSQL |
| Redis | 6379 | Cache/Queue |

### Nützliche Befehle

```bash
# Container Status
docker compose ps

# Logs ansehen
docker compose logs -f backend

# Migrationen ausführen
docker compose exec backend php artisan migrate

# In Backend Shell
docker compose exec backend sh
```

### Lizenz (Deutsch)

Dieses Projekt ist unter der **Polyform NonCommercial 1.0.0 Lizenz** veröffentlicht.

**✅ ERLAUBT ohne Erlaubnis:**
- Persönliche Nutzung
- Bildungszwecke
- Open Source Entwicklung
- Modifikation und Anpassung
- Kostenlose Verbreitung

**❌ ERFORDERT KOMMERZIELLE LIZENZ:**
- Verkauf der Software oder Dienstleistungen
- Nutzung in Umsatz generierenden Anwendungen
- SaaS Integration
- Unternehmensproduktion
- Weiterverkauf für Profit

Für kommerzielle Lizenzen kontaktieren Sie bitte:
- Email: [your-email@example.com]

---

<div align="center">

**Mit ❤️ entwickelt mit Laravel & React**

</div>
