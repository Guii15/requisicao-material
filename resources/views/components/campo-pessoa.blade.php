@props(['nome', 'id', 'rotulo'])
{{-- Nome de quem retira ou devolve o material (não precisa ter login). --}}
<div>
    <label for="{{ $id }}" class="rotulo">{{ $rotulo }}</label>
    <input id="{{ $id }}" name="{{ $nome }}" type="text" required maxlength="255"
           value="{{ old($nome) }}"
           {{ $attributes->only('data-foco') }}
           class="campo mt-1.5 @error($nome) campo-erro @enderror">
    @error($nome)
        <p class="erro-campo">{{ $message }}</p>
    @enderror
</div>
