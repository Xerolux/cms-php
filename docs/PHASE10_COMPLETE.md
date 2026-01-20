# Phase 10: Kommentarsystem - ABGESCHLOSSEN ✅

## Übersicht

Das Kommentarsystem wurde erfolgreich implementiert! User können jetzt Beiträge kommentieren, Admins können diese moderieren und ein automatischer Spam-Schutz filternt unerwünschte Kommentare.

## Backend Implementierung

### 1. Database Migration

**Datei:** `backend/database/migrations/2024_01_20_000014_create_comments_table.php`

```php
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
    $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
    $table->string('author_name')->nullable();
    $table->string('author_email')->nullable();
    $table->string('author_ip')->nullable();
    $table->text('content');
    $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])->default('pending');
    $table->unsignedInteger('likes_count')->default(0);
    $table->unsignedInteger('dislikes_count')->default(0);
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['post_id', 'status']);
    $table->index('status');
    $table->index('created_at');
});
```

### 2. Comment Model

**Datei:** `backend/app/Models/Comment.php`

**Features:**
- **Beziehungen:** Post, User, Parent (self), Replies (hasMany)
- **Scopes:** approved(), pending(), spam()
- **Helper Methods:** approve(), reject(), markAsSpam()
- **Accessor:** getAuthorNameAttribute()

```php
// Beziehungen
public function post() {
    return $this->belongsTo(Post::class);
}

public function user() {
    return $this->belongsTo(User::class);
}

public function parent() {
    return $this->belongsTo(Comment::class, 'parent_id');
}

public function replies() {
    return $this->hasMany(Comment::class, 'parent_id');
}

// Scopes
public function scopeApproved($query) {
    return $query->where('status', 'approved');
}

public function scopePending($query) {
    return $query->where('status', 'pending');
}

// Moderation
public function approve() {
    $this->update([
        'status' => 'approved',
        'approved_at' => now(),
    ]);
}

public function reject() {
    $this->update([
        'status' => 'rejected',
        'rejected_at' => now(),
    ]);
}

public function markAsSpam() {
    $this->update(['status' => 'spam']);
}
```

### 3. Comment Controller

**Datei:** `backend/app/Http/Controllers/Api/V1/CommentController.php`

**API Endpoints:**
- `GET /api/v1/comments` - Liste (Pagination, Filter)
- `POST /api/v1/comments` - Kommentar erstellen
- `GET /api/v1/comments/{id}` - Details
- `PUT /api/v1/comments/{id}` - Update
- `POST /api/v1/comments/{id}/approve` - Freischalten
- `POST /api/v1/comments/{id}/reject` - Ablehnen
- `POST /api/v1/comments/{id}/spam` - Spam markieren
- `DELETE /api/v1/comments/{id}` - Löschen

**Spam Detection Algorithmus:**

```php
protected function detectSpam(Request $request, array $data): int
{
    $score = 0;

    // Excessive Links (>2) = +3
    $linkCount = preg_match_all('/http/', $data['content']);
    if ($linkCount > 2) $score += 3;

    // Excessive Caps (>70%) = +2
    $capsRatio = preg_match_all('/[A-Z]/', $data['content']) / strlen($data['content']);
    if ($capsRatio > 0.7) $score += 2;

    // Repetitive Words (<30% unique) = +2
    $words = explode(' ', strtolower($data['content']));
    $uniqueWords = array_unique($words);
    if (count($words) > 10 && count($uniqueWords) / count($words) < 0.3) {
        $score += 2;
    }

    // Short Content (<10 chars) = +1
    if (strlen($data['content']) < 10) $score += 1;

    return $score; // >5 = Spam
}
```

## Frontend Implementierung

### 1. TypeScript Types

**Datei:** `frontend/src/types/index.ts`

```typescript
export interface Comment {
  id: number;
  post_id: number;
  user_id?: number;
  parent_id?: number;
  author_name?: string;
  author_email?: string;
  author_ip?: string;
  content: string;
  status: 'pending' | 'approved' | 'rejected' | 'spam';
  likes_count: number;
  dislikes_count: number;
  approved_at?: string;
  rejected_at?: string;
  created_at: string;
  updated_at: string;
  user?: User;
  parent?: Comment;
  replies?: Comment[];
}
```

### 2. API Service

**Datei:** `frontend/src/services/api.ts`

```typescript
const commentService = {
  async getAll(params?: any) {
    const { data } = await api.get('/comments', { params });
    return data;
  },

  async approve(id: number) {
    const { data } = await api.post(`/comments/${id}/approve`);
    return data;
  },

  async reject(id: number) {
    const { data } = await api.post(`/comments/${id}/reject`);
    return data;
  },

  async markAsSpam(id: number) {
    const { data } = await api.post(`/comments/${id}/spam`);
    return data;
  },

  async delete(id: number) {
    await api.delete(`/comments/${id}`);
  },
};
```

### 3. Comments Page

**Datei:** `frontend/src/pages/CommentsPage.tsx`

**Features:**
- **Analytics Dashboard:** 4 Statistik Cards
- **Status Filtering:** Dropdown für alle Status
- **Quick Actions:** Approve, Reject, Spam Buttons
- **Expandable Rows:** Vollständiger Content + Replies
- **View Modal:** Alle Details in Modal

