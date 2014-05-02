@extends('layout')

@section('subtitle')
Delete User {{ $user -> email }}
@stop

@section('content')

{{ Form::open( array('url' => route('deleteUser', array('email' => $user -> email)))) }}
<legend>
  Are you sure you want to delete user {{ $user -> email }}? This process is irreversible.
</legend>
<div class="form-group">
  <a class="btn btn-default" href="{{ route('modUsers') }}"> Cancel </a>
  <button type="submit" class="btn btn-danger">
    Delete
  </button>
</div>
{{ Form::close() }}

@stop