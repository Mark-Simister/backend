<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public static function middleware(): array
    {
        return [
            new \Illuminate\Routing\Controllers\Middleware('auth'),

            new \Illuminate\Routing\Controllers\Middleware('permission:form.view', ['only' => ['index', 'show']]),
            new \Illuminate\Routing\Controllers\Middleware('permission:form.create', ['only' => ['create', 'store']]),
            new \Illuminate\Routing\Controllers\Middleware('permission:form.edit', ['only' => ['edit', 'update']]),
            new \Illuminate\Routing\Controllers\Middleware('permission:form.delete', ['only' => ['destroy']]),
        ];
    }

    // Show list of forms
    public function index()
    {
         $forms = Form::latest()->get();
        return view('admin.forms.index', compact('forms'));
    }

    // Show form creation page
    public function create()
    {
        return view('admin.forms.create');
    }

    // Store new form
    public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'cta_type' => 'required|in:apply_now,reachout',
        'fields' => 'required|array|min:1',
        'fields.*.label' => 'required|string|max:255',
        'fields.*.type' => 'required|in:text,email,number,textarea',
        'fields.*.required' => 'sometimes|boolean',

        'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
    ], [
        'fields.required' => 'The form must have at least one field.',
        'fields.min' => 'The form must have at least one field.',
    ]);

    // Normalize fields
    $fields = array_map(function ($field) {
        $field['key'] = \Str::slug($field['label'], '_'); // generate consistent key
        return $field;
    }, $request->fields);

    // Handle video upload
    $videoPath = $request->old_video_path ?? null;
    if ($request->hasFile('video')) {
        $videoName = time() . '.' . $request->video->extension();
        $request->video->move(public_path('videos'), $videoName);
        $videoPath = 'videos/' . $videoName;
    }

    
    $imagePath = null;
    if ($request->hasFile('image')) {
        $folderPath = public_path('channel');
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
        }
        $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
        $request->image->move($folderPath, $imageName);
        $imagePath = 'channel/' . $imageName;
    }

    Form::create([
        'name' => $request->name,
        'cta_type' => $request->cta_type,
        'video_path' => $videoPath,
        'video_thumbnail' => $imagePath, 
        'fields' => $fields,
        // 'is_active' => true, 
    ]);

    return redirect()->route('admin.forms.index')->with('success', 'Form created successfully!');
}




    // Show single form with video + dynamic fields
    public function show(Form $form)
    {
        return view('admin.forms.show', compact('form'));
    }

    // Handle submission
    public function submit(Request $request, Form $form)
    {
        $data = [];

        foreach ($form->fields as $field) {
            $key = $field['key'] ?? \Str::slug($field['label'], '_');
            $data[$key] = $request->input($key);
            // Optional: validate required dynamically
            if (!empty($field['required'])) {
                $request->validate([$key => 'required']);
            }
        }

        FormSubmission::create([
            'form_id' => $form->id,
            'user_id' => auth()->id(), // null if guest
            'data' => $data,
        ]);

        return back()->with('success', 'Form submitted successfully!');
    }


    // Show the edit form
    public function edit(Form $form)
    {
        return view('admin.forms.edit', compact('form'));
    }

    // Update an existing form
    public function update(Request $request, Form $form)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'cta_type' => 'required|in:apply_now,reachout',
        'fields' => 'required|array|min:1',
        'fields.*.label' => 'required|string|max:255',
        'fields.*.type' => 'required|in:text,email,number,textarea',
        'fields.*.required' => 'sometimes|boolean',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
    ], [
        'fields.required' => 'The form must have at least one field.',
        'fields.min' => 'The form must have at least one field.',
    ]);

    $fields = array_map(function ($field) {
        $field['key'] = $field['key'] ?? \Str::slug($field['label'], '_');
        return $field;
    }, $request->fields);

    $videoPath = $form->video_path;
    if ($request->hasFile('video')) {
        $videoName = time() . '.' . $request->video->extension();
        $request->video->move(public_path('videos'), $videoName);
        $videoPath = 'videos/' . $videoName;
    }

    $imagePath = $form->video_thumbnail;
    if ($request->hasFile('image')) {
        $folderPath = public_path('channel');
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
        }
        $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
        $request->image->move($folderPath, $imageName);
        $imagePath = 'channel/' . $imageName;
    }

    $form->update([
        'name' => $request->name,
        'cta_type' => $request->cta_type,
        'video_path' => $videoPath,
        'video_thumbnail' => $imagePath,
        'fields' => $fields,
    ]);

    return redirect()->route('admin.forms.index')->with('success', 'Form updated successfully!');
}





    // Delete a form
    public function destroy(Form $form)
{
    if ($form->video_path && file_exists(public_path($form->video_path))) {
        unlink(public_path($form->video_path));
    }

    if ($form->video_thumbnail && file_exists(public_path($form->video_thumbnail))) {
        unlink(public_path($form->video_thumbnail));
    }

    $form->delete();

    return redirect()->route('admin.forms.index')->with('success', 'Form deleted successfully!');
}



    public function submissionsPageNew()
    {
        $forms = Form::all();
        return view('admin.forms.select_form_submissions', compact('forms'));
    }

    public function submissions(Form $form)
    {
        $forms = Form::all(); // <--- add this
        $submissions = $form->submissions()->latest()->get();
        return view('admin.forms.submissions', compact('form', 'submissions', 'forms'));
    }



    // api's

    public function apiIndex()
    {
        $forms = Form::where('is_active', true)->get();

        return response()->json([
            'status' => 'success',
            'data' => $forms->map(function ($form) {
                return [
                    'id' => $form->id,
                    'name' => $form->name,
                    'cta_type' => $form->cta_type,
                    'video_path' => $form->video_path ? asset($form->video_path) : null,
                    'fields' => $form->fields,
                ];
            }),
        ]);
    }

    public function apiSubmit(Request $request, Form $form)
    {
        $user = $request->user('api') ?? $request->user('sanctum') ?? null;
        // Check if the user is blocked
        if ($user && $user->is_blocked) {
            return response()->json([
                'status' => false,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403); // Forbidden
        }
        $user_id = $user->id ?? null;
        // dd($request,$form,$user);
        // Validate dynamically based on form fields
        $rules = [];
        foreach ($form->fields as $field) {
            $fieldName = \Str::slug($field['label'], '_'); // generate safe key
            if (!empty($field['required'])) {
                $rules[$fieldName] = 'required';
            }
        }

        $validated = $request->validate($rules);

        // Save submission
        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user_id,
            'data' => $validated,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Form submitted successfully!',
            'submission_id' => $submission->id,
        ]);
    }


}
