@extends('layout')

@section('subtitle')
{{ $game -> id }}
@stop

@section('content')

<div class="well">
    <h2>{{ $game -> id}} <small>Weapons</small></h2>
    @foreach($weapons as $weapon)
    <p>
        <a href="{{ route('showLoadouts', array($game -> id, $weapon -> name)) }}">
            {{ $weapon -> name }}
        </a>
    </p>
    @endforeach
</div>

@stop