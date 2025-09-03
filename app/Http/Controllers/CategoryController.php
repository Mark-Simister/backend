<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // web CRUD
            new Middleware('permission:category.view',   only: ['index']),
            new Middleware('permission:category.create', only: ['create','store']),
            new Middleware('permission:category.edit',   only: ['edit','update']),
            new Middleware('permission:category.delete', only: ['destroy']),

        ];
    }

    // public function index()
    // {
    //     $categories = Category::latest()->paginate(10);
    //     return view('admin.categories.index', compact('categories'));
    // }
    public function index()
    {
        $categories = Category::latest()->get(); 
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
{
    $channels = \App\Models\Channel::all();
    return view('admin.categories.create', compact('channels'));
}

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:categories,name',
            'channel_id' => 'required|exists:channels,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $folderPath = public_path('category');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
            $request->image->move($folderPath, $imageName);
            $imagePath = 'category/' . $imageName;
        }

        Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'channel_id' => $request->channel_id,
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully!');
    }




    public function edit(Category $category)
{
    $channels = \App\Models\Channel::all();
    return view('admin.categories.edit', compact('category', 'channels'));
}

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|unique:categories,name,' . $category->id,
            'channel_id' => 'required|exists:channels,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $imagePath = $category->image;
        if ($request->hasFile('image')) {
            $folderPath = public_path('category');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
            $request->image->move($folderPath, $imageName);
            $imagePath = 'category/' . $imageName;

            if ($category->image && file_exists(public_path($category->image))) {
                unlink(public_path($category->image));
            }
        }

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'channel_id' => $request->channel_id,
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully!');
    }

    public function destroy(Category $category)
    {
        if ($category->image && file_exists(public_path($category->image))) {
            unlink(public_path($category->image));
        }

        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }



    /**
     * Get all categories.
     */
    public function index_api()
    {
        $categories = Category::with('channel')->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Categories fetched successfully',
            'data' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'channel' => $category->channel,
                    'category_image' => $category->category_image 
                        ? asset($category->category_image) 
                        : null,
                    'created_at' => $category->created_at->toDateTimeString(),
                ];
            }),
        ]);
    }

    /**
     * Store a new category.
     */
    public function store_api(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name',
            'channel_id' => 'required|exists:channels,id',
            'category_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('category_image')) {
            $folderPath = public_path('category');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $imageName = time() . '_' . uniqid() . '.' . $request->category_image->extension();
            $request->category_image->move($folderPath, $imageName);
            $imagePath = 'category/' . $imageName;
        }

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'channel_id' => $request->channel_id,
            'category_image' => $imagePath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Show category details.
     */
    public function show_api($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Category details fetched successfully',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'channel_id' => $category->channel_id,
                'category_image' => $category->category_image 
                    ? asset($category->category_image) 
                    : null,
                'created_at' => $category->created_at->toDateTimeString(),
            ]
        ], 200);
    }

    /**
     * Update a category.
     */
    public function update_api(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'channel_id' => 'required|exists:channels,id',
            'category_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = $category->category_image;
        if ($request->hasFile('category_image')) {
            $folderPath = public_path('category');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $imageName = time() . '_' . uniqid() . '.' . $request->category_image->extension();
            $request->category_image->move($folderPath, $imageName);
            $imagePath = 'category/' . $imageName;

            if ($category->category_image && file_exists(public_path($category->category_image))) {
                unlink(public_path($category->category_image));
            }
        }

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'channel_id' => $request->channel_id,
            'category_image' => $imagePath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ], 200);
    }

    /**
     * Delete a category.
     */
    public function destroy_api($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }

        try {
            if ($category->category_image && file_exists(public_path($category->category_image))) {
                unlink(public_path($category->category_image));
            }

            $category->delete();

            return response()->json([
                'status' => true,
                'message' => 'Category deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}