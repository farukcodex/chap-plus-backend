<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $pages = Page::all()->keyBy('slug');
        
        $response = [
            'about_us' => $pages->get('about-us')->content ?? null,
            'privacy_policy' => $pages->get('privacy-policy')->content ?? null,
            'terms_and_conditions' => $pages->get('terms-and-conditions')->content ?? null,
        ];

        return $this->apiSuccess('Pages retrieved successfully', $response);
    }

    public function show(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->first();

        if (!$page) {
            return $this->apiError('Page not found', 404, ['code' => 'PAGE_NOT_FOUND']);
        }

        return $this->apiSuccess('Page retrieved successfully', [
            'title' => $page->title,
            'content' => $page->content
        ]);
    }
}
