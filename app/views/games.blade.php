@extends('layout')

@section('subtitle')
Games
@stop

@section('content')
<div class="col-md-12">
    <h2>Games</h2>
    <span class="line"> <span class="sub-line"></span> </span>
    @foreach($games as $game)
    <div class="col-md-6">
        <article class="post">
            <a href="{{ route('showGame', $game -> id) }}">
                <img src="{{ asset($game -> thumb_url) }}" alt="{{ $game -> id }}" />
                <header>
                    <h3>{{ $game -> id }}</h3>
                </header>
            </a>
        </article>
    </div>
    @endforeach
</div>
@stop