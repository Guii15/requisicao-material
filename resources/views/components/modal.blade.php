@props(['nome', 'titulo', 'abrir' => false])
{{-- Abre com: $dispatch('abrir-modal', 'nome'). O primeiro campo com data-foco recebe o foco. --}}
<div x-data="{ aberto: {{ $abrir ? 'true' : 'false' }}, focar() { this.$nextTick(() => this.$root.querySelector('[data-foco]')?.focus()) } }"
     x-init="if (aberto) focar()"
     x-on:abrir-modal.window="if ($event.detail === @js($nome)) { aberto = true; focar() }"
     x-on:keydown.escape.window="aberto = false">
    <div x-show="aberto" x-cloak
         class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-950/50 px-4 pt-[12vh] pb-8"
         role="dialog" aria-modal="true" aria-labelledby="modal-{{ $nome }}-titulo">
        <div class="w-full max-w-md border border-slate-500 bg-white shadow-lg shadow-slate-950/20" x-on:click.outside="aberto = false">
            <h2 id="modal-{{ $nome }}-titulo" class="quadro-titulo text-slate-800">{{ $titulo }}</h2>
            {{ $slot }}
        </div>
    </div>
</div>
