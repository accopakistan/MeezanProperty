{{-- Site-wide direct WhatsApp link to the Meezan Property team. A plain wa.me chat, not the Cloud API assistant. --}}
@php $url = \App\Support\Contact::whatsappUrl(); @endphp
@if ($url)
    <a href="{{ $url }}" target="_blank" rel="noopener"
       class="fixed right-4 bottom-4 z-30 flex items-center gap-2 rounded-full bg-[#16a34a] px-4 py-3 text-sm font-semibold text-white shadow-lift transition hover:bg-[#15803d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#16a34a] sm:right-6 sm:bottom-6 print:hidden"
       aria-label="Chat with Meezan Property on WhatsApp, {{ \App\Support\Contact::whatsappDisplay() }}" data-whatsapp-float>
        <x-icon name="whatsapp" class="size-6" />
        <span class="hidden sm:inline">WhatsApp us</span>
    </a>
@endif
