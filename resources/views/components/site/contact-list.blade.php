{{-- Official contact details. $tone: 'light' (on white) or 'dark' (on the footer). --}}
@props(['tone' => 'light'])
@php
    use App\Support\Contact;
    $dark = $tone === 'dark';
    $icon = $dark ? 'size-4 shrink-0 text-gold-500' : 'size-5 shrink-0 text-brand-600';
    $link = $dark ? 'hover:text-white' : 'link';
@endphp
<ul {{ $attributes->merge(['class' => $dark ? 'space-y-2.5 text-sm' : 'space-y-4 text-[15px]']) }}>
    @if (Contact::address())
        <li class="flex items-start gap-2.5"><x-icon name="map-pin" :class="$icon.' mt-0.5'" /> <span>{{ Contact::address() }}</span></li>
    @endif
    @if (Contact::phone())
        <li class="flex items-center gap-2.5"><x-icon name="phone" :class="$icon" /> <span>Office: <a href="{{ Contact::phoneHref() }}" class="{{ $link }}">{{ Contact::phoneDisplay() }}</a></span></li>
    @endif
    @if (Contact::whatsapp())
        <li class="flex items-center gap-2.5"><x-icon name="whatsapp" :class="$icon" /> <span>WhatsApp: <a href="{{ Contact::whatsappUrl() }}" target="_blank" rel="noopener" class="{{ $link }}">{{ Contact::whatsappDisplay() }}</a></span></li>
    @endif
    @if (Contact::email())
        <li class="flex items-center gap-2.5"><x-icon name="mail" :class="$icon" /> <a href="{{ Contact::emailHref() }}" class="{{ $link }}">{{ Contact::email() }}</a></li>
    @endif
</ul>
