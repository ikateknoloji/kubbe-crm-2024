<?php

namespace App\Http\Controllers\V1\Category;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // 'product_categories' tablosundaki tüm kategorileri döndüren metod
    public function index()
    {
        $categories = ProductCategory::all();
        return response()->json(['categories' => $categories], 200);
    }
}