<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\BusImage;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class BusController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $buses = Bus::with(['images'])
            ->where('merchant_profile_id', $merchantProfile->id)
            ->latest()
            ->paginate(15);

        return $this->apiSuccess('Buses retrieved successfully', [
            'buses' => $buses
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_per_seat' => 'required|numeric|min:0',
            'bus_type' => 'required|string',
            'driver_position' => 'required|string',
            'seat_pattern' => 'required|string',
            'total_rows' => 'required|integer|min:1',
            'back_row_seats' => 'required|integer|min:0',
            'total_bookable_seats' => 'required|integer|min:1',
            'has_middle_door' => 'boolean',
            'departure_place' => 'required|string|max:255',
            'departure_time' => 'required|string|max:255',
            'rest_place' => 'nullable|string|max:255',
            'rest_duration' => 'nullable|string|max:255',
            'destination_place' => 'required|string|max:255',
            'destination_time' => 'required|string|max:255',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
        ]);

        try {
            DB::beginTransaction();

            $busData = $validated;
            unset($busData['images']);
            $busData['merchant_profile_id'] = $merchantProfile->id;

            $bus = Bus::create($busData);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('bus_images', 'public');
                    BusImage::create([
                        'bus_id' => $bus->id,
                        'image_path' => $path,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            DB::commit();

            return $this->apiSuccess('Bus created successfully', [
                'bus' => $bus->load('images')
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to create bus: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $bus = Bus::with(['images'])
            ->where('merchant_profile_id', $merchantProfile->id)
            ->find($id);

        if (!$bus) {
            return $this->apiError('Bus not found', 404);
        }

        return $this->apiSuccess('Bus details retrieved successfully', ['bus' => $bus]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $bus = Bus::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$bus) {
            return $this->apiError('Bus not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price_per_seat' => 'sometimes|numeric|min:0',
            'bus_type' => 'sometimes|string',
            'driver_position' => 'sometimes|string',
            'seat_pattern' => 'sometimes|string',
            'total_rows' => 'sometimes|integer|min:1',
            'back_row_seats' => 'sometimes|integer|min:0',
            'total_bookable_seats' => 'sometimes|integer|min:1',
            'has_middle_door' => 'boolean',
            'departure_place' => 'sometimes|string|max:255',
            'departure_time' => 'sometimes|string|max:255',
            'rest_place' => 'nullable|string|max:255',
            'rest_duration' => 'nullable|string|max:255',
            'destination_place' => 'sometimes|string|max:255',
            'destination_time' => 'sometimes|string|max:255',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'is_active' => 'boolean',
            'images_to_delete' => 'nullable|array',
            'images_to_delete.*' => 'exists:bus_images,id',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
        ]);

        try {
            DB::beginTransaction();

            $busData = $validated;
            unset($busData['images_to_delete'], $busData['images']);

            $bus->update($busData);

            if (!empty($validated['images_to_delete'])) {
                $imagesToDelete = BusImage::whereIn('id', $validated['images_to_delete'])
                    ->where('bus_id', $bus->id)
                    ->get();

                foreach ($imagesToDelete as $img) {
                    if (Storage::disk('public')->exists($img->image_path)) {
                        Storage::disk('public')->delete($img->image_path);
                    }
                    $img->delete();
                }
            }

            if ($request->hasFile('images')) {
                $hasPrimary = $bus->images()->where('is_primary', true)->exists();

                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('bus_images', 'public');
                    BusImage::create([
                        'bus_id' => $bus->id,
                        'image_path' => $path,
                        'is_primary' => !$hasPrimary && $index === 0,
                    ]);
                    if (!$hasPrimary && $index === 0) $hasPrimary = true;
                }
            }

            DB::commit();

            return $this->apiSuccess('Bus updated successfully', [
                'bus' => $bus->load('images')
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to update bus: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $bus = Bus::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$bus) {
            return $this->apiError('Bus not found', 404);
        }

        try {
            foreach ($bus->images as $img) {
                if (Storage::disk('public')->exists($img->image_path)) {
                    Storage::disk('public')->delete($img->image_path);
                }
            }
            $bus->delete();

            return $this->apiSuccess('Bus deleted successfully');
        } catch (Exception $e) {
            return $this->apiError('Failed to delete bus: ' . $e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $bus = Bus::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$bus) {
            return $this->apiError('Bus not found', 404);
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $bus->update(['is_active' => $validated['is_active']]);

        $statusText = $bus->is_active ? 'activated' : 'deactivated';
        return $this->apiSuccess("Bus successfully {$statusText}", [
            'bus' => $bus
        ]);
    }

    public function deleteImage(Request $request, string $imageId): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $image = \App\Models\BusImage::whereHas('bus', function ($q) use ($merchantProfile) {
            $q->where('merchant_profile_id', $merchantProfile->id);
        })->find($imageId);

        if (!$image) {
            return $this->apiError('Image not found', 404);
        }

        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();

        return $this->apiSuccess('Image deleted successfully');
    }
}
