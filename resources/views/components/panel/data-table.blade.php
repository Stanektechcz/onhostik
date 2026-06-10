@props(['headers' => []])
<div class="table-responsive theme-scrollbar">
    <table class="table table-hover display dataTable">
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
