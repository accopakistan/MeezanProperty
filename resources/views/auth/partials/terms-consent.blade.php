{{-- The registration consent. It names "terms" only once they are approved, so no one is asked to accept a draft. --}}
@php $final = \App\Support\Legal::approved('terms') && \App\Support\Legal::approved('privacy'); @endphp
<label class="flex items-start gap-2.5 {{ $class ?? 'text-sm text-muted' }}">
    <input type="checkbox" name="terms" value="1" class="checkbox mt-0.5" required @checked(old('terms'))>
    @if ($final)
        <span>I agree to the <a href="{{ route('pages.terms') }}" class="link">Terms of use</a> and <a href="{{ route('pages.privacy') }}" class="link">Privacy policy</a>.</span>
    @else
        <span>I confirm my details are accurate, and I have read how Meezan Property <a href="{{ route('pages.terms') }}" class="link">works</a> and <a href="{{ route('pages.privacy') }}" class="link">handles my data</a>.</span>
    @endif
</label>
