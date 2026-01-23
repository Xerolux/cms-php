# GraphQL API Setup für XQUANTORIA

Dieses Dokument beschreibt die Einrichtung und Verwendung des GraphQL APIs für XQUANTORIA mit nuwave/lighthouse.

## Installation

### Backend

1. **Composer Dependencies installieren:**

```bash
cd backend
composer require nuwave/lighthouse
composer require mll-lab/lighthouse-graphql-pagination
```

2. **Lighthouse Konfiguration veröffentlichen:**

```bash
php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider"
```

3. **GraphQL Schema ansehen:**

Das GraphQL Schema befindet sich in `backend/graphql/schema.graphql`.

4. **GraphQL Playground testen:**

Besuche `http://localhost:8000/graphql/playground` im Browser.

### Frontend

1. **Dependencies installieren:**

```bash
cd frontend
npm install
```

Die notwendigen Pakete wurden bereits zu `package.json` hinzugefügt:
- `@apollo/client`: Apollo GraphQL Client
- `graphql`: GraphQL JavaScript-Implementierung

2. **Umgebungsvariablen konfigurieren:**

Kopiere `.env.graphql.example` nach `.env`:

```bash
cp .env.graphql.example .env
```

Passe die GraphQL URL an:

```env
VITE_GRAPHQL_URL=http://localhost:8000/graphql
```

## GraphQL API Features

### Authentifizierung

Das GraphQL API verwendet Laravel Sanctum für die Authentifizierung. Token werden automatisch zu allen Anfragen hinzugefügt.

### Public Queries (Keine Authentifizierung erforderlich)

```graphql
query GetPublicPosts {
  publicPosts(first: 10) {
    data {
      id
      title
      slug
      excerpt
      author {
        name
      }
    }
  }
}
```

### Protected Queries (Authentifizierung erforderlich)

```graphql
query GetPosts {
  posts(first: 10) {
    data {
      id
      title
      status
      author {
        name
      }
    }
  }
}
```

### Mutations (Authentifizierung erforderlich)

```graphql
mutation CreatePost {
  createPost(input: {
    title: "Mein Post"
    slug: "mein-post"
    content: "Inhalt..."
    status: "draft"
  }) {
    id
    title
  }
}
```

## Verwendung im Frontend

### Apollo Client verwenden

```typescript
import { useQuery } from '@apollo/client';
import { GET_POSTS } from '../graphql/queries';

function PostsList() {
  const { data, loading, error } = useQuery(GET_POSTS, {
    variables: { first: 10 }
  });

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error.message}</div>;

  return (
    <ul>
      {data.posts.data.map(post => (
        <li key={post.id}>{post.title}</li>
      ))}
    </ul>
  );
}
```

### Custom Hooks verwenden

```typescript
import { usePosts } from '../hooks/usePosts';

function PostsList() {
  const { posts, loading, error, refetch } = usePosts();

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error loading posts</div>;

  return (
    <>
      <button onClick={() => refetch()}>Refresh</button>
      <ul>
        {posts.map(post => (
          <li key={post.id}>{post.title}</li>
        ))}
      </ul>
    </>
  );
}
```

## Pagination

Das API verwendet Cursor-basierte Pagination nach dem Laravel Paginator Pattern:

```graphql
query GetPaginatedPosts {
  posts(first: 20, page: 1) {
    data {
      id
      title
    }
    paginatorInfo {
      currentPage
      lastPage
      total
      hasMorePages
    }
  }
}
```

## Datei-Uploads

Für Datei-Uploads verwenden wir den `Upload` Scalar:

```typescript
import { useMutation } from '@apollo/client';
import { UPLOAD_MEDIA } from '../graphql/mutations';

function UploadComponent() {
  const [uploadMedia, { loading }] = useMutation(UPLOAD_MEDIA);

  const handleFileUpload = (file: File) => {
    uploadMedia({
      variables: {
        file,
        title: 'Mein Bild',
        alt_text: 'Alternativer Text'
      }
    });
  };

  return <input type="file" onChange={(e) => handleFileUpload(e.target.files[0])} />;
}
```

## Real-Time Updates (Subscriptions)

Subscriptions sind im Schema definiert, benötigen aber einen WebSocket-Server:

```typescript
import { useSubscription } from '@apollo/client';
import { POST_UPDATED } from '../graphql/subscriptions';

function PostUpdates({ postId }) {
  const { data } = useSubscription(POST_UPDATED, {
    variables: { postId }
  });

  if (data) {
    console.log('Post updated:', data.postUpdated);
  }

  return null;
}
```

## Fehlerbehandlung

Der Apollo Client ist so konfiguriert, dass er Fehler automatisch behandelt:

1. **Authentifizierungsfehler:** Weiterleitung zur Login-Seite
2. **Autorisierungsfehler:** Fehlermeldung anzeigen
3. **Validierungsfehler:** Detaillierte Fehlermeldungen
4. **Netzwerkfehler:** Allgemeine Netzwerk-Fehlermeldung

## GraphQL Playground

Im Development-Modus ist der GraphQL Playground verfügbar unter:
`http://localhost:8000/graphql/playground`

Dies ermöglicht es dir, Queries und Mutations interaktiv zu testen.

## Best Practices

1. **Typensicherheit:** Verwende die TypeScript-Typen aus `src/types/graphql.ts`
2. **Cache-Strategie:** Nutze den Apollo Cache für optimale Performance
3. **Fehlerbehandlung:** Verwende Custom Hooks für konsistente Fehlerbehandlung
4. **Pagination:** Nutze die Paginierungs-Helper für effizientes Laden
5. **Optimistic Updates:** Implementiere Optimistic UI für bessere UX

## Nächste Schritte

1. Installiere die Composer-Pakete im Backend
2. Teste das GraphQL API im Playground
3. Baue erste Queries im Frontend
4. Implementiere Mutations für CRUD-Operationen
5. Füge Real-Time Updates mit Subscriptions hinzu

## Troubleshooting

### "Unauthenticated" Fehler

Stelle sicher, dass du eingeloggt bist und ein gültiges Token hast.

### "Unauthorized" Fehler

Überprüfe, ob du die notwendigen Berechtigungen hast.

### CORS Fehler

Stelle sicher, dass die Frontend-URL in den Laravel CORS-Einstellungen erlaubt ist.

### Cache-Probleme

Du kannst den Apollo Cache leeren:

```typescript
import { apolloClient } from './lib/apollo-client';

apolloClient.clearStore();
```

## Weitere Informationen

- [Lighthouse Documentation](https://lighthouse-php.com/)
- [Apollo Client Documentation](https://www.apollographql.com/docs/react/)
- [GraphQL Specification](https://spec.graphql.org/)
