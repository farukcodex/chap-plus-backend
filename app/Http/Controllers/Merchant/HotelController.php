<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class HotelController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $hotels = Hotel::with(['images', 'reviews'])
            ->where('merchant_profile_id', $merchantProfile->id)
            ->latest()
            ->paginate(15);

        return $this->apiSuccess('Hotels retrieved successfully', [
            'hotels' => $hotels
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_per_night' => 'required|numeric|min:0',
            'room_quantity' => 'required|integer|min:1',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
        ]);

        try {
            DB::beginTransaction();

            $hotel = Hotel::create([
                'merchant_profile_id' => $merchantProfile->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price_per_night' => $validated['price_per_night'],
                'room_quantity' => $validated['room_quantity'],
                'facilities' => $validated['facilities'] ?? [],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if ($request->hasFile('images')) {
                $isPrimary = true;
                foreach ($request->file('images') as $image) {
                    $path = $image->store('hotel_images', 'public');
                    HotelImage::create([
                        'hotel_id' => $hotel->id,
                        'image_path' => $path,
                        'is_primary' => $isPrimary
                    ]);
                    $isPrimary = false; 
                }
            }

            DB::commit();

            return $this->apiSuccess('Hotel created successfully', ['hotel' => $hotel->load('images')], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to create hotel', 500, ['error' => $e->getMessage()]);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $hotel = Hotel::with(['images', 'reviews'])->where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$hotel) {
            return $this->apiError('Hotel not found', 404);
        }

        return $this->apiSuccess('Hotel details retrieved', ['hotel' => $hotel]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $hotel = Hotel::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$hotel) {
            return $this->apiError('Hotel not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_per_night' => 'required|numeric|min:0',
            'room_quantity' => 'required|integer|min:0',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'images_to_delete' => 'nullable|array',
            'images_to_delete.*' => 'integer|exists:hotel_images,id',
        ]);

        try {
            DB::beginTransaction();

            $hotel->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price_per_night' => $validated['price_per_night'],
                'room_quantity' => $validated['room_quantity'],
                'facilities' => $validated['facilities'] ?? $hotel->facilities,
                'is_active' => $validated['is_active'] ?? $hotel->is_active,
            ]);

            if (!empty($validated['images_to_delete'])) {
                $imagesToDelete = HotelImage::where('hotel_id', $hotel->id)
                    ->whereIn('id', $validated['images_to_delete'])
                    ->get();

                foreach ($imagesToDelete as $img) {
                    Storage::disk('public')->delete($img->image_path);
                    $img->delete();
                }
            }

            if ($request->hasFile('images')) {
                $hasPrimary = HotelImage::where('hotel_id', $hotel->id)->where('is_primary', true)->exists();
                $isPrimary = !$hasPrimary;

                foreach ($request->file('images') as $image) {
                    $path = $image->store('hotel_images', 'public');
                    HotelImage::create([
                        'hotel_id' => $hotel->id,
                        'image_path' => $path,
                        'is_primary' => $isPrimary
                    ]);
                    $isPrimary = false;
                }
            }

            DB::commit();

            return $this->apiSuccess('Hotel updated successfully', ['hotel' => $hotel->fresh('images')]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to update hotel', 500, ['error' => $e->getMessage()]);
        }
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $validated = $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $hotel = Hotel::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$hotel) {
            return $this->apiError('Hotel not found', 404);
        }

        $hotel->update(['is_active' => $validated['is_active']]);

        return $this->apiSuccess('Hotel status updated');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $hotel = Hotel::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$hotel) {
            return $this->apiError('Hotel not found', 404);
        }

        foreach ($hotel->images as $img) {
            Storage::disk('public')->delete($img->image_path);
        }
        
        $hotel->delete();

        return $this->apiSuccess('Hotel deleted successfully');
    }
}
