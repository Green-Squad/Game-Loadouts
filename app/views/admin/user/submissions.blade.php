@extends('admin.layout')

@section('subtitle')
{{ $user -> username }} Submissions
@stop

@section('content')
<div class="row">
    <div class="col-lg-12">
        <h1>{{ $user -> username }} <small>Submissions</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
            <li>
                <i class="icon-file-alt"></i> 
                <a href="{{ route('modUsers') }}">
                    Users
                </a>
            </li>
            <li class="active">
                <i class="icon-file-alt"></i> Submissions
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-7">
        <div class="well">
            <table class="table table-striped tablesorter">
                <thead>
                    <tr>
                        <th>Game <i class="fa fa-sort"></i></th>
                        <th>Weapon <i class="fa fa-sort"></i></th>
                        <th>Attachments <i class="fa fa-sort"></i></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loadouts as $loadout)
                    <?php
                    $weapon = Weapon::findOrFail($loadout -> weapon_id);
                    ?>
                    <tr id="loadout-{{ $loadout -> id }}">
                        <td>
                            {{ $weapon -> game_id }}
                        </td>
                        <td>
                            {{ $weapon -> name }}
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
                            <a class="btn btn-default" href="{{ route('showLoadout', array(urlencode($weapon -> game_id), urlencode($weapon -> name), $loadout -> id)) }}">
                                <span class="glyphicon glyphicon-search"></span>
                            </a>
                            &nbsp;
                            <a class="btn btn-danger" href="#">
                                <span class="glyphicon glyphicon-trash"></span>
                            </a>
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /.row -->
@stop

@section('page-scripts')
<script src="{{ asset('js/tablesorter/jquery.tablesorter.js') }}"></script>
<script src="{{ asset('js/tablesorter/tables.js') }}"></script>
@stop
