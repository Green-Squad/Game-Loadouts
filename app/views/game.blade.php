@extends('layout')

@section('subtitle')
{{ $game -> id }}
@stop

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('css/games/' . $game -> id . '.css') }}" />
@stop

@section('sub-header')
<section id="game" >
    <!-- Sub Header -->
    <div class="sub-header" >
        <div class="container">
            <div class="row" >
                <ul class="sub-header-container" >
                    <li>
                        <h3 class="title">{{ $game -> id }}</h3>
                    </li>
                    <li>
                        <ul class="custom-breadcrumb" >
                            <li>
                                <h6>
                                <a href="{{ route('home') }}">
                                    Home
                                </a></h6>
                            </li>
                            <li>
                                <i class="separator entypo-play" ></i>
                            </li>
                            <li>
                                <h6>{{ $game -> id }}</h6>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
@stop

@section('content')
<div class="col-lg-12">
    <h2>Weapons</h2>
    <table class="table">
        @foreach($weapons as $weapon)
        <tr>
            <td class="leftTable">
                <a class="block" href="{{ route('showLoadouts', array($game -> id, $weapon -> name)) }}">
                    <img src="{{ asset($weapon -> thumb_url) }}" alt="{{ $weapon -> name }}" />
                </a>
            </td>
            <td class="rightTable">
                <a class="h3 block" href="{{ route('showLoadouts', array($game -> id, $weapon -> name)) }}">
                    {{ $weapon -> name }}
                </a>
            </td>
        </tr>
        @endforeach
    </table>
</div>
@stop