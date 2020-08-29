@extends('admin.layout')

@section('subtitle')
Game Administration
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>{{ $game -> id }} <small>Administration</small></h1>
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
            <li class="active">
                <i class="icon-file-alt"></i> {{ $game -> id }}
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div>
<div class="row">
    <div class="col-lg-12">
        <h3> Import <a href="{{ action('ImportController@import', array('game_id' => $game -> id)) }}" class="btn btn-sm btn-success"><span class="glyphicon glyphicon-plus"></span></a></h3>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <h3> Weapons <a href="{{ action('WeaponController@create', array('game_id' => $game -> id)) }}" class="btn btn-sm btn-success"><span class="glyphicon glyphicon-plus"></span></a></h3>
        <table class="table tablesorter">
            <thead>
                <tr>
                    <th> ID <i class="fa fa-sort"></i></th>
                    <th> Name <i class="fa fa-sort"></i></th>
                    <th> Attachments <i class="fa fa-sort"></i></th>
                    <th> Loadouts <i class="fa fa-sort"></i></th>
                    <th> Image_URL <i class="fa fa-sort"></i></th>
                    <th> Min Attachments </th>
                    <th> Max Attachments </th>
                    <th> Type </th>
                    <th> Created at <i class="fa fa-sort"></i></th>
                    <th> Updated at <i class="fa fa-sort"></i></th>
                    <th> Actions </th>
                </tr>
            </thead>
            <tbody>
                @foreach($weapons as $weapon)
                <tr>
                    <td>
                        {{ $weapon -> id }}
                    </td>
                    <td>
                        <a href="{{ route('weaponLoadouts', array('id' => $game -> id, 'name' => $weapon -> name)) }}">
                            {{ $weapon -> name }}
                        </a>
                    </td>
                    <td>
                        {{ count($weapon -> attachments) }}
                    </td>
                    <td>
                        {{ count(Loadout::where('weapon_id', $weapon -> id) -> get()) }}
                    </td>
                    <td>
                        @if ($weapon -> thumb_url)
                        <a href="{{ asset($weapon -> image_url) }}">
                            <img src="{{ asset($weapon -> thumb_url) }}" alt="thumb URL" class="thumbnail" />
                        </a>
                        @else
                        No Image
                        @endif
                    </td>
                    <td>
                        {{ $weapon -> min_attachments }}
                    </td>
                    <td>
                        {{ $weapon -> max_attachments }}
                    </td>
                    <td>
                        {{ $weapon -> type }}
                    </td>
                    <td>
                        {{ $weapon -> created_at }}
                    </td>
                    <td>
                        {{ $weapon -> updated_at }}
                    </td>
                    <td>
                        <a href="{{ route('weaponEdit', array('id' => $game -> id, 'name' => $weapon -> name)) }}" class="btn btn-default">
                            <span class="glyphicon glyphicon-pencil"></span>
                        </a>
                        &nbsp;
                        <a href="{{ route('weaponDelete', array('id' => $game -> id, 'name' => $weapon -> name)) }}" class="btn btn-danger">
                            <span class="glyphicon glyphicon-trash"></span>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-12">
        <h3> Attachments <a href="{{ action('AttachmentController@create', array('game_id' => $game -> id)) }}" class="btn btn-sm btn-success"><span class="glyphicon glyphicon-plus"></span></a></h3>
        <table class="table tablesorter">
            <thead>
                <tr>
                    <th> ID <i class="fa fa-sort"></i></th>
                    <th> Name <i class="fa fa-sort"></i></th>
                    <th> Slot <i class="fa fa-sort"></i></th>
                    <th> Image_URL <i class="fa fa-sort"></i></th>
                    <th> Created at <i class="fa fa-sort"></i></th>
                    <th> Updated at <i class="fa fa-sort"></i></th>
                    <th> Actions </th>
                </tr>
            </thead>
            <tbody>
                @foreach($attachments as $attachment)
                <tr>
                    <td>
                        {{ $attachment -> id }}
                    </td>
                    <td>
                        {{ $attachment -> name }}
                    </td>
                    <td>
                        {{ $attachment -> slot }}
                    </td>
                    <td>
                        @if ($attachment -> image_url)
                        <a href="{{ asset($attachment -> image_url) }}">
                            <img src="{{ asset($attachment -> thumb_url) }}" alt="Image URL" class="thumbnail" />
                        </a>
                        @else
                        No Image
                        @endif
                    </td>
                    <td>
                        {{ $attachment -> created_at }}
                    </td>
                    <td>
                        {{ $attachment -> updated_at }}
                    </td>
                    <td>
                        <a href="{{ route('attachmentEdit', array('id' => $game -> id, 'name' => $attachment -> id)) }}" class="btn btn-default">
                            <span class="glyphicon glyphicon-pencil"></span>
                        </a>
                        &nbsp;
                        <a href="{{ route('attachmentDelete', array('id' => $game -> id, 'name' => $attachment -> id)) }}" class="btn btn-danger">
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
