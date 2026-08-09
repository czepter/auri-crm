<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="portal">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- A client's own gallery and paperwork, never something to index. --}}
    <meta name="robots" content="noindex, nofollow">
    <title inertia>{{ config('app.name', 'Studio') }}</title>
    @vite('resources/js/portal/main.ts')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
