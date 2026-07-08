<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Sự kiện | ColabSpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900 font-sans antialiased">
    <!-- Navbar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('event.home') }}" class="text-xl font-bold text-orange-600">ColabSpace Events</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-16 px-4 sm:py-24 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl lg:text-6xl">Sự kiện đã diễn ra</h1>
            <p class="mt-4 max-w-3xl mx-auto text-xl text-gray-500">Xem lại các sự kiện nổi bật đã được tổ chức tại ColabSpace.</p>
        </div>
    </div>

    <!-- Event List Grid -->
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        @if($pastEvents->count() > 0)
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($pastEvents as $event)
                    <div class="bg-white overflow-hidden shadow rounded-lg flex flex-col">
                        <div class="flex-shrink-0">
                            @if($event->image_url)
                                <img class="h-48 w-full object-cover" src="{{ $event->image_url }}" alt="">
                            @else
                                <div class="h-48 w-full bg-gray-200 flex items-center justify-center text-gray-400">No Image</div>
                            @endif
                        </div>
                        <div class="flex-1 bg-white p-6 flex flex-col justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-orange-600">
                                    {{ \Carbon\Carbon::parse($event->start_time)->format('d/m/Y') }}
                                </p>
                                <a href="{{ route('event.show', $event->slug) }}" class="block mt-2">
                                    <p class="text-xl font-semibold text-gray-900">{{ $event->title }}</p>
                                    <p class="mt-3 text-base text-gray-500">{{ Str::limit($event->short_description, 100) }}</p>
                                </a>
                            </div>
                            <div class="mt-6 flex items-center">
                                <a href="{{ route('event.show', $event->slug) }}" class="text-orange-600 hover:text-orange-500 font-medium">Xem chi tiết &rarr;</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-12">
                {{ $pastEvents->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Không có dữ liệu</h3>
                <p class="mt-1 text-sm text-gray-500">Chưa có sự kiện nào trong hệ thống.</p>
            </div>
        @endif
    </div>

    <footer class="bg-white mt-12">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-base text-gray-400">
                &copy; {{ date('Y') }} ColabSpace. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>
