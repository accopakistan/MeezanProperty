@props(['property', 'saved' => false, 'style' => 'icon'])
{{-- icon: white heart over a photo · plain: purple heart on white · button: labelled button --}}
<form method="POST" action="{{ route('properties.save', $property) }}" data-save-form data-login-url="{{ route('login') }}" {{ $attributes }}>
    @csrf
    @if ($style === 'button')
        <button type="submit" aria-pressed="{{ $saved ? 'true' : 'false' }}" aria-label="{{ $saved ? 'Remove from saved' : 'Save property' }}"
                class="group/save btn btn-secondary aria-pressed:border-red-200 aria-pressed:text-red-600">
            <x-icon name="heart" class="size-4 group-aria-pressed/save:fill-current" />
            <span class="group-aria-pressed/save:hidden">Save</span><span class="hidden group-aria-pressed/save:inline">Saved</span>
        </button>
    @else
        <button type="submit" aria-pressed="{{ $saved ? 'true' : 'false' }}" aria-label="{{ $saved ? 'Remove from saved' : 'Save property' }}"
                @class(['group/save grid size-8 place-items-center transition aria-pressed:text-red-500',
                        'text-white drop-shadow-[0_1px_2px_rgb(0_0_0/0.5)] hover:scale-110' => $style === 'icon',
                        'text-brand-400 hover:text-red-500' => $style === 'plain'])>
            <x-icon name="heart" class="size-[22px] group-aria-pressed/save:fill-current" />
        </button>
    @endif
</form>
