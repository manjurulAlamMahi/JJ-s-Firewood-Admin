@props([
    'submit' => 'Save changes',
    'cancel' => null,        // URL — omit to hide the cancel button
    'cancelLabel' => 'Cancel',
])

{{--
    Sticky on mobile so the save button is always reachable at the bottom of a
    long form; a plain inline row on desktop.
--}}
<div {{ $attributes->merge(['class' => 'av-savebar']) }}>
    @if ($cancel)
        <a href="{{ $cancel }}" class="btn btn-secondary av-savebar__cancel">{{ $cancelLabel }}</a>
    @endif

    {{ $slot }}

    <button type="submit" class="btn btn-primary">{{ $submit }}</button>
</div>
