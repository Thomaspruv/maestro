@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-900/50 bg-danger-muted p-3" role="alert">
        <p class="text-xs font-semibold text-danger">Impossible de continuer — corrigez les champs suivants :</p>
        <ul class="mt-2 space-y-1">
            @foreach ($errors->keys() as $field)
                <li class="text-[11px] text-danger">• {{ $errors->first($field) }}</li>
            @endforeach
        </ul>
    </div>
@endif
