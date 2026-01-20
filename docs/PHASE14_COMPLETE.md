# Phase 14: Backup & Restore System - ABGESCHLOSSEN ✅

## Übersicht

Das komplette Backup & Restore System wurde erfolgreich implementiert! Admins können jetzt vollständige Backups erstellen (Datenbank + Dateien), herunterladen und bei Bedarf wiederherstellen. Das System unterstützt verschiedene Backup-Typen und automatische Bereinigung.

## Backend Implementierung

### 1. Database Migration

**Datei:** `backend/database/migrations/2024_01_20_000018_create_backups_table.php`

```php
Schema::create('backups', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('type'); // full, database, files
    $table->string('status'); // pending, creating, completed, failed
    $table->string('disk')->default('local');
    $table->string('path');
    $table->unsignedBigInteger('file_size')->nullable();
    $table->integer('items_count')->default(0);
    $table->text('description')->nullable();
    $table->json('options')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    $table->string('error_message')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

**Spalten:**
- `name` - Backup Name (optional, auto-generated wenn leer)
- `type` - Typ: full, database, files
- `status` - Status: pending, creating, completed, failed
- `disk` - Storage Disk (local, s3, etc.)
- `path` - Pfad zur Backup-Datei
- `file_size` - Dateigröße in Bytes
- `items_count` - Anzahl der gesicherten Elemente
- `description` - Beschreibung
- `options` - JSON Optionen
- `completed_at` - Fertigstellungs-Zeitpunkt
- `failed_at` - Fehler-Zeitpunkt
- `error_message` - Fehlermeldung
- `created_by` - Ersteller (User ID)

### 2. Backup Model

**Datei:** `backend/app/Models/Backup.php`

**Scopes:**
```php
public function scopePending($query)
{
    return $query->where('status', 'pending');
}

public function scopeCompleted($query)
{
    return $query->where('status', 'completed');
}

public function scopeFailed($query)
{
    return $query->where('status', 'failed');
}

public function scopeOfType($query, $type)
{
    return $query->where('type', $type);
}
```

**Accessors:**
```php
public function getFileSizeFormattedAttribute(): string
{
    return $this->formatBytes($this->file_size);
}

public function getDurationAttribute(): string
{
    if (!$this->completed_at || !$this->created_at) {
        return '-';
    }

    $duration = $this->completed_at->diffInSeconds($this->created_at);

    if ($duration < 60) {
        return $duration . 's';
    }

    return floor($duration / 60) . 'm ' . ($duration % 60) . 's';
}
```

**Methoden:**
```php
public function exists(): bool
{
    return Storage::disk($this->disk)->exists($this->path);
}

public function getContent(): string
{
    return Storage::disk($this->disk)->get($this->path);
}

public function deleteFile(): bool
{
    if ($this->exists()) {
        return Storage::disk($this->disk)->delete($this->path);
    }

    return false;
}

