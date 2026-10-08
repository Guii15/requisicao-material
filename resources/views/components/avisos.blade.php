@if (session('sucesso'))
    <div role="status" class="mb-5 rounded-2xl border border-hairline bg-paper px-4 py-3 text-sm font-medium text-ink">
        {{ session('sucesso') }}
    </div>
@endif

@if (session('erro'))
    <div role="alert" class="mb-5 rounded-2xl border border-destrutivo/35 bg-paper px-4 py-3 text-sm font-medium text-destrutivo">
        {{ session('erro') }}
    </div>
@endif
