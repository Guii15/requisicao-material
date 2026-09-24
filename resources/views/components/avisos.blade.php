@if (session('sucesso'))
    <div role="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
        {{ session('sucesso') }}
    </div>
@endif

@if (session('erro'))
    <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">
        {{ session('erro') }}
    </div>
@endif
