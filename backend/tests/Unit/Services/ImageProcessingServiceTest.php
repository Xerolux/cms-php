<?php

namespace Tests\Unit\Services;

use App\Models\Media;
use App\Services\ImageProcessingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageProcessingServiceTest extends TestCase
{
    protected ImageProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageProcessingService();
        Storage::fake('public');
    }

    public function test_generate_thumbnails_for_image()
    {
        // Create a test image
        $image = \Illuminate\Support\Facades\File::copy(
            storage_path('test.jpg'),
            Storage::disk('public')->path('test.jpg')
        );

        $media = Media::factory()->create([
            'file_name' => 'test.jpg',
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1920,
            'height' => 1080,
        ]);

        // Mock image manager
        $mockManager = $this->createMock(ImageManager::class);
        // ... implementation depends on Intervention Image v3 mocking

        $thumbnails = $this->service->generateThumbnails($media);

        $this->assertIsArray($thumbnails);
        $this->assertArrayHasKey('thumbnail', $thumbnails);
        $this->assertArrayHasKey('small', $thumbnails);
        $this->assertArrayHasKey('medium', $thumbnails);
        $this->assertArrayHasKey('large', $thumbnails);
    }

    public function test_generate_thumbnails_returns_empty_for_non_image()
    {
        $media = Media::factory()->create([
            'file_name' => 'document.pdf',
            'file_path' => 'documents/test.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $thumbnails = $this->service->generateThumbnails($media);

        $this->assertEmpty($thumbnails);
    }

    public function test_crop_image()
    {
        Storage::disk('public')->put('test.jpg', 'fake-image-content');

        $media = Media::factory()->create([
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1920,
            'height' => 1080,
        ]);

        $result = $this->service->cropImage($media, 100, 100, 500, 500);

        $this->assertInstanceOf(Media::class, $result);
        $this->assertStringContainsString('_cropped', $result->file_path);
    }

    public function test_resize_image_with_aspect_ratio()
    {
        Storage::disk('public')->put('test.jpg', 'fake-image-content');

        $media = Media::factory()->create([
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1920,
            'height' => 1080,
        ]);

        $result = $this->service->resizeImage($media, 800, 600, true);

        $this->assertInstanceOf(Media::class, $result);
        $this->assertStringContainsString('800x600', $result->file_path);
    }

    public function test_resize_image_without_aspect_ratio()
    {
        Storage::disk('public')->put('test.jpg', 'fake-image-content');

        $media = Media::factory()->create([
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $result = $this->service->resizeImage($media, 800, 600, false);

        $this->assertInstanceOf(Media::class, $result);
    }

    public function test_rotate_image()
    {
        Storage::disk('public')->put('test.jpg', 'fake-image-content');

        $media = Media::factory()->create([
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1920,
            'height' => 1080,
        ]);

        $result = $this->service->rotateImage($media, 90);

        $this->assertInstanceOf(Media::class, $result);
        // After 90 degree rotation, width and height should swap
        $this->assertEquals(1080, $result->width);
        $this->assertEquals(1920, $result->height);
    }

    public function test_flip_image_horizontal()
    {
        Storage::disk('public')->put('test.jpg', 'fake-image-content');

        $media = Media::factory()->create([
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $result = $this->service->flipImage($media, 'horizontal');

        $this->assertInstanceOf(Media::class, $result);
    }

    public function test_flip_image_vertical()
    {
        Storage::disk('public')->put('test.jpg', 'fake-image-content');

        $media = Media::factory()->create([
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $result = $this->service->flipImage($media, 'vertical');

        $this->assertInstanceOf(Media::class, $result);
    }

    public function test_get_srcset_with_thumbnails()
    {
        $media = Media::factory()->create([
            'url' => 'http://example.com/image.jpg',
            'width' => 1920,
            'thumbnails' => [
                'small' => ['url' => 'http://example.com/small.jpg', 'width' => 300],
                'medium' => ['url' => 'http://example.com/medium.jpg', 'width' => 600],
            ],
        ]);

        $srcset = $this->service->getSrcset($media);

        $this->assertStringContainsString('http://example.com/small.jpg 300w', $srcset);
        $this->assertStringContainsString('http://example.com/medium.jpg 600w', $srcset);
        $this->assertStringContainsString('http://example.com/image.jpg 1920w', $srcset);
    }

    public function test_get_srcset_without_thumbnails()
    {
        $media = Media::factory()->create([
            'url' => 'http://example.com/image.jpg',
            'width' => 1920,
            'thumbnails' => null,
        ]);

        $srcset = $this->service->getSrcset($media);

        $this->assertEquals('http://example.com/image.jpg 1920w', $srcset);
    }

    public function test_optimize_image()
    {
        Storage::disk('public')->put('test.jpg', 'fake-image-content');

        $media = Media::factory()->create([
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000000,
        ]);

        $result = $this->service->optimizeImage($media, 85);

        $this->assertTrue($result);
    }

    public function test_optimize_image_returns_false_for_non_image()
    {
        $media = Media::factory()->create([
            'mime_type' => 'application/pdf',
        ]);

        $result = $this->service->optimizeImage($media);

        $this->assertFalse($result);
    }

    public function test_convert_to_webp()
    {
        Storage::disk('public')->put('test.jpg', 'fake-image-content');

        $media = Media::factory()->create([
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $webpPath = $this->service->convertToWebP($media, 85);

        $this->assertNotNull($webpPath);
        $this->assertStringEndsWith('.webp', $webpPath);
    }

    public function test_convert_to_webp_returns_null_for_non_image()
    {
        $media = Media::factory()->create([
            'mime_type' => 'application/pdf',
        ]);

        $webpPath = $this->service->convertToWebP($media);

        $this->assertNull($webpPath);
    }

    public function test_get_image_dimensions()
    {
        Storage::disk('public')->put('test.jpg', 'fake-image-content');

        $dimensions = $this->service->getImageDimensions('test.jpg');

        $this->assertIsArray($dimensions);
        $this->assertCount(2, $dimensions);
    }

    public function test_get_image_dimensions_for_nonexistent_file()
    {
        $dimensions = $this->service->getImageDimensions('nonexistent.jpg');

        $this->assertEquals([0, 0], $dimensions);
    }

    public function test_batch_process_multiple_images()
    {
        $media1 = Media::factory()->create([
            'file_path' => 'test1.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $media2 = Media::factory()->create([
            'file_path' => 'test2.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        Storage::disk('public')->put('test1.jpg', 'fake-content-1');
        Storage::disk('public')->put('test2.jpg', 'fake-content-2');

        $results = $this->service->batchProcess(
            [$media1->id, $media2->id],
            function ($media) {
                return ['processed' => true];
            }
        );

        $this->assertIsArray($results);
        $this->assertArrayHasKey($media1->id, $results);
        $this->assertArrayHasKey($media2->id, $results);
        $this->assertTrue($results[$media1->id]['success']);
        $this->assertTrue($results[$media2->id]['success']);
    }

    public function test_batch_process_handles_nonexistent_media()
    {
        $results = $this->service->batchProcess(
            [99999],
            function ($media) {
                return ['processed' => true];
            }
        );

        $this->assertArrayHasKey(99999, $results);
        $this->assertFalse($results[99999]['success']);
        $this->assertArrayHasKey('error', $results[99999]);
    }

    public function test_batch_process_handles_exceptions()
    {
        $media = Media::factory()->create([
            'file_path' => 'test.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $results = $this->service->batchProcess(
            [$media->id],
            function ($media) {
                throw new \Exception('Test exception');
            }
        );

        $this->assertFalse($results[$media->id]['success']);
        $this->assertEquals('Test exception', $results[$media->id]['error']);
    }

    public function test_get_thumbnail_path_creates_directory()
    {
        $media = Media::factory()->create([
            'file_path' => 'images/test.jpg',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getThumbnailPath');
        $method->setAccessible(true);

        $thumbnailPath = $method->invoke($this->service, 'images/test.jpg', 'small');

        $this->assertStringContainsString('thumbnails/small', $thumbnailPath);
        $this->assertStringEndsWith('test.jpg', $thumbnailPath);
    }
}
