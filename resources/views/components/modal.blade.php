@props(['nome', 'titulo', 'abrir' => false])
{{-- Abre com: $dispatch('abrir-modal', 'nome'). O primeiro campo com data-foco recebe o foco. --}}
<div x-data="{ aberto: {{ $abrir ? 'true' : 'false' }}, focar() { this.$nextTick(() => this.$root.querySelector('[data-foco]')?.focus()) } }"
     x-init="if (aberto) focar()"
     x-on:abrir-modal.window="if ($event.detail === @js($nome)) { aberto = true; focar() }"
     x-on:keydown.escape.window="aberto = false">
    <div x-show="aberto" x-cloak
         class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-950/40 px-4 pt-[12vh] pb-8 backdrop-blur-[2px]"
         role="dialog" aria-modal="true" aria-labelledby="modal-{{ $nome }}-titulo">
        <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl shadow-slate-950/15 ring-1 ring-slate-200" x-on:click.outside="aberto = false">
            <h2 id="modal-{{ $nome }}-titulo" class="px-5 pt-5 text-lg font-semibold text-slate-900">{{ $titulo }}</h2>
            {{ $slot }}
        </div>
    </div>
</div>
