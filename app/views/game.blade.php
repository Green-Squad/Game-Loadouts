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
<div class="col-md-8">
    <h2>Weapons</h2>
    <span class="line"> <span class="sub-line"></span> </span>
    <table class="table table-striped table-hover">
        @foreach($weapons as $weapon)
        <tr>
            <td class="no-padding">
                <a class="block padding-8px flex" href="{{ route('showLoadouts', array($game -> id, $weapon -> name)) }}">
                    <div class="col-md-3 vertical-center">
                        <img src="{{ asset($weapon -> thumb_url) }}" alt="{{ $weapon -> name }}" />
                    </div>
                    <div class="col-md-9 vertical-center">
                        <span class="h3">{{ $weapon -> name }}</span>
                    </div>
                    <div class="clearfix"></div>
                </a>

            </td>

        </tr>
        @endforeach
    </table>
</div>
<div class="col-md-4">
    <h2>Recent Loadouts</h2>
    <span class="line"> <span class="sub-line"></span> </span>
    @foreach($recentLoadouts as $loadout)
    <?php $loadout = Loadout::findOrFail($loadout -> id); ?>
    <a class="loadoutSmall block" href="{{ route('showLoadout', array($game -> id, Weapon::findOrFail($loadout -> weapon_id) -> name, $loadout ->id)) }}">
        <h4 class="weaponSmall">{{ Weapon::findOrFail($loadout -> weapon_id) -> name }}</h4>
        @foreach($loadout -> attachments as $attachment)
        <div class="attachmentSmall">
            <img src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" />
            {{ $attachment -> name }}
        </div>
        @endforeach
    </a>
    @endforeach
</div>
@stop