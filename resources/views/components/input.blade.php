@props(['id', 'name', 'label', 'type' => 'text', 'help' => null, 'required' => false, 'value' => '', 'mask' => null, 'inputmode' => null, 'pattern' => null, 'autocomplete' => null])
<div class="form-group">
    <label for="{{ $id }}">{{ $label }}@if($required) <span class="required-label">(obrigatório)</span>@endif</label>
    @if($help)<span class="help-text" id="{{ $id }}-help">{{ $help }}</span>@endif
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : old($name, $value) }}"
        class="field @error($name) is-invalid @enderror" {{ $attributes }}
        @if($required) required @endif
        @if($help || $errors->has($name)) aria-describedby="{{ $help ? $id.'-help' : '' }} {{ $errors->has($name) ? $id.'-error' : '' }}" @endif
        @if($mask) data-mask="{{ $mask }}" @endif
        @if($inputmode) inputmode="{{ $inputmode }}" @endif
        @if($pattern) pattern="{{ $pattern }}" @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @error($name) aria-invalid="true" @enderror>
    @error($name)<span class="error-message" id="{{ $id }}-error">{{ $message }}</span>@enderror
</div>
