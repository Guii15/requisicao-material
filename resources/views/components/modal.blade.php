@props(['nome', 'titulo', 'abrir' => false])
{{-- Abre com: $dispatch('abrir-modal', 'nome'). O primeiro campo com data-foco recebe o foco. --}}
<div x-data="{ aberto: {{ $abrir ? 'true' : 'false' }}, focar() { this.$nextTick(() => this.$root.querySelector('[data-foco]')?.focus()) } }"
     x-init="if (aberto) focar()"
     x-on:abrir-modal.window="if ($event.detail === @js($nome)) { aberto = true; focar() }"
     x-on:keydown.escape.window="aberto = false">
    <div x-show="aberto" x-cloak
         class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-ink/40 px-4 pt-[12vh] pb-8 backdrop-blur-[2px]"
         role="dialog" aria-modal="true" aria-labelledby="modal-{{ $nome }}-titulo">
        <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-[0_24px_48px_-16px_rgba(0,0,0,0.3)] ring-1 ring-hairline" x-on:click.outside="aberto = false">
            <h2 id="modal-{{ $nome }}-titulo" class="px-5 pt-5 text-lg font-semibold text-ink">{{ $titulo }}</h2>
            {{ $slot }}
        </div>
    </div>
</div>
