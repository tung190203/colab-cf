<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $events = \App\Models\Event::orderBy('created_at', 'desc')->get();
        return response()->json($events);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:events,slug',
            'short_description' => 'nullable|string',
            'content' => 'nullable|string',
            'image_url' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'status' => 'required|in:active,completed,draft',
        ]);
        
        $validated['content'] = $validated['content'] ?? '';

        $event = \App\Models\Event::create($validated);

        return response()->json($event, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $event = \App\Models\Event::findOrFail($id);
        return response()->json($event);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $event = \App\Models\Event::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:events,slug,' . $id,
            'short_description' => 'nullable|string',
            'content' => 'nullable|string',
            'image_url' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'status' => 'required|in:active,completed,draft',
        ]);
        
        $validated['content'] = $validated['content'] ?? '';

        $event->update($validated);

        return response()->json($event);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $event = \App\Models\Event::findOrFail($id);
        $event->delete();

        return response()->json(['message' => 'Event deleted']);
    }

    /**
     * Upload and extract a ZIP file containing a static landing page.
     */
    public function uploadZip(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:51200',
        ]);

        $file = $request->file('zip_file');
        
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slugBase = \Illuminate\Support\Str::slug($originalName);
        if (empty($slugBase)) {
            $slugBase = 'event-' . time();
        }

        $slug = $slugBase;
        $counter = 1;
        while (\App\Models\Event::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $counter;
            $counter++;
        }

        $extractPath = public_path('events/' . $slug);
        
        $zip = new \ZipArchive;
        $res = $zip->open($file->getRealPath());
        if ($res === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
            
            $directories = \Illuminate\Support\Facades\File::directories($extractPath);
            $files = \Illuminate\Support\Facades\File::files($extractPath);
            
            if (count($directories) === 1 && count($files) === 0) {
                $subDir = $directories[0];
                $subFiles = \Illuminate\Support\Facades\File::files($subDir);
                $subDirs = \Illuminate\Support\Facades\File::directories($subDir);
                
                foreach ($subDirs as $dir) {
                    \Illuminate\Support\Facades\File::moveDirectory($dir, $extractPath . '/' . basename($dir));
                }
                foreach ($subFiles as $f) {
                    \Illuminate\Support\Facades\File::move($f->getRealPath(), $extractPath . '/' . $f->getFilename());
                }
                \Illuminate\Support\Facades\File::deleteDirectory($subDir);
            }
        } else {
            return response()->json(['message' => 'Lỗi không thể giải nén file ZIP'], 500);
        }

        $event = \App\Models\Event::create([
            'title' => 'Sự kiện ' . str_replace('-', ' ', $originalName),
            'slug' => $slug,
            'status' => 'draft',
            'start_time' => now(),
            'end_time' => now()->addDays(7),
            'content' => '',
            'short_description' => ''
        ]);

        return response()->json($event, 201);
    }
}
