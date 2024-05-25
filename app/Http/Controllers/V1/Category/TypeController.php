<?php

namespace App\Http\Controllers\V1\Category;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use Illuminate\Http\Request;

class TypeController extends Controller
{
    public function showByCategory($category)
    {
        $productTypes = ProductType::where('product_category_id', $category)->get();
        return response()->json(['productTypes' => $productTypes], 200);
    }
}