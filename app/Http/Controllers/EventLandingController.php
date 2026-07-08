<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EventLandingController extends Controller
{
    public function index()
    {
        $activeEvent = \App\Models\Event::where('status', 'active')->first();

        if ($activeEvent) {
            return redirect("/events/{$activeEvent->slug}/index.html");
        }

        $pastEvents = \App\Models\Event::where('status', 'completed')
            ->orderBy('start_time', 'desc')
            ->paginate(12);

        return view('event.list', compact('pastEvents'));
    }

    public function show($slug)
    {
        $event = \App\Models\Event::where('slug', $slug)->firstOrFail();
        
        return redirect("/events/{$event->slug}/index.html");
    }
}
