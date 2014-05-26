@extends('admin.layout')

@section('subtitle')
User Administration
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>User <small>Administration</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
            <li class="active">
                <i class="icon-file-alt"></i> Users
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-12">
        <table class="table tablesorter">
            <thead>
                <tr>
                    <th> Username <i class="fa fa-sort"></i></th>
                    <th> Email <i class="fa fa-sort"></i></th>
                    <th> Role <i class="fa fa-sort"></i></th>
                    <th> Confirmed <i class="fa fa-sort"></i></th>
                    <th> Created at <i class="fa fa-sort"></i></th>
                    <th> Updated at <i class="fa fa-sort"></i></th>
                    <th> Disabled until <i class="fa fa-sort"></i></th>
                    <th> Failed Attempts <i class="fa fa-sort"></i></th>
                    <th> Loadouts <i class="fa fa-sort"></i></th>
                    <th> Actions </th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>
                        <a href="{{ route('userSubmissions', $user -> email) }}">
                            {{ $user -> username }}
                        </a>
                    </td>
                    <td>
                        {{ $user -> email }}
                    </td>
                    <td>
                        {{ $user -> role }}
                    </td>
                    <td>
                        @if($user -> confirm_token == 1)
                            Yes
                        @else
                            No
                        @endif
                    </td>
                    <td>
                        {{ $user -> created_at }}
                    </td>
                    <td>
                        {{ $user -> updated_at }}
                    </td>
                    <td>
                        {{ $user -> disabled_until }}
                    </td>
                    <td>
                        {{ $user -> failed_attempts }}
                    </td>
                    <td>
                        {{ $user -> loadouts -> count() }}
                    </td>
                    <td>
                        <a href="{{ route('editUser', array('email' => $user -> email)) }}" class="btn btn-default"><span class="glyphicon glyphicon-pencil"></span></a>
                        &nbsp;<a href="{{ route('deleteUser', array('email' => $user -> email) )}}" class="btn btn-danger"><span class="glyphicon glyphicon-trash"></span></a>
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
