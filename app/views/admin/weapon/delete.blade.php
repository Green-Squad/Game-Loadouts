@extends('admin.layout')

@section('subtitle')
Delete Weapon {{ $weapon -> name }}
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>{{ $weapon -> name }} <small>Delete</small></h1>
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
            <li class="active">
                <i class="icon-file-alt"></i> Delete
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-7">
        <div class="well">
            {{ Form::open( array('url' => action('WeaponController@destroy', array('id' => $game -> id, 'weaponID' => $weapon -> id)), 'class' => 'form-horizontal')) }}
            <fieldset>
                <legend>
                    <p>
                        Are you sure you want to delete Weapon {{ $weapon -> name }}? This process is irreversible.
                    </p>
                </legend>
                <div class="form-group">
                    <div class="col-lg-6">
                        <a class="btn btn-default" href="{{ action('GameController@index') }}">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-danger">
                            Delete
                        </button>
                    </div>
                </div>
            </fieldset>
            {{ Form::close() }}
        </div>
    </div>
</div>

@stop