public static function generateFilename(string $type): string
{
    $date = now()->format('Y-m-d_H-i-s');
    return "backup-{$type}-{$date}.zip";
}
```

### 3. Backup Service

**Datei:** `backend/app/Services/BackupService.php`

**Create Backup:**
```php
public function create(array $options = []): Backup
{
    $backup = Backup::create([
        'name' => $options['name'] ?? 'Backup ' . now()->format('Y-m-d H:i:s'),
        'type' => $options['type'] ?? 'full',
        'status' => 'creating',
        'disk' => $options['disk'] ?? 'local',
        'path' => 'temp', // Will be updated after creation
        'description' => $options['description'] ?? null,
        'options' => $options,
        'created_by' => auth()->id(),
    ]);

    // Create ZIP archive
    $zip = new ZipArchive();
    $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // Add database dump
    if ($this->shouldIncludeDatabase($options)) {
        $sqlDump = $this->dumpDatabase();
        $zip->addFromString("database.sql", $sqlDump);
        $itemsCount++;
    }

    // Add files
    if ($this->shouldIncludeFiles($options)) {
        $files = $this->getFilesToBackup($options['exclude_files'] ?? []);
        foreach ($files as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file);
            $zip->addFile($file, $relativePath);
            $itemsCount++;
        }
    }

    // Add metadata
    $zip->addFromString('backup-metadata.json', json_encode([
        'created_at' => now()->toIso8601String(),
        'type' => $backup->type,
        'database' => $this->databaseName,
        'laravel_version' => app()->version(),
        'options' => $options,
    ], JSON_PRETTY_PRINT));

    $zip->close();

    // Store the backup file
    Storage::disk($backup->disk)->put($storagePath, file_get_contents($tempPath));

    // Update backup record
    $backup->update([
        'path' => $storagePath,
        'file_size' => $fileSize,
        'items_count' => $itemsCount,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    return $backup->fresh();
}
```

**Database Dump:**
```php
protected function dumpDatabase(): string
{
    $dsn = config('database.connections.mysql');
    $database = $dsn['database'];

    $command = "mysqldump --user={$dsn['username']} --password={$dsn['password']} --host={$dsn['host']} --port={$dsn['port']} {$database}";

    // Use --single-transaction for InnoDB
    $command .= " --single-transaction --quick --lock-tables=false";

    $output = shell_exec($command);

    if ($output === null) {
        throw new \Exception('Database dump failed');
    }

    return $output;
}
```

**Restore Backup:**
```php
public function restore(Backup $backup, array $options = []): array
{
    $results = [
        'database' => false,
        'files' => false,
        'errors' => [],
    ];

    // Download backup file
    $tempPath = storage_path("app/temp/restore-" . Str::random(8) . ".zip");
    file_put_contents($tempPath, $backup->getContent());

    $zip = new ZipArchive();
    $zip->open($tempPath);

    // Restore database
    if ($this->shouldIncludeDatabase($backup->options) && ($options['restore_database'] ?? true)) {
        $sqlDump = $zip->getFromName("database.sql");
        if ($sqlDump) {
            $this->restoreDatabase($sqlDump);
            $results['database'] = true;
        }
    }

    // Restore files
    if ($this->shouldIncludeFiles($backup->options) && ($options['restore_files'] ?? true)) {
        $this->restoreFiles($zip);
        $results['files'] = true;
    }

    $zip->close();
    File::delete($tempPath);

    return $results;
}
```

**Restore Database:**
```php
protected function restoreDatabase(string $sqlDump): void
{
    $dsn = config('database.connections.mysql');
    $database = $dsn['database'];

    $command = "mysql --user={$dsn['username']} --password={$dsn['password']} --host={$dsn['host']} --port={$dsn['port']} {$database}";

    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (is_resource($process)) {
        fwrite($pipes[0], $sqlDump);
        fclose($pipes[0]);

        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \Exception("Database restore failed: {$error}");
        }
    }
}
```

**Get Files to Backup:**
```php
protected function getFilesToBackup(array $exclude = []): array
{
    $basePath = base_path();
    $files = [];

    $directories = [
        $basePath . '/app',
        $basePath . '/config',
        $basePath . '/database',
        $basePath . '/public',
        $basePath . '/resources',
        $basePath . '/routes',
        $basePath . '/.env',
    ];

    foreach ($directories as $directory) {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();

                // Check exclusions
                $excluded = false;
                foreach ($exclude as $pattern) {
                    if (Str::startsWith($filePath, $pattern) ||
                        str_contains($filePath, $pattern)) {
                        $excluded = true;
                        break;
                    }
                }

                if (!$excluded) {
                    $files[] = $filePath;
                }
            }
        }
    }

    return $files;
}
```

**Restore Files:**
```php
protected function restoreFiles(ZipArchive $zip): void
{
    $basePath = base_path();

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);

        // Skip metadata and system files
        if ($filename === 'backup-metadata.json' ||
            str_starts_with($filename, '.env')) {
            continue;
        }

        $filePath = $basePath . '/' . $filename;
        $directory = dirname($filePath);

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Extract file
        file_put_contents($filePath, $zip->getFromIndex($i));
    }
}
```

**Disk Usage:**
```php
public function getDiskUsage(): array
{
    $backupPath = storage_path('app/backups');
    $totalSize = 0;
    $fileCount = 0;

    if (is_dir($backupPath)) {
        foreach (glob("{$backupPath}/*.zip") as $file) {
            $totalSize += filesize($file);
            $fileCount++;
        }
    }

    return [
        'total_size' => $totalSize,
        'file_count' => $fileCount,
        'human_size' => $this->formatBytes($totalSize),
    ];
}
```

**Clean Old Backups:**
```php
public function cleanOldBackups(int $keep = 5): int
{
    $backups = Backup::completed()
        ->orderBy('created_at', 'desc')
        ->get();

    $deletedCount = 0;

    foreach ($backups->slice($keep) as $backup) {
        $this->delete($backup);
        $deletedCount++;
    }

    return $deletedCount;
}
```

### 4. API Controller

**Datei:** `backend/app/Http/Controllers/Api/V1/BackupController.php`

**Endpoints:**
- `GET /api/v1/backups` - Alle Backups auflisten
- `POST /api/v1/backups` - Neues Backup erstellen
- `GET /api/v1/backups/stats` - Statistiken
- `GET /api/v1/backups/{id}` - Backup Details
- `POST /api/v1/backups/{id}/restore` - Restore ausführen
- `GET /api/v1/backups/{id}/download` - Backup herunterladen
- `DELETE /api/v1/backups/{id}` - Backup löschen

**Store:**
```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'nullable|string|max:255',
        'type' => 'required|in:full,database,files',
        'description' => 'nullable|string',
        'disk' => 'nullable|string',
        'include_database' => 'nullable|boolean',
        'include_files' => 'nullable|boolean',
        'exclude_files' => 'nullable|array',
        'exclude_files.*' => 'string',
    ]);

    try {
        $backup = $this->backupService->create($validated);

        return response()->json([
            'message' => 'Backup created successfully',
            'backup' => $backup,
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Backup failed',
            'error' => $e->getMessage(),
        ], 500);
    }
}
```

**Restore:**
```php
public function restore(Request $request, $id)
{
    $validated = $request->validate([
        'restore_database' => 'nullable|boolean',
        'restore_files' => 'nullable|boolean',
        'confirm' => 'required|accepted',
    ]);

    $backup = Backup::findOrFail($id);

    if ($backup->status !== 'completed') {
        return response()->json([
            'message' => 'Cannot restore from incomplete backup',
        ], 400);
    }

    try {
        $results = $this->backupService->restore($backup, $validated);

        return response()->json([
            'message' => 'Backup restored successfully',
            'results' => $results,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Restore failed',
            'error' => $e->getMessage(),
        ], 500);
    }
}
```

**Stats:**
```php
public function stats()
{
    $totalBackups = Backup::count();
    $completedBackups = Backup::completed()->count();
    $failedBackups = Backup::failed()->count();
    $totalSize = Backup::completed()->sum('file_size');
    $diskUsage = $this->backupService->getDiskUsage();

    $latestBackup = Backup::completed()
        ->orderBy('created_at', 'desc')
        ->first();

    return response()->json([
        'total_backups' => $totalBackups,
        'completed_backups' => $completedBackups,
        'failed_backups' => $failedBackups,
        'total_size' => $totalSize,
        'disk_usage' => $diskUsage,
        'latest_backup' => $latestBackup?->created_at,
    ]);
}
```

## Frontend Implementierung

### 1. API Service

**Datei:** `frontend/src/services/api.ts`

```typescript
const backupService = {
  async getAll(params?: any) {
    const { data } = await api.get('/backups', { params });
    return data;
  },

  async get(id: string | number) {
    const { data } = await api.get(`/backups/${id}`);
    return data;
  },

  async create(backupData: any) {
    const { data } = await api.post('/backups', backupData);
    return data;
  },

  async download(id: string | number) {
    const url = `${window.location.origin}/api/v1/backups/${id}/download`;
    window.open(url, '_blank');
  },

  async restore(id: string | number, options: any) {
    const { data } = await api.post(`/backups/${id}/restore`, {
      ...options,
      confirm: true,
    });
    return data;
  },

  async delete(id: string | number) {
    await api.delete(`/backups/${id}`);
  },

  async getStats() {
    const { data } = await api.get('/backups/stats');
    return data;
  },
};
```

### 2. Backups Page UI

**Datei:** `frontend/src/pages/BackupsPage.tsx`

**Features:**

**Stats Dashboard (4 Karten):**
- Total Backups (Anzahl aller Backups)
- Completed (Erfolgreich abgeschlossene)
- Total Size (Gesamtspeicherplatz)
- Latest Backup (Datum des letzten Backups)

**Backup Tabelle:**
- Name (mit Beschreibung, Creator, Datum)
- Type (Full, Database, Files) mit Icons
- Status (Pending, Creating, Completed, Failed) mit Tags
- Size (Formattierte Größe)
- Items (Anzahl der Elemente)
- Duration (Dauer der Erstellung)
- Created (Erstellungsdatum)

**Filter & Sortierung:**
- Filter by Type (Full, Database, Files)
- Filter by Status (Completed, Creating, Failed)
- Sort by Name, Size, Items, Created

**Create Backup Modal:**
- Backup Name (optional, auto-generated wenn leer)
- Backup Type (3 Optionen):
  - **Full Backup** (Database + Files)
  - **Database Only** (nur Datenbank)
  - **Files Only** (nur Dateien)
- Description (Beschreibung)
- Info Alert mit Tipps:
  - Full backups include database and all application files
  - Database backups use mysqldump with single-transaction
  - Files are compressed in ZIP format
  - Backups are stored in storage/app/backups
  - Ensure sufficient disk space before creating backups

**Restore Modal:**
- Warning Alert (Daten werden überschrieben!)
- Backup Informationen:
  - Backup Name
  - Type
  - Created
  - Size
- Restore Options:
  - Restore Database (Checkbox, disabled bei Files-only)
  - Restore Files (Checkbox, disabled bei Database-only)

**Actions:**
- Download (nur bei Status "completed")
- Restore (nur bei Status "completed")
- View Error (nur bei Status "failed")
- Delete (immer verfügbar)

**Status Tags mit Icons:**
- **Pending** - ClockCircleOutlined (default)
- **Creating** - SyncOutlined spin (processing)
- **Completed** - CheckCircleOutlined (success)
- **Failed** - ExclamationCircleOutlined (error)

**Type Tags mit Icons:**
- **Full** - CloudDownloadOutlined (blue)
- **Database** - DatabaseOutlined (green)
- **Files** - FileOutlined (orange)

## Backup File Format

### ZIP Structure:

```
backup-full-2024-01-20_10-30-00.zip
├── database.sql              # Database dump (optional)
├── app/                      # Application files
├── config/                   # Configuration files
├── database/                 # Database files
├── public/                   # Public files
├── resources/                # Resources
├── routes/                   # Routes
├── .env                      # Environment file
└── backup-metadata.json      # Metadata
```

### backup-metadata.json:

```json
{
  "created_at": "2024-01-20T10:30:00+00:00",
  "type": "full",
  "database": "blog_cms",
  "laravel_version": "11.0.0",
  "options": {
    "name": "My Backup",
    "type": "full",
    "description": "Full backup before update"
  }
}
```

### mysqldump Options:

```bash
mysqldump \
  --user=username \
  --user=password \
  --host=localhost \
  --port=3306 \
  database_name \
  --single-transaction \    # InnoDB consistency
  --quick \                  # Retrieve rows one at a time
  --lock-tables=false        # Don't lock tables
