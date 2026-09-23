@if (session('sucesso'))
    <div role="status" class="mb-4 flex items-start gap-2 rounded-sm border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
        <x-phosphor-check-circle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>{{ session('sucesso') }}</span>
    </div>
@endif

@if (session('erro'))
    <div role="alert" class="mb-4 flex items-start gap-2 rounded-sm border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
        <x-phosphor-warning-circle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>{{ session('erro') }}</span>
    </div>
@endif
