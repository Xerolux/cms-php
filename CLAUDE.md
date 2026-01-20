# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a self-hosted, GDPR-compliant Blog/CMS platform with an API-First architecture. The project uses a modern tech stack optimized for performance, scalability, and AI integration.

**Tech Stack:**
- **Backend:** PHP 8.2+ with Laravel 11
- **Frontend:** React 18 with TypeScript and Vite
- **Database:** PostgreSQL 15+ (MySQL/MariaDB also supported)
- **API:** RESTful with Sanctum authentication
- **Cache/Queue:** Redis
- **Containerization:** Docker

**Key Design Principles:**
- API-First architecture for seamless AI integration
- RBAC (Role-Based Access Control) system
- Secure, token-based downloads (temporary, one-time URLs)
- GDPR-compliant with built-in privacy features
- SEO-optimized with meta tags and structured data

## Development Setup

### Starting the Development Environment

1. **Choose and start database** (Docker required):
```bash
# PostgreSQL (recommended)
docker compose --profile postgres up -d

# MySQL
docker compose --profile mysql up -d

# MariaDB
docker compose --profile mariadb up -d
```

2. **Backend Setup**:
```bash
cd backend
composer install
cp .env.example .env
# Edit .env with database configuration (see "Database Configuration" below)
php artisan key:generate
php artisan migrate
php artisan serve  # Starts on http://127.0.0.1:8000
```

3. **Frontend Setup**:
```bash
cd frontend
npm install
npm run dev  # Starts on http://127.0.0.1:5173
```

### Database Configuration

Configure `backend/.env` based on your chosen database:

**PostgreSQL:**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cms_db
DB_USERNAME=cms_user
DB_PASSWORD=secret
```

**MySQL/MariaDB:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cms_db
DB_USERNAME=cms_user
DB_PASSWORD=secret
```

### Common Development Commands

**Backend (Laravel):**
```bash
cd backend

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Create new migration
php artisan make:migration create_table_name

# Seed database
php artisan db:seed

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Generate IDE helper
php artisan ide-helper:generate
```

**Frontend (React):**
```bash
cd frontend

# Development server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview

# Lint code
npm run lint

# Fix linting issues
npm run lint:fix
```

## Architecture

### Directory Structure

```
cms-php/
├── backend/                    # Laravel Backend
│   ├── app/
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   │       └── Api/
│   │   │           └── V1/     # API Controllers (versioned)
│   │   ├── Models/            # Eloquent Models
│   │   └── Services/          # Business logic services
│   ├── database/
│   │   ├── migrations/        # Database migrations
│   │   └── seeders/           # Seed data
│   ├── routes/
│   │   ├── api.php            # API routes
│   │   └── web.php            # Web routes
│   └── config/                # Configuration files
├── frontend/                  # React Frontend
│   ├── src/
│   │   ├── components/        # React components
│   │   ├── pages/            # Page components
│   │   ├── hooks/            # Custom React hooks
│   │   ├── services/         # API service layer
│   │   ├── store/            # State management (Zustand)
│   │   └── types/            # TypeScript type definitions
│   └── public/               # Static assets
└── docs/                     # Documentation
```

### Backend Architecture

**API Versioning:**
- All API routes are prefixed with `/api/v1/`
- Controllers are namespaced under `App\Http\Controllers\Api\V1`
- Future versions can be added as `V2`, `V3`, etc.

**Key Models & Relationships:**

- **User**: Roles (super_admin, admin, editor, author, contributor, subscriber)
- **Post**: Articles with status (draft, scheduled, published, archived)
  - BelongsTo: User (author), Media (featured_image)
  - BelongsToMany: Category, Tag, Download
- **Category**: Hierarchical structure (parent_id self-reference)
- **Tag**: Simple tags with usage_count
- **Media**: File uploads with image metadata (width, height, alt_text)
- **Download**: Secure files with access levels
- **DownloadToken**: Temporary tokens (1-hour expiry, single-use)
- **Advertisement**: Ad management for monetization

