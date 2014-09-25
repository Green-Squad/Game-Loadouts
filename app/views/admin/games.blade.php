@extends('admin.layout')

@section('subtitle')
Game Administration
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>Game <small>Administration</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
            <li class="active">
                <i class="icon-file-alt"></i> Games
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
                    <th> Live </th>
                    <th> Thumb URL </th>
                    <th> Short Name <i class="fa fa-sort"></i></th>
                    <th> Created at <i class="fa fa-sort"></i></th>
                    <th> Updated at <i class="fa fa-sort"></i></th>
                    <th> Actions </th>
                </tr>
            </thead>
            <tbody>
                @foreach($games as $game)
                <tr>
                    <td>
                        <a href="{{ action('GameController@show', array('id' => $game -> id)) }}">
                            {{ $game -> id }}
                        </a>
                    </td>
                    <td>
                        {{ $game -> live }}
                    </td>
                    <td style="width: 10%">
                        <a href="{{ asset($game -> thumb_url) }}"><img style="width: 100%" src="{{ asset($game -> thumb_url) }}" alt="" /></a>
                    </td>
                    <td>
                        {{ $game -> short_name }}
                    </td>
                    <td>
                        {{ $game -> created_at }}
                    </td>
                    <td>
                        {{ $game -> updated_at }}
                    </td>
                    <td>
                        <a href="{{ action('GameController@edit', array('id' => $game -> id)) }}" class="btn btn-default">
                            <span class="glyphicon glyphicon-pencil"></span>
                        </a>
                        &nbsp;
                        <a href="{{ route('gameDelete', array('id' => $game -> id) )}}" class="btn btn-danger">
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
