<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\RiderProfile;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class RiderController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $query = User::role('RIDER')->with('riderProfile');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('email', 'LIKE', "%{$search}%")
                  ->orWhereHas('riderProfile', function($rpq) use ($search) {
                      $rpq->where('phone_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->has('status')) {
            $status = $request->status;
            $query->whereHas('riderProfile', function($rpq) use ($status) {
                $rpq->where('status', $status);
            });
        }

        $riders = $query->latest()->paginate(20);

        return $this->apiSuccess('Riders retrieved successfully', ['riders' => $riders]);
    }

    public function show(string $id): JsonResponse
    {
        $rider = User::role('RIDER')->with('riderProfile')->find($id);

        if (!$rider) {
            return $this->apiError('Rider not found', 404);
        }

        return $this->apiSuccess('Rider details retrieved', ['rider' => $rider]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected'
        ]);

        $rider = User::role('RIDER')->with('riderProfile')->find($id);

        if (!$rider || !$rider->riderProfile) {
            return $this->apiError('Rider profile not found', 404);
        }

        $rider->riderProfile->update([
            'status' => $validated['status']
        ]);

        return $this->apiSuccess('Rider status updated successfully', ['rider' => $rider]);
    }
}
