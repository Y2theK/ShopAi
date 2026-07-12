<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : null;
        $category = is_string($request->query('category')) ? trim($request->query('category')) : null;
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        $data = Cache::tags(['products'])->remember(
            sprintf('products:index:%s:%s:%d:%d', md5((string) $search), md5((string) $category), $page, $perPage),
            60,
            function () use ($search, $category, $perPage) {
                $products = Product::query()
                    ->with('category')
                    ->search($search)
                    ->inCategory($category)
                    ->latest('id')
                    ->paginate($perPage)
                    ->withQueryString();

                return [
                    'data' => ProductResource::collection($products)->resolve(),
                    'meta' => $this->getpaginatedMeta($products),
                ];
            }
        );

        return $this->successResponse($data,
            'Products retrieved successfully!'
        );
    }
}
