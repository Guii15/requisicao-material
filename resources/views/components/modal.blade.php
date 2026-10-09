@props(['nome', 'titulo', 'abrir' => false, 'largura' => 'max-w-md', 'subtitulo' => null])
{{-- Abre com: $dispatch('abrir-modal', 'nome'). O primeiro campo com data-foco recebe o foco. Com $subtitulo, o título vai num cabeçalho cinza com botão de fechar. --}}
<div x-data="{ aberto: {{ $abrir ? 'true' : 'false' }}, focar() { this.$nextTick(() => this.$root.querySelector('[data-foco]')?.focus()) } }"
     x-init="if (aberto) focar()"
     x-on:abrir-modal.window="if ($event.detail === @js($nome)) { aberto = true; focar() }"
     x-on:keydown.escape.window="aberto = false">
    <div x-show="aberto" x-cloak
         class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-ink/40 px-4 pt-[6vh] pb-8 backdrop-blur-[2px] sm:pt-[12vh]"
         role="dialog" aria-modal="true" aria-labelledby="modal-{{ $nome }}-titulo">
        <div class="w-full {{ $largura }} overflow-hidden rounded-lg bg-paper shadow-[0_24px_48px_-16px_rgba(0,0,0,0.25)] ring-1 ring-hairline" x-on:click.outside="aberto = false">
            @if ($subtitulo)
                <div class="flex items-start justify-between gap-4 border-b border-hairline bg-fundo px-6 py-5">
                    <div class="min-w-0">
                        <h2 id="modal-{{ $nome }}-titulo" class="text-lg font-semibold text-ink">{{ $titulo }}</h2>
                        <p class="mt-1 text-[13px] text-mid-gray">{{ $subtitulo }}</p>
                    </div>
                    <button type="button" x-on:click="aberto = false" aria-label="Fechar" class="-mr-2 rounded-md p-1.5 text-mid-gray hover:bg-surface-alt hover:text-ink focus-visible:outline-2 focus-visible:outline-marca">
                        <x-phosphor-x class="size-4" aria-hidden="true" />
                    </button>
                </div>
            @else
                <h2 id="modal-{{ $nome }}-titulo" class="px-5 pt-5 text-lg font-semibold text-ink">{{ $titulo }}</h2>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