```

## Backup Types

### 1. Full Backup
- **Enthält:** Datenbank + Alle Dateien
- **Größe:** 50MB - 2GB+ (je nach Projekt)
- **Dauer:** 30s - 10min (je nach Größe)
- **Verwendung:** Komplette System-Sicherung

### 2. Database Only
- **Enthält:** Nur Datenbank (SQL Dump)
- **Größe:** 1MB - 100MB
- **Dauer:** 5s - 30s
- **Verwendung:** Schnelle Datenbank-Sicherung

### 3. Files Only
- **Enthält:** Nur Dateien (app, config, public, etc.)
- **Größe:** 10MB - 1GB+
- **Dauer:** 10s - 5min
- **Verwendung:** Datei-Sicherung ohne Datenbank

## Restore Process

### Step 1: Backup wählen
- Nur Backups mit Status "completed" können wiederhergestellt werden
- Details anzeigen (Name, Type, Size, Created)

### Step 2: Optionen wählen
- **Restore Database** - Datenbank wiederherstellen
- **Restore Files** - Dateien wiederherstellen
- Bei "Full" Backups: Beides verfügbar
- Bei "Database" Backups: Nur Database
- Bei "Files" Backups: Nur Files

### Step 3: Bestätigen
- Warning: Aktuelle Daten werden überschrieben!
- Diese Aktion kann nicht rückgängig gemacht werden!

### Step 4: Ausführen
- Database: SQL Dump wird mit `mysql` Command importiert
- Files: ZIP wird entpackt und Dateien überschreiben existierende
- `.env` Datei wird NICHT überschrieben (Security)

### Step 5: Ergebnis
- Erfolgreiche Wiederherstellung mit Details
- Bei Fehlern: Warnung mit Fehlerliste

## Best Practices

### ✅ DO:

1. **Regelmäßige Backups**
   - Mindestens 1x täglich (automatisch via Cron)
   - Vor Updates/Deploys
   - Vor großen Änderungen

2. **Backup Typen wählen**
   - Full Backup: Wöchentlich
   - Database: Täglich
   - Files: Bei Änderungen

3. **Alte Backups aufräumen**
   - `cleanOldBackups(5)` - Nur 5 neueste behalten
   - Speicherplatz sparen
   - Übersicht behalten

4. **Backup testen**
   - Regelmäßig Restore testen
   - Funktioniert Backup?
   - Alle Dateien vorhanden?

5. **Externe Speicherung**
   - S3, Dropbox, FTP, etc.
   - Offsite Storage für Disaster Recovery
   - Redundanz

### ❌ DON'T:

1. **Nicht nur auf lokale Backups verlassen**
   - Server Crash = Alle Backups weg
   - Externe Speicherung nutzen!

2. **Backups nicht teilen**
   - Sensible Daten enthalten
   - Passwörter, API Keys, etc.
   - Sicher speichern!

3. **.env nicht blind überschreiben**
   - Enthält Secrets
   - Neue Umgebung anders konfiguriert
   - Manuell prüfen!

4. **Nicht ohne Test restoren**
   - Backup könnte korrupt sein
   - Erst auf Staging testen!
   - Danach auf Production

## Storage Configuration

### Local Storage:
```php
// config/filesystems.php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app/backups'),
    ],
],
```

### S3 Storage:
```php
's3' => [
    'driver' => 's3',
    'bucket' => env('AWS_BUCKET'),
    'region' => env('AWS_DEFAULT_REGION'),
    'url' => env('AWS_URL'),
],
```

### Usage:
```php
Storage::disk('s3')->put($path, $content);
```

## Automated Backups (Cron)

### Console Command (möglich):
```php
// app/Console/Commands/BackupCommand.php
public function handle()
{
    $backup = $this->backupService->create([
        'type' => 'full',
        'name' => 'Automatic Backup ' . now()->format('Y-m-d H:i:s'),
    ]);

    $this->info("Backup created: {$backup->name}");
}
```

### Schedule:
```php
// app/Console/Kernel.php
$schedule->command('backup:create')
    ->daily()
    ->at('02:00')
    ->onSuccess(function () {
        Log::info('Backup completed successfully');
    })
    ->onFailure(function () {
        Log::error('Backup failed');
    });
