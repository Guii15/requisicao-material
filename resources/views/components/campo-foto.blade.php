@props(['id', 'rotulo', 'ajuda' => 'No celular, abre a câmera. PNG, JPG ou WEBP, até 10 MB.'])
{{-- Foto do produto na transferência de filial (saída e chegada). Campo "foto", obrigatório. --}}
<div x-data="{ nome: '' }">
    <label for="{{ $id }}" class="rotulo">{{ $rotulo }}</label>
    <p class="ajuda">{{ $ajuda }}</p>
    <input id="{{ $id }}" name="foto" type="file" required accept="image/png,image/jpeg,image/webp"
           x-on:change="nome = $event.target.files[0]?.name ?? ''"
           class="campo mt-1.5 @error('foto') campo-erro @enderror">
    <p x-show="nome" x-cloak class="mt-1.5 text-[13px] text-ink-soft">Anexada: <span class="font-medium" x-text="nome"></span></p>
    @error('foto')
        <p class="erro-campo">{{ $message }}</p>
    @enderror
</div>