**Analytics Cards:**
```tsx
<Card>
  <Statistic title="Total Comments" value={totalComments} prefix={<MessageOutlined />} />
</Card>
<Card>
  <Statistic title="Pending" value={pendingComments} valueStyle={{ color: pendingComments > 0 ? '#fa8c16' : undefined }} />
</Card>
<Card>
  <Statistic title="Approved" value={approvedComments} valueStyle={{ color: '#52c41a' }} />
</Card>
<Card>
  <Statistic title="Spam" value={spamComments} valueStyle={{ color: spamComments > 0 ? '#722ed1' : undefined }} />
</Card>
```

**Status Tags mit Icons:**
```tsx
const getStatusIcon = (status: string) => {
  const icons = {
    pending: <ExclamationCircleOutlined />,
    approved: <CheckCircleOutlined />,
    rejected: <CloseCircleOutlined />,
    spam: <StopOutlined />,
  };
  return icons[status] || <MessageOutlined />;
};

<Tag icon={getStatusIcon(status)} color={getStatusColor(status)}>
  {getStatusLabel(status)}
</Tag>
```

**Moderation Actions:**
```tsx
{record.status === 'pending' && (
  <>
    <Tooltip title="Approve">
      <Button type="text" style={{ color: '#52c41a' }}
        icon={<CheckCircleOutlined />}
        onClick={() => handleApprove(record.id)}
      />
    </Tooltip>
    <Tooltip title="Reject">
      <Button type="text" style={{ color: '#ff4d4f' }}
        icon={<CloseCircleOutlined />}
        onClick={() => handleReject(record.id)}
      />
    </Tooltip>
  </>
)}
```

## Database Schema

```
comments
├── id (Primary Key)
├── post_id (Foreign Key → posts)
├── user_id (Foreign Key → users, nullable)
├── parent_id (Foreign Key → comments, nullable)
├── author_name (für Gäste)
├── author_email (für Gäste)
├── author_ip (Spam-Tracking)
├── content (Kommentar-Text)
├── status (pending|approved|rejected|spam)
├── likes_count (Reactions)
├── dislikes_count (Reactions)
├── approved_at (Timestamp)
├── rejected_at (Timestamp)
├── created_at
├── updated_at
└── deleted_at (Soft Deletes)
```

## Status Workflow

```
Gast/User erstellt Kommentar
         ↓
    [PENDING]
         ↓
    Admin Moderation
    ↙          ↓          ↘
[APPROVED]  [REJECTED]  [SPAM]
    ↓           ↓           ↓
  Sichtbar   Versteckt   Auto-Detect
```

## Spam Detection Rules

| Regel | Punkte | Beschreibung |
|-------|--------|--------------|
| Excessive Links | +3 | Mehr als 2 Links im Text |
| Excessive Caps | +2 | Über 70% Großbuchstaben |
| Repetitive Words | +2 | Weniger als 30% einzigartige Wörter |
| Short Content | +1 | Weniger als 10 Zeichen |

**Spam Threshold:** Score > 5 = Automatisch als Spam markiert

## Features Zusammenfassung

### Backend
- ✅ Vollständiges CRUD API
- ✅ Threaded Comments (Parent/Child)
- ✅ Guest Comments (Name/Email)
- ✅ User Comments (Authenticated)
- ✅ IP Address Tracking
- ✅ Spam Detection (Multi-Factor)
- ✅ Moderation Workflow
- ✅ Soft Deletes
- ✅ Reactions Tracking

### Frontend
- ✅ Comments Management UI
- ✅ Analytics Dashboard
- ✅ Status Filtering
- ✅ Quick Actions (Approve/Reject/Spam)
- ✅ Expandable Rows
- ✅ View Modal
- ✅ Threaded Display
- ✅ Reactions Display

## Nächste Schritte (Optional)

**Advanced Features:**
- [ ] Comment Editing (für User innerhalb 5 Min)
- [ ] Comment Pinning (wichtige Kommentare oben)
- [ ] Comment Flags (User melden Kommentare)
- [ ] Email Notifications bei neuen Kommentaren
- [ ] Auto-Reply für bestimmte Keywords
- [ ] Comment Voting (Upvote/Downvote)
- [ ] Comment Search
- [ ] Bulk Actions (mehrere auf einmal moderieren)

## API Examples

### Kommentar erstellen (Gast)
```bash
POST /api/v1/comments
{
  "post_id": 1,
  "content": "Great article!",
  "author_name": "Max Mustermann",
  "author_email": "max@example.com"
}
```

### Kommentar erstellen (User)
```bash
POST /api/v1/comments
Authorization: Bearer {token}
{
  "post_id": 1,
  "content": "Great article!"
}
```

### Antwort erstellen
```bash
POST /api/v1/comments
{
  "post_id": 1,
  "parent_id": 5,
  "content": "I agree!"
}
```

### Kommentar moderieren
```bash
POST /api/v1/comments/5/approve
POST /api/v1/comments/5/reject
POST /api/v1/comments/5/spam
```

---

**Phase 10 Status:** ✅ KOMPLETT

Das Kommentarsystem ist voll funktionsfähig mit Moderation, Spam-Schutz und threaded Comments!
