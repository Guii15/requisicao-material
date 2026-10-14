@props(['id', 'rotulo', 'obrigatorio' => true])
{{-- Anexo de print: escolhe o arquivo ou cola com Ctrl+V enquanto o modal está aberto. --}}
<div x-data="{
         nome: '',
         colar(e) {
             const imagem = [...(e.clipboardData?.files ?? [])].find((f) => f.type.startsWith('image/'));
             if (!imagem) return;
             const lista = new DataTransfer();
             lista.items.add(imagem);
             this.$refs.arquivo.files = lista.files;
             this.nome = imagem.name || 'Imagem colada';
         },
     }"
     x-on:paste.window="aberto && colar($event)">
    <label for="{{ $id }}" class="rotulo">{{ $rotulo }}</label>
    <p class="ajuda">Cole o print aqui (Ctrl+V) ou escolha o arquivo. PNG, JPG ou WEBP, até 5 MB.</p>
    <input id="{{ $id }}" name="comprovante" type="file" x-ref="arquivo" @required($obrigatorio)
           accept="image/png,image/jpeg,image/webp"
           x-on:change="nome = $event.target.files[0]?.name ?? ''"
           class="campo mt-1.5 @error('comprovante') campo-erro @enderror">
    <p x-show="nome" x-cloak class="mt-1.5 text-[13px] text-ink-soft">Anexado: <span class="font-medium" x-text="nome"></span></p>
    @error('comprovante')
        <p class="erro-campo">{{ $message }}</p>
    @enderror
</div>
