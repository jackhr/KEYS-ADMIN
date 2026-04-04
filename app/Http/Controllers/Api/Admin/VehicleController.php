<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        $vehicles = Vehicle::query()
            ->orderByRaw('COALESCE(landing_order, 999999) ASC')
            ->orderBy('id')
            ->get()
            ->map(fn (Vehicle $vehicle): array => $this->toPayload($vehicle))
            ->all();

        return response()->json([
            'success' => true,
            'data' => $vehicles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validateVehicle($request);
        $payload['slug'] = $this->resolveVehicleSlug($payload['name']);

        return DB::transaction(function () use ($payload, $request): JsonResponse {
            $vehicle = Vehicle::query()->create($payload);
            $this->syncVehicleImage($vehicle, $vehicle->slug, null, $request->file('image'));

            return response()->json([
                'success' => true,
                'message' => 'Vehicle created.',
                'data' => $this->toPayload($vehicle),
            ], 201);
        });
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $originalSlug = $vehicle->slug;
        $originalImageFilename = $vehicle->image_filename;
        $payload = $this->validateVehicle($request, true);

        if (array_key_exists('name', $payload)) {
            $payload['slug'] = $this->resolveVehicleSlug($payload['name'], $vehicle);
        }

        return DB::transaction(function () use ($request, $vehicle, $payload, $originalSlug, $originalImageFilename): JsonResponse {
            $vehicle->fill($payload);
            $vehicle->save();
            $this->syncVehicleImage($vehicle, $originalSlug, $originalImageFilename, $request->file('image'));

            return response()->json([
                'success' => true,
                'message' => 'Vehicle updated.',
                'data' => $this->toPayload($vehicle),
            ]);
        });
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $vehicleId = $vehicle->id;
        $imageFilename = $vehicle->image_filename;

        try {
            $vehicle->delete();
        } catch (QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle cannot be deleted because related records exist.',
            ], 409);
        }

        $this->deleteCustomImageIfUnused($imageFilename, $vehicleId);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted.',
        ]);
    }

    private function validateVehicle(Request $request, bool $partial = false): array
    {
        $this->assertImageUploadSucceeded();

        $required = $partial ? 'sometimes' : 'required';
        $optional = $partial ? 'sometimes' : 'nullable';

        $validated = $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:60'],
            'type' => [$required, 'string', 'min:2', 'max:30'],
            'showing' => [$optional, 'boolean'],
            'landing_order' => [$optional, 'nullable', 'integer', 'min:1', 'max:999999'],
            'base_price_XCD' => [$required, 'numeric', 'min:0'],
            'base_price_USD' => [$required, 'numeric', 'min:0'],
            'insurance' => [$required, 'integer', 'min:0', 'max:100000'],
            'times_requested' => [$optional, 'integer', 'min:0'],
            'people' => [$required, 'integer', 'min:1', 'max:30'],
            'bags' => [$optional, 'nullable', 'integer', 'min:0', 'max:30'],
            'doors' => [$required, 'integer', 'min:1', 'max:10'],
            'four_wd' => [$optional, 'boolean'],
            'ac' => [$optional, 'boolean'],
            'manual' => [$optional, 'boolean'],
            'image' => [
                $partial ? 'sometimes' : 'nullable',
                'file',
                'max:10240',
            ],
        ]);

        if (array_key_exists('four_wd', $validated)) {
            $validated['4wd'] = $validated['four_wd'];
            unset($validated['four_wd']);
        }

        if ($request->hasFile('image') && $this->detectedImageExtension($request->file('image')) === null) {
            throw ValidationException::withMessages([
                'image' => ['Image format is unsupported or the file is corrupted.'],
            ]);
        }

        return $validated;
    }

    private function toPayload(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'type' => $vehicle->type,
            'slug' => $vehicle->slug,
            'showing' => (bool) $vehicle->showing,
            'landing_order' => $vehicle->landing_order,
            'base_price_XCD' => (float) $vehicle->base_price_XCD,
            'base_price_USD' => (float) $vehicle->base_price_USD,
            'insurance' => (int) $vehicle->insurance,
            'times_requested' => (int) $vehicle->times_requested,
            'people' => (int) $vehicle->people,
            'bags' => $vehicle->bags !== null ? (int) $vehicle->bags : null,
            'doors' => (int) $vehicle->doors,
            'four_wd' => (bool) $vehicle->getAttribute('4wd'),
            'ac' => (bool) $vehicle->ac,
            'manual' => (bool) $vehicle->manual,
            'image_url' => $this->imageUrl($vehicle),
        ];
    }

    private function syncVehicleImage(
        Vehicle $vehicle,
        string $originalSlug,
        ?string $originalImageFilename,
        ?UploadedFile $image
    ): void {
        $this->ensureGalleryExists();

        if ($image !== null) {
            $this->replaceVehicleImage($vehicle, $image, $originalSlug, $originalImageFilename);

            return;
        }

        if ($originalSlug === $vehicle->slug) {
            return;
        }

        if ($originalImageFilename !== null) {
            $this->renameCustomImageToMatchSlug($vehicle, $originalImageFilename);

            return;
        }

        $this->promoteLegacyImageToCurrentSlug($vehicle, $originalSlug);
    }

    private function replaceVehicleImage(
        Vehicle $vehicle,
        UploadedFile $image,
        string $originalSlug,
        ?string $originalImageFilename
    ): void {
        $sourcePath = $image->getRealPath();
        $extension = $this->detectedImageExtension($image);

        if ($sourcePath === false || ! is_file($sourcePath) || $extension === null) {
            throw ValidationException::withMessages([
                'image' => ['Image format is unsupported or the file is corrupted.'],
            ]);
        }

        $filename = $this->slugImageFilename($vehicle->slug, $extension);

        if (! File::copy($sourcePath, $this->storedImagePath($filename))) {
            throw ValidationException::withMessages([
                'image' => ['Image could not be saved.'],
            ]);
        }

        $vehicle->forceFill([
            'image_filename' => $filename,
        ])->saveQuietly();

        if ($originalImageFilename !== null) {
            if ($originalImageFilename !== $filename) {
                $this->deleteCustomImageIfUnused($originalImageFilename, $vehicle->id);
            }

            return;
        }

        $this->deleteLegacyImageIfUnused($originalSlug, $vehicle->id, $filename);
    }

    private function renameCustomImageToMatchSlug(Vehicle $vehicle, string $originalImageFilename): void
    {
        $extension = $this->filenameExtension($originalImageFilename);

        if ($extension === null) {
            return;
        }

        $newFilename = $this->slugImageFilename($vehicle->slug, $extension);

        if ($newFilename === $originalImageFilename) {
            return;
        }

        $originalPath = $this->storedImagePath($originalImageFilename);

        if (! File::exists($originalPath)) {
            return;
        }

        $newPath = $this->storedImagePath($newFilename);

        if ($this->customImageInUseByOthers($originalImageFilename, $vehicle->id)) {
            File::copy($originalPath, $newPath);
        } else {
            File::move($originalPath, $newPath);
        }

        $vehicle->forceFill([
            'image_filename' => $newFilename,
        ])->saveQuietly();
    }

    private function promoteLegacyImageToCurrentSlug(Vehicle $vehicle, string $originalSlug): void
    {
        $oldFilename = $this->detectedLegacyImageFilename($originalSlug);

        if ($oldFilename === null) {
            return;
        }

        $extension = $this->filenameExtension($oldFilename);

        if ($extension === null) {
            return;
        }

        $newFilename = $this->legacyImageFilename($vehicle->slug, $extension);

        if ($oldFilename === $newFilename) {
            return;
        }

        $oldPath = $this->storedImagePath($oldFilename);

        if (! File::exists($oldPath)) {
            return;
        }

        $newPath = $this->storedImagePath($newFilename);

        if ($this->legacyImageInUseByOthers($originalSlug, $vehicle->id)) {
            File::copy($oldPath, $newPath);
        } else {
            File::move($oldPath, $newPath);
        }

        $vehicle->forceFill([
            'image_filename' => $newFilename,
        ])->saveQuietly();
    }

    private function imageUrl(Vehicle $vehicle): string
    {
        $prefix = rtrim((string) config('admin.vehicle_image_url_prefix', '/gallery/'), '/');

        return $prefix.'/'.$this->resolvedImageFilename($vehicle);
    }

    private function resolvedImageFilename(Vehicle $vehicle): string
    {
        $imageFilename = trim((string) ($vehicle->image_filename ?? ''));

        if ($imageFilename !== '') {
            return $imageFilename;
        }

        return $this->detectedLegacyImageFilename($vehicle->slug) ?? $this->legacyImageFilename($vehicle->slug, 'avif');
    }

    private function galleryPath(): string
    {
        return rtrim((string) config('admin.vehicle_gallery_path', public_path('gallery')), DIRECTORY_SEPARATOR);
    }

    private function ensureGalleryExists(): void
    {
        $galleryPath = $this->galleryPath();

        if (! File::exists($galleryPath)) {
            File::makeDirectory($galleryPath, 0755, true);
        }
    }

    private function storedImagePath(string $filename): string
    {
        return $this->galleryPath().DIRECTORY_SEPARATOR.$filename;
    }

    private function legacyImageFilename(string $slug, string $extension): string
    {
        return $slug.'.'.$extension;
    }

    private function slugImageFilename(string $slug, string $extension): string
    {
        return $slug.'.'.strtolower($extension);
    }

    private function filenameExtension(string $filename): ?string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpeg' => 'jpg',
            'avif', 'jpg', 'png', 'webp' => $extension,
            default => null,
        };
    }

    private function resolveVehicleSlug(string $name, ?Vehicle $vehicle = null): string
    {
        $baseSlug = $this->slugFromName($name);

        for ($suffix = 1; $suffix <= 999; $suffix++) {
            $candidate = $suffix === 1 ? $baseSlug : $baseSlug.'-'.$suffix;
            $query = Vehicle::query()->where('slug', $candidate);

            if ($vehicle !== null) {
                $query->where('id', '!=', $vehicle->id);
            }

            if (! $query->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'name' => ['Unable to generate a unique slug for this vehicle.'],
        ]);
    }

    private function slugFromName(string $name): string
    {
        $slug = Str::of($name)->ascii()->lower()->toString();
        $slug = (string) preg_replace('/\s+/', '_', $slug);
        $slug = (string) preg_replace('/[^a-z0-9_-]+/', '', $slug);
        $slug = (string) preg_replace('/_{2,}/', '_', $slug);
        $slug = (string) preg_replace('/-{2,}/', '-', $slug);
        $slug = trim($slug, '_-');

        return $slug !== '' ? $slug : 'vehicle';
    }

    private function customImageInUseByOthers(string $filename, int $vehicleId): bool
    {
        return Vehicle::query()
            ->where('image_filename', $filename)
            ->where('id', '!=', $vehicleId)
            ->exists();
    }

    private function deleteCustomImageIfUnused(?string $filename, int $vehicleId): void
    {
        $filename = trim((string) $filename);

        if ($filename === '' || $this->customImageInUseByOthers($filename, $vehicleId)) {
            return;
        }

        $path = $this->storedImagePath($filename);

        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function legacyImageInUseByOthers(string $slug, int $vehicleId): bool
    {
        return Vehicle::query()
            ->where('slug', $slug)
            ->where('id', '!=', $vehicleId)
            ->whereNull('image_filename')
            ->exists();
    }

    private function deleteLegacyImageIfUnused(string $slug, int $vehicleId, string $replacementFilename): void
    {
        if ($this->legacyImageInUseByOthers($slug, $vehicleId)) {
            return;
        }

        foreach ($this->allLegacyImageFilenames($slug) as $legacyFilename) {
            if ($legacyFilename === $replacementFilename) {
                continue;
            }

            $legacyPath = $this->storedImagePath($legacyFilename);

            if (File::exists($legacyPath)) {
                File::delete($legacyPath);
            }
        }
    }

    /** @return array<int, string> */
    private function allLegacyImageFilenames(string $slug): array
    {
        $filenames = [];

        foreach (['avif', 'webp', 'jpg', 'png'] as $extension) {
            $filename = $this->legacyImageFilename($slug, $extension);

            if (File::exists($this->storedImagePath($filename))) {
                $filenames[] = $filename;
            }
        }

        return $filenames;
    }

    private function detectedLegacyImageFilename(string $slug): ?string
    {
        foreach ($this->allLegacyImageFilenames($slug) as $filename) {
            return $filename;
        }

        return null;
    }

    private function detectedImageExtension(UploadedFile $image): ?string
    {
        $path = $image->getRealPath();
        $type = $this->detectedImageType($path);

        if ($type !== false) {
            return match ($type) {
                IMAGETYPE_AVIF => 'avif',
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
                default => null,
            };
        }

        $mimeType = ($path !== false && \class_exists(\finfo::class))
            ? (new \finfo(FILEINFO_MIME_TYPE))->file($path)
            : false;

        if (is_string($mimeType) && $mimeType !== '') {
            return match (strtolower($mimeType)) {
                'image/avif', 'image/heif' => 'avif',
                'image/jpeg', 'image/pjpeg' => 'jpg',
                'image/png', 'image/x-png' => 'png',
                'image/webp' => 'webp',
                default => null,
            };
        }

        return match (strtolower($image->getClientOriginalExtension())) {
            'avif' => 'avif',
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => null,
        };
    }

    private function detectedImageType(string|false $path): int|false
    {
        if ($path === false || ! is_file($path)) {
            return false;
        }

        if (\function_exists('exif_imagetype')) {
            $type = @\exif_imagetype($path);

            if ($type !== false) {
                return $type;
            }
        }

        $imageInfo = @\getimagesize($path);
        $type = is_array($imageInfo) ? ($imageInfo[2] ?? false) : false;

        return is_int($type) ? $type : false;
    }

    private function assertImageUploadSucceeded(): void
    {
        $error = $_FILES['image']['error'] ?? null;

        if ($error === null || (int) $error === UPLOAD_ERR_OK) {
            return;
        }

        throw ValidationException::withMessages([
            'image' => [$this->uploadErrorMessage((int) $error)],
        ]);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                'The uploaded image exceeds the server limit. Current PHP limits: upload_max_filesize=%s, post_max_size=%s.',
                ini_get('upload_max_filesize') ?: 'unknown',
                ini_get('post_max_size') ?: 'unknown'
            ),
            UPLOAD_ERR_PARTIAL => 'The image upload was only partially received. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded image to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the image upload.',
            default => 'The image failed to upload before processing started.',
        };
    }
}
