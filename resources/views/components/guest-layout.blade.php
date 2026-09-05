@props(['title' => null])
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' · CAMINO' : 'CAMINO' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..600,0..1,0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full">
    <div class="min-h-screen grid lg:grid-cols-[1.1fr_1fr]">
        {{-- Visuel --}}
        <aside class="relative hidden lg:flex flex-col justify-between p-10 text-white overflow-hidden">
            <img src="{{ asset('images/photo_paris.avif') }}" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-ink via-ink/50 to-ink/10"></div>
            <a href="{{ route('home') }}" class="relative flex items-center gap-2">
                <span class="h-9 w-9 rounded-xl bg-coral flex items-center justify-center"><span class="material-symbols-outlined filled" style="font-size:20px">location_on</span></span>
                <span class="font-display text-2xl font-semibold">CAMINO</span>
            </a>
            <div class="relative max-w-md">
                <p class="display text-4xl">La ville a plus à raconter que ses monuments.</p>
                <p class="mt-4 text-white/80">Une carte vivante, des parcours générés selon ton temps et la météo, et une communauté qui partage ses bons plans.</p>
            </div>
        </aside>

        {{-- Formulaire --}}
        <div class="flex flex-col">
            <div class="lg:hidden relative h-52 overflow-hidden text-white">
                <img src="{{ asset('images/photo_paris.avif') }}" alt="" class="absolute inset-0 h-full w-full object-cover" style="object-position: 60% 40%">
                <div class="absolute inset-0 bg-gradient-to-t from-paper via-ink/40 to-ink/30"></div>
                <div class="relative h-full flex flex-col justify-between p-5">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-2 w-fit">
                        <span class="h-8 w-8 rounded-xl bg-coral text-white flex items-center justify-center shadow-card"><span class="material-symbols-outlined filled" style="font-size:18px">location_on</span></span>
                        <span class="font-display text-xl font-semibold drop-shadow">CAMINO</span>
                    </a>
                    <p class="display text-2xl text-white drop-shadow pb-4">La ville a plus à raconter que ses monuments.</p>
                </div>
            </div>
            <div class="flex-1 flex items-start lg:items-center justify-center p-5 pt-2 sm:p-10">
                <div class="w-full max-w-md animate-fade-up">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
