@props(['status'])
{{-- Works with any enum exposing label() + color() --}}
<span class="badge badge-light-{{ $status->color() }}">{{ $status->label() }}</span>
