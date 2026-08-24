@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <strong>Review the highlighted fields.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
