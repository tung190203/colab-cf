<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sự kiện | ColabSpace</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#f1f6ef',
                            100: '#deeada',
                            400: '#5a8b42',
                            500: '#2D4F1E',
                            600: '#1f3815',
                            900: '#0a1207',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
        /* Subtle noise texture for premium feel */
        .noise-bg {
            position: relative;
        }
        .noise-bg::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 10;
        }

        .glass-nav { 
            background: rgba(255, 255, 255, 0.9); 
            backdrop-filter: blur(12px); 
            border-bottom: 1px solid rgba(0, 0, 0, 0.05); 
        }

        .card-editorial {
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .card-editorial:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -10px rgba(45, 79, 30, 0.15);
        }

        .image-reveal {
            clip-path: inset(0 0 0 0);
            transition: clip-path 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .card-editorial:hover .image-reveal {
            transform: scale(1.02);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-brand-500 selection:text-white noise-bg">
    
    <!-- Navbar -->
    <nav class="glass-nav fixed w-full top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <a href="{{ route('event.home') }}" class="flex items-center">
                        <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="ColabSpace" class="h-10 w-auto">
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-16">
        
        <!-- Magazine Style Hero -->
        <div class="max-w-7xl mx-auto px-6 lg:px-8 pt-20 pb-16">
            <div class="flex flex-col lg:flex-row gap-12 items-end justify-between border-b border-slate-300 pb-12">
                <div class="max-w-3xl">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="h-px w-8 bg-brand-500"></span>
                        <span class="font-mono text-sm font-semibold text-brand-600 uppercase tracking-widest">Lưu trữ sự kiện</span>
                    </div>
                    <h1 class="text-5xl md:text-7xl font-extrabold text-slate-900 tracking-tighter leading-none">
                        Khám phá các<br/>
                        hoạt động nổi bật.
                    </h1>
                </div>
                <div class="lg:w-1/3 text-slate-600 text-xl leading-relaxed font-medium">
                    Một không gian kết nối, chia sẻ và kiến tạo những giá trị mới. Xem lại các sự kiện đã định hình nên cộng đồng ColabSpace.
                </div>
            </div>
        </div>

        <!-- Event Grid -->
        <div class="max-w-7xl mx-auto px-6 lg:px-8 pb-24">
            @if($pastEvents->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-16">
                    
                    @foreach($pastEvents as $index => $event)
                        @if($index === 0)
                            <!-- Featured Event (Spans 2 columns on large screens) -->
                            <article class="card-editorial group relative bg-white border border-slate-200 rounded-sm overflow-hidden lg:col-span-2 flex flex-col md:flex-row">
                                <div class="w-full md:w-1/2 relative overflow-hidden bg-slate-100 aspect-video md:aspect-auto">
                                    @if($event->image_url)
                                        <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="image-reveal w-full h-full object-cover absolute inset-0">
                                    @else
                                        <div class="absolute inset-0 flex items-center justify-center text-slate-400">Không có hình ảnh</div>
                                    @endif
                                    <div class="absolute top-4 left-4 bg-brand-900 text-white px-4 py-1.5 text-sm font-mono font-medium rounded-sm">Mới nhất</div>
                                </div>
                                <div class="w-full md:w-1/2 p-8 lg:p-12 flex flex-col justify-center relative">
                                    <div class="font-mono text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4 border-b border-slate-200 pb-4 flex justify-between">
                                        <span>{{ \Carbon\Carbon::parse($event->start_time)->format('d.m.Y') }}</span>
                                        <span class="text-slate-600">Đã kết thúc</span>
                                    </div>
                                    <h2 class="text-4xl font-bold text-slate-900 mb-4 leading-tight group-hover:text-brand-600 transition-colors">
                                        <a href="{{ route('event.show', $event->slug) }}">
                                            <span class="absolute inset-0"></span>
                                            {{ $event->title }}
                                        </a>
                                    </h2>
                                    <p class="text-slate-700 mb-8 line-clamp-3 text-xl leading-relaxed">
                                        {{ $event->short_description ?: 'Theo dõi chi tiết sự kiện này tại trang thông tin chính thức.' }}
                                    </p>
                                    <div class="mt-auto inline-flex items-center text-base font-bold text-brand-900 group-hover:text-brand-600 uppercase tracking-widest transition-colors">
                                        Xem chi tiết 
                                        <svg class="ml-2 w-5 h-5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                                    </div>
                                </div>
                            </article>
                        @else
                            <!-- Standard Event Card -->
                            <article class="card-editorial group relative bg-white border border-slate-200 rounded-sm overflow-hidden flex flex-col">
                                <div class="relative h-60 w-full overflow-hidden bg-slate-100 border-b border-slate-200">
                                    @if($event->image_url)
                                        <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="image-reveal w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-slate-400">Không có hình</div>
                                    @endif
                                </div>
                                <div class="p-8 flex-1 flex flex-col relative">
                                    <div class="font-mono text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4 flex gap-4">
                                        <span>{{ \Carbon\Carbon::parse($event->start_time)->format('d.m.Y') }}</span>
                                    </div>
                                    <h3 class="text-2xl font-bold text-slate-900 mb-3 leading-snug group-hover:text-brand-600 transition-colors">
                                        <a href="{{ route('event.show', $event->slug) }}">
                                            <span class="absolute inset-0"></span>
                                            {{ $event->title }}
                                        </a>
                                    </h3>
                                    <p class="text-slate-600 text-base leading-relaxed mb-6 line-clamp-3">
                                        {{ $event->short_description ?: 'Theo dõi chi tiết sự kiện này tại trang thông tin chính thức.' }}
                                    </p>
                                    <div class="mt-auto pt-6 border-t border-slate-200 flex items-center justify-between">
                                        <span class="text-base font-semibold text-slate-900 group-hover:text-brand-600 transition-colors">Xem lại</span>
                                        <div class="w-10 h-10 rounded-full border border-slate-300 flex items-center justify-center group-hover:border-brand-600 group-hover:bg-brand-50 transition-colors">
                                            <svg class="w-5 h-5 text-slate-500 group-hover:text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endif
                    @endforeach

                </div>

                <!-- Pagination -->
                <div class="mt-20 flex justify-center border-t border-slate-200 pt-12">
                    {{ $pastEvents->links() }}
                </div>
            @else
                <!-- Empty State -->
                <div class="py-32 text-center">
                    <div class="inline-flex w-24 h-24 rounded-full border-2 border-dashed border-slate-300 items-center justify-center mb-6">
                        <span class="text-slate-300 font-mono text-4xl">0</span>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-3 tracking-tight">Chưa có sự kiện lưu trữ</h3>
                    <p class="text-slate-500 text-lg max-w-md mx-auto">ColabSpace hiện tại chưa có sự kiện nào đã diễn ra được công bố trên hệ thống.</p>
                </div>
            @endif
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center">
                <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="ColabSpace" class="h-8 w-auto brightness-0 invert opacity-80">
            </div>
            <div class="font-mono text-xs uppercase tracking-wider">
                &copy; {{ date('Y') }} All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>