**Authentication:**
- Laravel Sanctum for API authentication
- JWT tokens with refresh capability
- Routes protected with `auth:sanctum` middleware

**API Response Format:**
- Controllers return JSON responses
- Pagination uses Laravel's built-in paginator
- Related entities loaded via eager loading (`with()`)

### Frontend Architecture

**State Management:**
- Zustand for global state
- Context API for component-level state
- React Query for server state (planned)

**Routing:**
- React Router v6 for navigation
- Lazy loading for code splitting

**API Integration:**
- Axios with interceptors for JWT handling
- Centralized API service in `src/services/api.ts`
- Automatic token refresh on 401 responses

## Key Features & Implementation Details

### Secure Downloads

The system implements a secure download mechanism with temporary, one-time URLs:

1. **Token Generation**: When a download page is accessed, a new token is generated
2. **Token Properties**:
   - Valid for 1 hour (configurable)
   - Single-use only (marked as used after download)
   - Optionally bound to user session/IP
3. **URL Format**: `/dl/{token}` (public route without auth)
4. **Controller**: `DownloadController::download()`

### Media Management

- **Supported formats**: JPG, PNG, WEBP, GIF, SVG, MP4, WEBM, PDF
- **Automatic processing**: Compression, WebP conversion, thumbnail generation
- **Storage**: Year/month-based organization (`storage/media/YYYY/MM/`)
- **Bulk upload**: `POST /api/v1/media/bulk-upload`

### Content Workflow

**Post Statuses:**
- `draft`: Initial state, not visible publicly
- `scheduled`: Set for future publication (published_at date required)
- `published`: Live and accessible
- `archived`: Removed from public view but preserved

**Post Relationships:**
- Multiple categories per post (many-to-many)
- Multiple tags per post (many-to-many)
- Multiple downloads per post (many-to-many)
- One featured image (many-to-one)

### Role-Based Access Control (RBAC)

**User Roles:**
- `super_admin`: Full system access
- `admin`: All content and users (except super_admin)
- `editor`: Edit all posts
- `author`: Create/edit own posts
- `contributor`: Create drafts (requires approval)
- `subscriber`: Read-only access for member areas

### SEO Features

- Meta title and description per post
- Slug generation from titles (auto-generated, editable)
- Structured data support (Schema.org)
- Sitemap generation (planned)
- Canonical URLs (planned)

### Multilingual Support

**Current Implementation:**
- Language field on posts (`de`, `en`)
- Translation relationship (`translation_of_id` for linked translations)

**Planned:**
- Admin interface language switcher
- URL-based language routing (`/de/`, `/en/`)
- hreflang tags for SEO

## API Endpoints

**Base URL:** `http://127.0.0.1:8000/api/v1`

**Public Endpoints:**
- `GET /health` - Health check
- `POST /auth/login` - User authentication
- `GET /dl/{token}` - Secure download via token

**Protected Endpoints** (require authentication):
- `POST /auth/refresh` - Refresh JWT token
- `GET /auth/me` - Get current user

**Content Management:**
- `GET /posts` - List posts (with filtering: status, category_id, tag_id, search)
- `POST /posts` - Create post
- `GET /posts/{id}` - Get single post (by ID or slug)
- `PUT /posts/{id}` - Update post
- `DELETE /posts/{id}` - Delete post
- `POST /posts/bulk` - Bulk create posts
- `DELETE /posts/bulk` - Bulk delete posts

**Categories & Tags:**
- `GET /categories` - List categories
- `POST /categories` - Create category
- `PUT /categories/{id}` - Update category
- `DELETE /categories/{id}` - Delete category
- (Same pattern for tags: `/tags`)

**Media:**
- `GET /media` - List media
- `POST /media` - Upload media
- `POST /media/bulk-upload` - Bulk upload
- `PUT /media/{id}` - Update media metadata
- `DELETE /media/{id}` - Delete media

**Downloads:**
- `GET /downloads` - List downloads
- `POST /downloads` - Upload download file
- `DELETE /downloads/{id}` - Delete download

