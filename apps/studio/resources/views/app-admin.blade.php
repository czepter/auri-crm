<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    {{-- T-12: read by DocumentTemplates' preview fetch() — the one non-Inertia
         POST this surface makes, so it needs the token by hand rather than
         through Inertia's own CSRF handling. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ app(App\Settings\Support\StudioSettingsResolver::class)->displayName() ?? config('app.name', 'Studio') }}</title>
    @vite('resources/js/admin/main.ts')
    @inertiaHead
</head>
<body class="min-h-screen bg-background font-sans antialiased">
    @inertia
</body>
</html>
