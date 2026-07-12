<?php

namespace App\Http\Controllers\Api;

use App\CacheGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $data = CacheGroup::for('categories')->remember(
            'categories:index',
            3600,
            fn () => CategoryResource::collection(Category::orderBy('name')->get())->resolve(),
        );

        return $this->successResponse($data,
            'Categories retrieved successfully!'
        );
    }
}
