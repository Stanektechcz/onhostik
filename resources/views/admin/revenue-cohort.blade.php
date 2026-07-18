@extends('layouts.panel')

@section('title', 'Kohortová analýza tržeb')

@section('content')
<div class="container-fluid">
    <x-panel.card title="Kohortová analýza tržeb (akvizice × platební měsíc)">
        <p class="text-muted f-12">Každý řádek = kohorta zákazníků dle měsíce registrace. Sloupce = měsíce plateb.</p>

        @if(empty($cohorts))
            <p class="text-muted">Nedostatek dat.</p>
        @else
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0" style="font-size:11px">
                <thead>
                    <tr>
                        <th>Kohorta</th>
                        @foreach($allMonths as $month)
                        <th class="text-right">{{ $month }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($cohorts as $cohort)
                    <tr>
                        <td class="f-w-600">{{ $cohort }}</td>
                        @foreach($allMonths as $month)
                        <td class="text-right">
                            @if(isset($cohortData[$cohort][$month]))
                                @php $d = $cohortData[$cohort][$month]; @endphp
                                {{ number_format($d['revenue_minor'] / 100, 0, ',', ' ') }} Kč
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-panel.card>
</div>
@endsection
