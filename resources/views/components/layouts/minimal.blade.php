{{--
    Minimal server-rendered layout — no nav, no footer, no brand assets.
    Used only for utility pages (e.g. prescribe-rx embed handoff) that must
    be served by Laravel rather than the decoupled frontend.
--}}
@props(['title' => 'Loading…'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="min-h-screen bg-gray-50 antialiased">
    {{ $slot }}
    @livewireScripts
    @stack('scripts')
</body>
</html>