```

## API Examples

### Create Backup:

```bash
POST /api/v1/backups
{
  "name": "My Backup",
  "type": "full",
  "description": "Full backup before update"
}

Response:
{
  "message": "Backup created successfully",
  "backup": {
    "id": 1,
    "name": "My Backup",
    "type": "full",
    "status": "completed",
    "file_size": 52428800,
    "file_size_formatted": "50.00 MB",
    "items_count": 1523,
    "duration": "2m 15s",
    ...
  }
}
```

### List Backups:

```bash
GET /api/v1/backups?page=1&per_page=20&type=full&status=completed

Response:
{
  "data": [...],
  "total": 42,
  "per_page": 20,
  "current_page": 1,
  ...
}
```

### Get Stats:

```bash
GET /api/v1/backups/stats

Response:
{
  "total_backups": 42,
  "completed_backups": 38,
  "failed_backups": 4,
  "total_size": 2147483648,
  "disk_usage": {
    "total_size": 2147483648,
    "file_count": 38,
    "human_size": "2.00 GB"
  },
  "latest_backup": "2024-01-20T10:30:00+00:00"
}
```

### Restore:

```bash
POST /api/v1/backups/1/restore
{
  "restore_database": true,
  "restore_files": true,
  "confirm": true
}

Response:
{
  "message": "Backup restored successfully",
  "results": {
    "database": true,
    "files": true,
    "errors": []
  }
}
```

### Download:

```bash
GET /api/v1/backups/1/download

