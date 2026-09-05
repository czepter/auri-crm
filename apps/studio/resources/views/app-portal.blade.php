<!DOCTYPE html>
{{-- #234: the studio's display pair, set on the root so both the portal and
     the gallery inherit it — they are the two surfaces a client sees, and the
     admin deliberately writes in the system stack (C-01). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="portal"
      data-font="{{ app(App\Settings\Support\StudioSettingsResolver::class)->fontPair()->value }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- P-08's lightbox posts a heart and a comment without leaving the page,
         so it needs the token Inertia's own visits get for free. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- A client's own gallery and paperwork, never something to index. --}}
    <meta name="robots" content="noindex, nofollow">
    <title inertia>{{ app(App\Settings\Support\StudioSettingsResolver::class)->displayName() ?? config('app.name', 'Studio') }}</title>
    @vite('resources/js/portal/main.ts')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