**Users:**
- `GET /users` - List users
- `POST /users` - Create user
- `PUT /users/{id}` - Update user
- `DELETE /users/{id}` - Delete user

**Ads:**
- `GET /ads` - List advertisements
- `POST /ads` - Create ad
- `PUT /ads/{id}` - Update ad
- `DELETE /ads/{id}` - Delete ad

## Testing

**Backend (PHPUnit):**
```bash
cd backend
php artisan test  # Run all tests
php artisan test --filter PostTest  # Run specific test
```

**Frontend (Vitest):**
```bash
cd frontend
npm test  # Run all tests
npm run test:ui  # Run with UI
```

## Development Guidelines

### Code Style

**Backend (PHP):**
- Follow PSR-12 coding standard
- Use Laravel's built-in conventions
- Type declarations where applicable
- Docblocks for complex methods

**Frontend (TypeScript):**
- Use functional components with hooks
- Prefer composition over inheritance
- TypeScript strict mode enabled
- Proper typing for props and state

### File Organization

**Creating new API endpoints:**
1. Add migration for database changes
2. Create/update Model in `backend/app/Models/`
3. Create controller in `backend/app/Http/Controllers/Api/V1/`
4. Add routes in `backend/routes/api.php`
5. Create corresponding TypeScript types in `frontend/src/types/`
6. Create API service methods in `frontend/src/services/`

**Database changes:**
- Always use migrations, never modify schema directly
- Rollback-safe migrations
- Add indexes for frequently queried columns
- Use foreign key constraints for relationships

### Security Considerations

**Validation:**
- All input must be validated using Form Request or controller validation
- File uploads require MIME type and size validation
- Sanitize user input to prevent XSS

**Authentication:**
- All protected routes require `auth:sanctum` middleware
- API routes should check user permissions for sensitive operations
- Implement rate limiting for public endpoints

**Downloads:**
- Never expose download files directly in `/storage/public/`
- Always use token-based access
- Validate file access permissions before serving

### Performance Optimization

**Backend:**
- Use eager loading (`with()`) to prevent N+1 queries
- Implement caching for frequently accessed data
- Use database indexes for search and filter queries
- Queue heavy operations (image processing, notifications)

**Frontend:**
- Implement code splitting with React.lazy()
- Use pagination for large lists
- Cache API responses with React Query
- Optimize images (WebP, lazy loading)

## Current Status & Known Issues

**Implemented:**
- ✅ Complete REST API with all CRUD operations
- ✅ Database schema with all models and migrations
- ✅ Authentication system with Sanctum
- ✅ Secure download system with token-based URLs
- ✅ Media upload with bulk operations
- ✅ Category and tag management
- ✅ Post management with filtering
- ✅ Role-based user system
- ✅ Advertisement management

**Pending (see docs/work-log.md for priority):**
- ⏳ Docker container issue (backend app container not starting)
- ⏳ Frontend UI implementation (React components)
- ⏳ Testing suite setup
- ⏳ Search functionality
- ⏳ Analytics tracking
- ⏳ Static pages management
- ⏳ Settings management
- ⏳ Backup/restore functionality
- ⏳ Cookie consent banner
- ⏳ Rate limiting implementation
- ⏳ Security headers configuration

## Documentation

- **Requirements**: `Blog-CMS-Software-Requirements.md` - Comprehensive feature specifications
- **Work Log**: `docs/work-log.md` - Development progress and next steps
- **Database Guide**: `docs/database-selection-guide.md`
- **Feature Guides**: Various files in `docs/` for specific features

## Deployment Notes

**Environment Variables Required:**
- Database connection (DB_* variables)
- Redis configuration (REDIS_*)
- APP_KEY (Laravel encryption key)
- APP_URL (base URL for the application)
- Mail configuration for notifications
- Storage configuration (local or S3-compatible)

**Security Checklist Before Deployment:**
- Set `APP_ENV=production` and `APP_DEBUG=false`
- Configure HTTPS and HSTS headers
- Set up proper CORS configuration
- Configure rate limiting
- Set up database backups
- Configure queue workers for background jobs
- Set up monitoring and logging
