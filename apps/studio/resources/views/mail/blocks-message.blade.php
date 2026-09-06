{{-- apps/studio/resources/views/mail/blocks-message.blade.php --}}
<x-mail::layout :brand="$brand ?? null">
    <x-slot:header></x-slot:header>

    @if (! empty($greeting))
        <p>{{ $greeting }}</p>
    @endif

    @foreach ($introLines as $line)
        <p>{{ $line }}</p>
    @endforeach

    @isset($actionText)
        <x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
    @endisset

    @foreach ($blocks ?? [] as $block)
        <hr>
        <p><strong>{{ $block['heading'] }}</strong></p>
        <p>{!! $block['body'] !!}</p>
        <x-mail::button :url="$block['buttonUrl']">{{ $block['buttonLabel'] }}</x-mail::button>
    @endforeach

    @foreach ($outroLines as $line)
        <p>{{ $line }}</p>
    @endforeach

    @if (! empty($salutation))
        <p>{{ $salutation }}</p>
    @endif

</x-mail::layout>
