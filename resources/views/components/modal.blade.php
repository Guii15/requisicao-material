@props(['nome', 'titulo', 'abrir' => false])
{{-- Abre com: $dispatch('abrir-modal', 'nome'). O primeiro campo com data-foco recebe o foco. --}}
<div x-data="{ aberto: {{ $abrir ? 'true' : 'false' }}, focar() { this.$nextTick(() => this.$root.querySelector('[data-foco]')?.focus()) } }"
     x-init="if (aberto) focar()"
     x-on:abrir-modal.window="if ($event.detail === @js($nome)) { aberto = true; focar() }"
     x-on:keydown.escape.window="aberto = false">
    <div x-show="aberto" x-cloak
         class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-950/50 px-4 pt-[12vh] pb-8"
         role="dialog" aria-modal="true" aria-labelledby="modal-{{ $nome }}-titulo">
        <div class="w-full max-w-md rounded-sm border border-slate-300 bg-white shadow-xl shadow-slate-950/10" x-on:click.outside="aberto = false">
            <div class="border-b border-slate-200 px-5 py-3.5">
                <h2 id="modal-{{ $nome }}-titulo" class="font-semibold text-slate-900">{{ $titulo }}</h2>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
