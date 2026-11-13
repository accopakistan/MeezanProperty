{{-- Official contact details. $tone: 'light' (on white) or 'dark' (on the footer). --}}
@props(['tone' => 'light'])
@php
    use App\Support\Contact;
    $dark = $tone === 'dark';
    // Each icon sits in a round badge, so the four lines line up and read as one set.
    $chip = 'grid shrink-0 place-items-center rounded-full '.($dark ? 'size-8 bg-white/10 text-gold-300 ring-1 ring-white/10' : 'size-10 bg-brand-50 text-brand-600');
    $icon = $dark ? 'size-4' : 'size-5';
    $link = $dark ? 'hover:text-white' : 'link';
@endphp
<ul {{ $attributes->merge(['class' => $dark ? 'space-y-2.5 text-sm' : 'space-y-4 text-[15px]']) }}>
    @if (Contact::address())
        <li class="flex items-start gap-3"><span class="{{ $chip }}"><x-icon name="map-pin" :class="$icon" /></span> <span class="pt-1">{{ Contact::address() }}</span></li>
    @endif
    @if (Contact::phone())
        <li class="flex items-center gap-3"><span class="{{ $chip }}"><x-icon name="phone" :class="$icon" /></span> <span>Office: <a href="{{ Contact::phoneHref() }}" class="{{ $link }}">{{ Contact::phoneDisplay() }}</a></span></li>
    @endif
    @if (Contact::whatsapp())
        <li class="flex items-center gap-3"><span class="{{ $chip }}"><x-icon name="whatsapp" :class="$icon" /></span> <span>WhatsApp: <a href="{{ Contact::whatsappUrl() }}" target="_blank" rel="noopener" class="{{ $link }}">{{ Contact::whatsappDisplay() }}</a></span></li>
    @endif
    @if (Contact::email())
        <li class="flex items-center gap-3"><span class="{{ $chip }}"><x-icon name="mail" :class="$icon" /></span> <a href="{{ Contact::emailHref() }}" class="{{ $link }}">{{ Contact::email() }}</a></li>
    @endif
</ul>
