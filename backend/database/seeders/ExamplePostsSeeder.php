<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExamplePostsSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing users
        $admin = User::where('role', 'super_admin')->first() ?? User::first();
        $users = User::where('id', '!=', $admin->id)->take(5)->get();

        if ($users->isEmpty()) {
            $this->command->warn('Not enough users found. Skipping...');
            return;
        }

        // Get or create tags
        $tagsData = [
            ['name' => 'PHP', 'slug' => 'php', 'color' => '#777BB4'],
            ['name' => 'Laravel', 'slug' => 'laravel', 'color' => '#FF2D20'],
            ['name' => 'React', 'slug' => 'react', 'color' => '#61DAFB'],
            ['name' => 'Docker', 'slug' => 'docker', 'color' => '#2496ED'],
            ['name' => 'PostgreSQL', 'slug' => 'postgresql', 'color' => '#336791'],
            ['name' => 'JavaScript', 'slug' => 'javascript', 'color' => '#F7DF1E'],
            ['name' => 'TypeScript', 'slug' => 'typescript', 'color' => '#3178C6'],
            ['name' => 'XQUANTORIA', 'slug' => 'xquantoria', 'color' => '#52c41a'],
        ];

        $tags = collect();
        foreach ($tagsData as $tagData) {
            $tag = Tag::firstOrCreate(
                ['slug' => $tagData['slug']],
                $tagData
            );
            $tags->push($tag);
        }

        // Get existing categories
        $categories = Category::take(5)->get();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Skipping...');
            return;
        }

        // Create published posts
        $postsData = [
            [
                'title' => 'Erste Schritte mit Laravel 11',
                'slug' => 'erste-schritte-mit-laravel-11',
                'excerpt' => 'Lernen Sie die neuen Features und Verbesserungen in Laravel 11 kennen.',
                'content' => 'Laravel 11 bringt viele aufregende neue Features mit sich...',
                'status' => 'published',
                'is_hidden' => false,
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => 'React Hooks: Eine umfassende Einführung',
                'slug' => 'react-hooks-einfuehrung',
                'excerpt' => 'Entdecken Sie die Leistungsfähigkeit von React Hooks für moderne Webanwendungen.',
                'content' => 'React Hooks haben die Art und Weise revolutioniert...',
                'status' => 'published',
                'is_hidden' => false,
                'published_at' => now()->subDays(3),
            ],
            [
                'title' => 'Docker für PHP-Entwickler',
                'slug' => 'docker-fuer-php-entwickler',
                'excerpt' => 'Wie Sie Ihre PHP-Anwendungen mit Docker containerisieren.',
                'content' => 'Docker hat sich zum Standard für Containerisierung etabliert...',
                'status' => 'published',
                'is_hidden' => false,
                'published_at' => now()->subDay(),
            ],
            [
                'title' => 'PostgreSQL vs MySQL: Ein Vergleich',
                'slug' => 'postgresql-vs-mysql-vergleich',
                'excerpt' => 'Welche Datenbank ist die richtige für Ihr Projekt?',
                'content' => 'Bei der Wahl einer relationalen Datenbank stehen...',
                'status' => 'published',
                'is_hidden' => false,
                'published_at' => now()->subHours(12),
            ],
            [
                'title' => 'Eigener CMS: Build or Buy?',
                'slug' => 'eigenes-cms-build-or-buy',
                'excerpt' => 'Die Vor- und Nachteile eines eigenen Content Management Systems.',
                'content' => 'Viele Unternehmen stehen vor der Entscheidung...',
                'status' => 'published',
                'is_hidden' => false,
                'published_at' => now()->subHours(6),
            ],
            [
                'title' => 'Geplanter: Neue API-Endpunkte',
                'slug' => 'geplante-neue-api-endpunkte',
                'excerpt' => 'Dies ist ein beispiel für einen geplanten Beitrag.',
                'content' => 'In Zukunft werden wir neue API-Endpunkte einführen...',
                'status' => 'scheduled',
                'is_hidden' => false,
                'published_at' => now()->addDays(3),
            ],
            [
                'title' => 'Versteckter: Internes Dokumentation',
                'slug' => 'versteckte-interne-dokumentation',
                'excerpt' => 'Dieser Beitrag ist versteckt und nur über direkten Link erreichbar.',
                'content' => 'Dies ist interne Dokumentation für das Team...',
                'status' => 'published',
                'is_hidden' => true,
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'Entwurf: Noch nicht fertig',
                'slug' => 'entwurf-noch-nicht-fertig',
                'excerpt' => 'Dieser Beitrag ist noch im Entwurfsstadium.',
                'content' => 'Der Inhalt wird noch überarbeitet...',
                'status' => 'draft',
                'is_hidden' => false,
                'published_at' => null,
            ],
        ];

        foreach ($postsData as $postData) {
            $post = Post::firstOrCreate(
                ['slug' => $postData['slug']],
                array_merge($postData, [
                    'author_id' => $admin->id,
                    'view_count' => rand(0, 500),
                    'meta_title' => $postData['title'],
                    'meta_description' => $postData['excerpt'],
                    'language' => 'de',
                ])
            );

            // Attach random categories
            $post->categories()->sync(
                $categories->random(rand(1, 2))->pluck('id')
            );

            // Attach random tags
            $post->tags()->sync(
                $tags->random(rand(2, 4))->pluck('id')
            );
        }

        // Create some comments
        $posts = Post::published()->get();
        foreach ($posts->take(3) as $post) {
            Comment::firstOrCreate(
                [
                    'post_id' => $post->id,
                    'author_email' => 'reader@example.com',
                ],
                [
                    'author_name' => 'Leser Peter',
                    'content' => 'Sehr interessanter Artikel! Danke für die Informationen.',
                    'status' => 'approved',
                    'author_ip' => '127.0.0.1',
                ]
            );
        }

        $this->command->info('Example posts and comments created successfully!');
    }
}