Response:
Binary ZIP file (Content-Disposition: attachment)
```

### Delete:

```bash
DELETE /api/v1/backups/1

Response:
{
  "message": "Backup deleted successfully"
}
```

## Features Zusammenfassung

### Backend:
- ✅ Database Migration & Model
- ✅ BackupService (Create, Restore, Delete)
- ✅ mysqldump (single-transaction)
- ✅ mysql import
- ✅ ZIP Archive (ZipArchive)
- ✅ Recursive File Iterator
- ✅ Metadata JSON
- ✅ Disk Usage Calculation
- ✅ Clean Old Backups
- ✅ API Controller
- ✅ Statistics Endpoint

### Frontend:
- ✅ Stats Dashboard (4 Karten)
- ✅ Backup Table mit Filter/Sort
- ✅ Create Backup Modal (3 Typen)
- ✅ Restore Modal mit Options
- ✅ Download Button
- ✅ Delete mit Confirmation
- ✅ Status Tags mit Icons
- ✅ Type Tags mit Icons
- ✅ Error Tooltip bei Failed
- ✅ Info Alerts

---

**Phase 14 Status:** ✅ KOMPLETT

Das Backup & Restore System ist voll funktionsfähig mit Full/Database/Files Backups, ZIP Kompression, mysqldump und automatischer Bereinigung!
