<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EventLandingController extends Controller
{
    public function index()
    {
        // Lấy sự kiện active mới nhất
        $activeEvent = \App\Models\Event::where('status', 'active')
            ->orderBy('start_time', 'desc')
            ->first();

        if ($activeEvent) {
            return redirect("/events/{$activeEvent->slug}/index.html", 302)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $pastEvents = \App\Models\Event::where('status', 'completed')
            ->orderBy('start_time', 'desc')
            ->paginate(12);

        return response()->view('event.list', compact('pastEvents'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function show($slug)
    {
        $event = \App\Models\Event::where('slug', $slug)->firstOrFail();
        
        return redirect("/events/{$event->slug}/index.html", 302)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
