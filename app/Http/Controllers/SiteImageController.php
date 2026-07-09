<?php

namespace App\Http\Controllers;

use App\Models\SiteImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SiteImageController extends Controller
{
    /** Admin Blade page listing the managed image slots. */
    public function index()
    {
        $images = SiteImage::orderBy('id')->get();
        return view('admin.site-images.index', compact('images'));
    }

    /** Replace the image for a single slot (identified by its key). */
    public function update(Request $request, $key)
    {
        $slot = SiteImage::where('key', $key)->firstOrFail();

        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
        ]);

        $destination = public_path('site');
        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        // Remove the previous file if it lives in our folder.
        if ($slot->image) {
            $old = public_path($slot->image);
            if (File::exists($old) && str_starts_with(realpath($old) ?: '', realpath($destination) ?: '')) {
                @File::delete($old);
            }
        }

        $file = $request->file('image');
        $filename = $slot->key . '_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($destination, $filename);
        $slot->image = 'site/' . $filename;
        $slot->save();

        return redirect()->route('admin.site-images.index')->with('success', $slot->label . ' updated.');
    }

    /** Public JSON: { key: url|null } for the frontend to read hero images. */
    public function api()
    {
        $map = SiteImage::orderBy('id')->get()
            ->mapWithKeys(fn ($s) => [$s->key => $s->image ? asset($s->image) : null]);

        return response()->json(['status' => true, 'data' => $map]);
    }
}
