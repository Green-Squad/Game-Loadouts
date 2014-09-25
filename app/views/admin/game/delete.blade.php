@extends('admin.layout')

@section('subtitle')
Delete Game {{ $game -> id }}
@stop

@section('content')

<div class="well">
    {{ Form::open( array('url' => action('GameController@destroy', array('id' => $game -> id)), 'method' => 'DELETE', 'class' => 'form-horizontal')) }}
    <fieldset>
        <legend>
            <h2>Delete Game</h2>
            <p>
                Are you sure you want to delete game {{ $game -> id }}? This process is irreversible.
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

@stop