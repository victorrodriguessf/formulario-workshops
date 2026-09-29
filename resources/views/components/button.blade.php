@props(['type' => 'button', 'variant' => 'primary'])
<button type="{{ $type }}" {{ $attributes->class(['btn', 'btn-secondary' => $variant === 'secondary', 'btn-quiet' => $variant === 'quiet']) }}>{{ $slot }}</button>
