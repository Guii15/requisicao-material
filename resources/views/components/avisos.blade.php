@if (session('sucesso'))
    <div role="status" class="mb-4 border border-emerald-700 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-950">
        {{ session('sucesso') }}
    </div>
@endif

@if (session('erro'))
    <div role="alert" class="mb-4 border border-red-700 bg-red-50 px-4 py-2.5 text-sm text-red-950">
        {{ session('erro') }}
    </div>
@endif
