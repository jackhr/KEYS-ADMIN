<?php

namespace Tests\Feature;

use App\Models\AdminApiToken;
use App\Models\AdminUser;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class VehicleImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $galleryPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->galleryPath = storage_path('framework/testing/vehicle-gallery');

        if (File::exists($this->galleryPath)) {
            File::deleteDirectory($this->galleryPath);
        }

        File::makeDirectory($this->galleryPath, 0755, true, true);
        config()->set('admin.vehicle_gallery_path', $this->galleryPath);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->galleryPath)) {
            File::deleteDirectory($this->galleryPath);
        }

        parent::tearDown();
    }

    public function test_create_vehicle_generates_slug_and_filename_from_the_vehicle_name(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->post('/api/admin/vehicles', array_merge($this->vehiclePayload([
                'name' => 'Sunbird',
                'slug' => 'ignore-this-slug',
            ]), [
                'image' => $this->makeImageUpload('sunbird.png', 'png'),
            ]));

        $vehicle = Vehicle::query()->where('name', 'Sunbird')->firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'sunbird')
            ->assertJsonPath('data.image_url', '/gallery/sunbird.png');

        $this->assertSame('sunbird', $vehicle->slug);
        $this->assertSame('sunbird.png', $vehicle->image_filename);
        $this->assertFileExists($this->galleryPath.'/sunbird.png');
        $this->assertSame('image/png', (new \finfo(FILEINFO_MIME_TYPE))->file($this->galleryPath.'/sunbird.png'));
    }

    public function test_create_vehicle_uses_a_numeric_slug_suffix_when_the_name_already_exists(): void
    {
        $this->createVehicle([
            'name' => 'Sunbird',
            'slug' => 'sunbird',
        ]);

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('WebP fixture generation requires imagewebp() in the local PHP build.');
        }

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->post('/api/admin/vehicles', array_merge($this->vehiclePayload([
                'name' => 'Sunbird',
                'slug' => 'still-ignored',
            ]), [
                'image' => $this->makeImageUpload('sunbird.webp', 'webp'),
            ]));

        $vehicle = Vehicle::query()->where('slug', 'sunbird-2')->firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'sunbird-2')
            ->assertJsonPath('data.image_url', '/gallery/sunbird-2.webp');

        $this->assertSame('sunbird-2.webp', $vehicle->image_filename);
        $this->assertFileExists($this->galleryPath.'/sunbird-2.webp');
        $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->file($this->galleryPath.'/sunbird-2.webp'));
    }

    public function test_create_vehicle_accepts_an_avif_upload_and_preserves_the_extension(): void
    {
        $upload = $this->makeImageUpload('shoreline.avif', 'avif');
        $uploadContents = (string) File::get($upload->getRealPath());

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->post('/api/admin/vehicles', array_merge($this->vehiclePayload([
                'name' => 'Shoreline',
            ]), [
                'image' => $upload,
            ]));

        $vehicle = Vehicle::query()->where('slug', 'shoreline')->firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.image_url', '/gallery/shoreline.avif');

        $this->assertSame('shoreline.avif', $vehicle->image_filename);
        $this->assertFileExists($this->galleryPath.'/shoreline.avif');
        $this->assertSame($uploadContents, (string) File::get($this->galleryPath.'/shoreline.avif'));
    }

    public function test_update_vehicle_replaces_the_existing_file_with_the_new_extension(): void
    {
        $vehicle = $this->createVehicle([
            'name' => 'Sunbird',
            'slug' => 'sunbird',
            'image_filename' => 'sunbird.png',
        ]);
        File::put($this->galleryPath.'/sunbird.png', 'old-image');

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->post('/api/admin/vehicles/'.$vehicle->id, [
                '_method' => 'PUT',
                'name' => 'Sunbird',
                'image' => $this->makeImageUpload('replacement.jpg', 'jpeg'),
            ]);

        $vehicle->refresh();

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'sunbird')
            ->assertJsonPath('data.image_url', '/gallery/sunbird.jpg');

        $this->assertSame('sunbird.jpg', $vehicle->image_filename);
        $this->assertFileDoesNotExist($this->galleryPath.'/sunbird.png');
        $this->assertFileExists($this->galleryPath.'/sunbird.jpg');
        $this->assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->file($this->galleryPath.'/sunbird.jpg'));
    }

    public function test_update_vehicle_name_renames_the_existing_custom_image_file(): void
    {
        $vehicle = $this->createVehicle([
            'name' => 'Sunbird',
            'slug' => 'sunbird',
            'image_filename' => 'sunbird.png',
        ]);
        File::put($this->galleryPath.'/sunbird.png', 'custom-image');

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->post('/api/admin/vehicles/'.$vehicle->id, [
                '_method' => 'PUT',
                'name' => 'Ocean Runner',
            ]);

        $vehicle->refresh();

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'ocean_runner')
            ->assertJsonPath('data.image_url', '/gallery/ocean_runner.png');

        $this->assertSame('ocean_runner.png', $vehicle->image_filename);
        $this->assertFileDoesNotExist($this->galleryPath.'/sunbird.png');
        $this->assertFileExists($this->galleryPath.'/ocean_runner.png');
        $this->assertSame('custom-image', (string) File::get($this->galleryPath.'/ocean_runner.png'));
    }

    public function test_update_vehicle_name_promotes_a_shared_legacy_image_without_deleting_the_old_file(): void
    {
        $vehicle = $this->createVehicle([
            'name' => 'Shared SUV',
            'slug' => 'shared_suv',
        ]);
        $this->createVehicle([
            'name' => 'Shared SUV Two',
            'slug' => 'shared_suv',
        ]);
        File::put($this->galleryPath.'/shared_suv.avif', 'shared-image');

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->post('/api/admin/vehicles/'.$vehicle->id, [
                '_method' => 'PUT',
                'name' => 'Solo SUV',
            ]);

        $vehicle->refresh();

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'solo_suv')
            ->assertJsonPath('data.image_url', '/gallery/solo_suv.avif');

        $this->assertSame('solo_suv.avif', $vehicle->image_filename);
        $this->assertFileExists($this->galleryPath.'/shared_suv.avif');
        $this->assertFileExists($this->galleryPath.'/solo_suv.avif');
        $this->assertSame('shared-image', (string) File::get($this->galleryPath.'/solo_suv.avif'));
    }

    public function test_destroy_vehicle_deletes_an_unused_custom_image_file(): void
    {
        $vehicle = $this->createVehicle([
            'image_filename' => 'sunbird.png',
        ]);
        File::put($this->galleryPath.'/sunbird.png', 'custom-image');

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->delete('/api/admin/vehicles/'.$vehicle->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('vehicles', [
            'id' => $vehicle->id,
        ]);
        $this->assertFileDoesNotExist($this->galleryPath.'/sunbird.png');
    }

    /** @return array<string, string> */
    private function apiHeaders(): array
    {
        $admin = AdminUser::query()->create([
            'username' => 'admin',
            'password_hash' => 'not-used-in-this-test',
            'role' => 'admin',
            'active' => true,
        ]);

        $plainToken = 'test-admin-token';

        AdminApiToken::query()->create([
            'admin_user_id' => $admin->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHour(),
        ]);

        return [
            'Authorization' => 'Bearer '.$plainToken,
            'Accept' => 'application/json',
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function createVehicle(array $overrides = []): Vehicle
    {
        return Vehicle::query()->create($this->vehiclePayload($overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function vehiclePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sunbird',
            'type' => 'suv',
            'slug' => 'sunbird',
            'showing' => true,
            'landing_order' => 1,
            'base_price_XCD' => 200,
            'base_price_USD' => 75,
            'insurance' => 25,
            'times_requested' => 0,
            'people' => 5,
            'bags' => 3,
            'doors' => 4,
            '4wd' => false,
            'ac' => true,
            'manual' => false,
            'image_filename' => null,
        ], $overrides);
    }

    private function makeImageUpload(string $originalName, string $format): UploadedFile
    {
        if ($format === 'avif') {
            return $this->makeFixtureUpload($this->avifFixturePath(), $originalName);
        }

        $path = tempnam(sys_get_temp_dir(), 'vehicle-image-test-');

        if ($path === false) {
            throw new RuntimeException('Unable to create temporary file for image upload test.');
        }

        $image = imagecreatetruecolor(64, 48);
        $background = imagecolorallocate($image, 70, 120, 190);
        $foreground = imagecolorallocate($image, 255, 255, 255);

        imagefill($image, 0, 0, $background);
        imagefilledellipse($image, 32, 24, 28, 20, $foreground);

        $written = match ($format) {
            'png' => imagepng($image, $path),
            'jpeg' => imagejpeg($image, $path, 90),
            'webp' => imagewebp($image, $path, 90),
            default => false,
        };

        imagedestroy($image);

        if (! $written) {
            File::delete($path);

            throw new RuntimeException('Unable to generate image upload fixture.');
        }

        return new UploadedFile(
            $path,
            $originalName,
            (string) mime_content_type($path),
            null,
            true
        );
    }

    private function makeFixtureUpload(string $sourcePath, string $originalName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vehicle-image-fixture-');

        if ($path === false || ! File::copy($sourcePath, $path)) {
            throw new RuntimeException('Unable to copy image upload fixture.');
        }

        return new UploadedFile(
            $path,
            $originalName,
            (string) mime_content_type($path),
            null,
            true
        );
    }

    private function avifFixturePath(): string
    {
        $path = base_path('tests/Fixtures/vehicle-upload.avif');

        if (! File::exists($path)) {
            throw new RuntimeException('The AVIF upload fixture is missing.');
        }

        return $path;
    }
}
