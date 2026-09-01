<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    use ApiResponseTrait;

    public function show(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->first();

        if (!$page) {
            return $this->apiError('Page not found', 404, ['code' => 'PAGE_NOT_FOUND']);
        }

        return $this->apiSuccess('Page retrieved successfully', ['page' => $page]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'content' => 'nullable|string'
        ]);

        $page = Page::where('slug', $slug)->first();

        if (!$page) {
            return $this->apiError('Page not found', 404, ['code' => 'PAGE_NOT_FOUND']);
        }

        $page->update(['content' => $request->content]);

        return $this->apiSuccess('Page updated successfully', ['page' => $page]);
    }
}
