@extends('admin.layout')

@section('subtitle')
Weapon Administration
@stop

@section('content')
<div class="row">
    <div class="col-lg-12">
        <h1>{{ $weapon -> name }} <small>Administration</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i>
                <a href="{{ route('adminDashboard') }}">
                    Dashboard
                </a>
            </li>
            <li>
                <i class="icon-file-alt"></i>
                <a href="{{ route('admin.game.index') }}">
                    Games
                </a>
            </li>
            <li>
                <i class="icon-file-alt"></i>
                <a href="{{ action('GameController@show', array('id' => $game -> id)) }}">
                    {{ $game -> id }}
                </a>
            </li>
            <li>
                <i class="icon-file-alt"></i> {{ $weapon -> name }}
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-12">
        <table class="table tablesorter">
            <thead>
                <tr>
                    <th> ID <i class="fa fa-sort"></i></th>
                    <th> Attachments <i class="fa fa-sort"></i></th>
                    <th> Created at <i class="fa fa-sort"></i></th>
                    <th> Updated at <i class="fa fa-sort"></i></th>
                    <th> Actions </th>
                </tr>
            </thead>
            <tbody>
                @foreach($loadouts as $loadout)
                <tr>
                    <td>
                        {{ $loadout -> id }}
                    </td>
                    <td>
                        <ul>
                            @foreach(Loadout::findOrFail($loadout -> id) -> attachments as $attachment)
                            <li>
                                {{ $attachment -> name }}
                            </li>
                            @endforeach
                        </ul>
                    </td>
                    <td>
                        {{ $loadout -> created_at }}
                    </td>
                    <td>
                        {{ $loadout -> updated_at }}
                    </td>
                    <td>
                        <a class="btn btn-danger" href="{{ route('deleteLoadout', array('id' => $game -> id, 'name' => $weapon -> name, 'loadoutID' => $loadout -> id)) }}">
                            <span class="glyphicon glyphicon-trash"></span>
                        </a>
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