<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\UserAddress;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserAddressController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $addresses = UserAddress::where('user_id', Auth::id())->get();
        return $this->apiSuccess('Addresses retrieved successfully', ['addresses' => $addresses]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'address_text' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $address = UserAddress::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'address_text' => $validated['address_text'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        return $this->apiSuccess('Address created successfully', ['address' => $address], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $address = UserAddress::where('user_id', Auth::id())->find($id);

        if (!$address) {
            return $this->apiError('Address not found', 404);
        }

        $address->delete();

        return $this->apiSuccess('Address deleted successfully');
    }
}
