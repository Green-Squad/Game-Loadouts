@extends('admin.layout')

@section('subtitle')
Edit Game {{ $game -> id }}
@stop

@section('content')

<div class="well">
  {{ Form::open( array('url' => action('GameController@update', array('id' => $game -> id)), 'method' => 'PUT', 'class' => 'form-horizontal')) }}
  <fieldset>
    <legend>
      <h2>Edit Game</h2>
    </legend>
    <div class="form-group">
      <div class="col-lg-12">
        {{ Form::text('id', $game -> id, array('class' => 'form-control input-lg', 'placeholder' => 'Game Name', 'required' => '')) }}
      </div>
    </div>
        <div class="form-group">
      <div class="col-lg-12">
        {{ Form::text('live', $game -> live, array('class' => 'form-control input-lg', 'placeholder' => 'Live', 'required' => '')) }}
      </div>
    </div>
    <div class="form-group">
      <div class="col-lg-12">
        <button type="submit" class="btn btn-primary">
          Save
        </button>
      </div>
    </div>
  </fieldset>
  {{ Form::close() }}
</div>

@stop