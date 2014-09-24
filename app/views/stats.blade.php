@extends('layout')

@section('subtitle')
Game Loadouts Stats
@stop

@section('description')
View the stats we have collected on all the game loadout submissions.
@stop

@section('content')
<div class="col-md-12">
    <h2>Website Stats <small class="pull-right">Stats are cached hourly.</small></h2>
    <span class="line"> <span class="sub-line"></span> </span>

</div>
<div class="col-md-4">
    <h2>Loadout Stats</h2>
    <table class="table">
        <tr>
            <th>Game</th>
            <th>Loadout Votes</th>
            <th>Unique Loadouts</th>
        </tr>
        @foreach($submissionsPerGame as $submissions)
        <tr>
            <td>{{ $submissions -> game }}</td>
            <td>{{ $submissions -> votes }}</td>
            <td>{{ $submissions -> loadouts }}</td>
        </tr>
        @endforeach
    </table>
</div>
<div class="col-md-8">
    <h2>Votes Per Day</h2>
    <h3>
        <?php $counter = 0; ?>
        @foreach ($gamesVotes as $game => $votes)
        <span style="color: {{ $colors[$counter++] }}">{{ $game }}</span> 
        @endforeach
    </h3>
    <canvas id="canvas" height="547" width="730"></canvas>
</div>
@stop

@section('scripts')
<script src="{{ asset('js/Chart.min.js') }}"></script>
<script>
    var lineChartData = {
    labels : {{ $daysJSON }},
    datasets : [
    <?php $counter = 0; ?>
        @foreach ($gamesVotes as $gameVotes)
        {
        fillColor : "rgba(220,220,220,0.0)",
        strokeColor : "{{ $colors[$counter] }}",
        pointColor : "{{ $colors[$counter++] }}",
        pointStrokeColor : "#fff",
        data : {{ json_encode($gameVotes) }}

        },
        @endforeach
        ]

        }
        var myLine = new Chart(document.getElementById("canvas").getContext("2d")).Line(lineChartData);
</script>
@stop