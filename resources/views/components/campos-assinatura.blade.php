@props(['nome', 'id', 'rotuloNome', 'rotuloAssinatura'])
{{-- Nome de quem retira ou devolve + assinatura desenhada na tela. Vai dentro de um form com x-data="assinaturaDesenho()". --}}
<div>
    <label for="{{ $id }}" class="rotulo">{{ $rotuloNome }}</label>
    <input id="{{ $id }}" name="{{ $nome }}" type="text" required
           value="{{ old($nome) }}"
           {{ $attributes->only('data-foco') }}
           class="campo mt-1.5 @error($nome) campo-erro @enderror">
    @error($nome)
        <p class="erro-campo">{{ $message }}</p>
    @enderror
</div>
<div>
    <label class="rotulo">{{ $rotuloAssinatura }}</label>
    <p class="ajuda">Peça pra pessoa desenhar aqui na tela, com o dedo ou o mouse.</p>
    <div class="mt-1.5 overflow-hidden rounded-lg border border-hairline bg-fundo">
        <canvas x-ref="tela" class="h-40 w-full touch-none"
                x-on:mousedown="comecar" x-on:mousemove="desenhar" x-on:mouseup="parar" x-on:mouseleave="parar"
                x-on:touchstart="comecar" x-on:touchmove="desenhar" x-on:touchend="parar"></canvas>
    </div>
    <button type="button" x-show="!vazio" x-cloak x-on:click="limpar" class="mt-1.5 text-sm font-medium text-mid-gray hover:text-ink">Limpar e assinar de novo</button>
    @error('assinatura')
        <p class="erro-campo">{{ $message }}</p>
    @enderror
</div>
