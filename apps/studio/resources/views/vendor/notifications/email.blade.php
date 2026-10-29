{{--
    Laravel's own template minus two lines: the "Hello!" it prints when a
    notification sets no greeting, and "Regards, <app name>". The compose
    screens' messages carry their own greeting, and the studio's signature
    lives in the layout footer (StudioMailFooter).
--}}
<x-mail::message>
@if (! empty($greeting))
# {{ $greeting }}
@endif

@foreach ($introLines as $line)
{{ $line }}

@endforeach

@isset($actionText)
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>
@endisset
{{-- Two newlines: Blade eats the one after @endisset, and without a blank line the outro stays inside the button's HTML block, unparsed. --}}


@foreach ($outroLines as $line)
{{ $line }}

@endforeach

@if (! empty($salutation))
{{ $salutation }}
@endif

@isset($actionText)
<x-slot:subcopy>
@lang(
    \App\Models\StudioSettings::current()->clientAddress() === 'sie'
        ? "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".'into your web browser:'
        : 'Wenn der Button „:actionText“ nicht funktioniert, öffne diesen Link im Browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
