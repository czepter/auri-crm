{{-- apps/studio/resources/views/mail/product-message.blade.php --}}
<x-mail::layout :brand="$brand">
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

    @foreach ($outroLines as $line)
        <p>{{ $line }}</p>
    @endforeach

    @if (! empty($salutation))
        <p>{{ $salutation }}</p>
    @endif

    <x-slot:footer>
        <p>Auri CRM ist Open Source. Rückmeldungen landen auf GitHub: <a href="https://github.com/czepter/auri-crm/issues">github.com/czepter/auri-crm/issues</a></p>
    </x-slot:footer>
</x-mail::layout>
