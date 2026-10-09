@if (session('sucesso'))
    <div role="status" class="mb-5 flex items-center gap-2.5 rounded-md border border-sucesso/25 bg-sucesso-suave px-4 py-3 text-[13px] font-medium text-sucesso">
        <x-phosphor-check-circle class="size-[18px] shrink-0" aria-hidden="true" />
        {{ session('sucesso') }}
    </div>
@endif

@if (session('erro'))
    <div role="alert" class="mb-5 flex items-center gap-2.5 rounded-md border border-destrutivo/25 bg-destrutivo-suave px-4 py-3 text-[13px] font-medium text-destrutivo">
        <x-phosphor-warning-circle class="size-[18px] shrink-0" aria-hidden="true" />
        {{ session('erro') }}
    </div>
@endif
