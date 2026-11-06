@push('javascript')
    @foreach (['success' => 'success', 'error' => 'error', 'message' => 'error', 'status' => 'success'] as $key => $type)
        @if (session($key))
            <script>
                MyZkToast.{{ $type }}(@js(session($key)));
            </script>
        @endif
    @endforeach
@endpush
