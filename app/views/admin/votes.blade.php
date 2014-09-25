@extends('admin.layout')

@section('subtitle')
{{ $pageName }} Administration
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>{{ $pageName }} <small>Administration</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
            <li class="active">
                <i class="icon-file-alt"></i> {{ $pageName }} 
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-12">
        <table class="table tablesorter">
            <thead>
                <tr>
                    <th> Date <i class="fa fa-sort"></i></th>
                    @foreach ($games as $game)
                    <th> {{ $game }} <i class="fa fa-sort"></i></th>
                    @endforeach
                    <th> Total <i class="fa fa-sort"></i></th>
                </tr>
            </thead>
            <tbody>
                @foreach($submissionsPerDay as $day => $submission)
                <tr>
                    <td>
                        {{ $day }}
                    </td>
                    <?php $total = 0; ?>
                    @foreach ($games as $game)
                        <td>
                            @if(isset($submission[$game]))
                                {{ $submission[$game] }}
                                <?php $total += $submission[$game]; ?>
                            @else
                                0
                            @endif
                        </td>
                    @endforeach
                    <td>
                        {{ $total }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div><!-- /.row -->

@stop

@section('page-scripts')

<script src="{{ asset('js/tablesorter/jquery.tablesorter.js') }}"></script>
<script src="{{ asset('js/tablesorter/tables.js') }}"></script>


@stop
