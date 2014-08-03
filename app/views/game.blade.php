@extends('layout')

@section('subtitle')
{{ $game -> id }}
@stop

@section('description')
{{ $game -> id }}@if($game -> short_name) ({{ $game -> short_name }})@endif weapon list. Find the best loadout for any {{ $game -> id }}@if($game -> short_name) ({{ $game -> short_name }})@endif gun.
@stop

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('css/games/' . urlencode($game -> id) . '.css') }}" />
<style>
    .hide {
        visibility: hidden;
    }

    .show {
        visibility: visible;
    }
</style>
@stop

@section('sub-header')
<section id="game" >
    <!-- Sub Header -->
    <div class="sub-header" >
        <div class="container">
            <div class="row" >
                <ul class="sub-header-container" >
                    <li>
                        <h1 class="title">{{ $game -> id }}</h1>
                    </li>
                    <li>
                        <ul class="custom-breadcrumb" >
                            <li>
                                <h5><a href="{{ route('home') }}"> Home </a></h5>
                            </li>
                            <li>
                                <i class="separator entypo-play" ></i>
                            </li>
                            <li>
                                <h5>{{ $game -> id }}</h5>
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
@if(HelperController::adsEnabled())
<div class="col-md-12">
    <script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
    <!-- Game Loadouts Leaderboard -->
    <ins class="adsbygoogle"
    style="display:block"
    data-ad-client="ca-pub-9067954073014278"
    data-ad-slot="1230654771"
    data-ad-format="auto"></ins>
    <script>
        ( adsbygoogle = window.adsbygoogle || []).push({});
    </script>
</div>
@endif
<div class="col-md-8">
    <h2>Weapons</h2>
    <span class="line"> <span class="sub-line"></span> </span>
    <form>
        <div class="form-group">
            <input type="text" class="form-control input-lg" id="weaponSearch" placeholder="Search Weapons">
        </div>
    </form>
    <table class="table table-striped table-hover" id="weapon-table">
        @foreach($weaponsByType as $key => $type)
        <tr>
            <td class="type" style="background-color: #fff; border: none;"><h3>{{ $key }}</h3><span class="line"> <span class="sub-line"></span> </span></td>
        </tr>
        @foreach($type as $weapon)
        <tr>
            <td class="searchable no-padding"><a class="block padding-8px flex" href="{{ route('showLoadouts', array(urlencode($game -> id), urlencode($weapon -> name))) }}">
            <div class="col-md-3 vertical-center">
                <img src="{{ asset($weapon -> thumb_url) }}" alt="{{ $weapon -> name }}" />
            </div>
            <div class="col-md-9 vertical-center">
                <span class="h3">{{ $weapon ->
                    name }}</span>
            </div> <div class="clearfix"></div> </a></td>

        </tr>
        @endforeach

        @endforeach
    </table>
</div>
<div class="col-md-4">
    @if(HelperController::adsEnabled())
    <h2>Advertisement</h2>
    <span class="line"> <span class="sub-line"></span> </span>
    <style>
        .game-loadouts-responsive-sidebar {
            width: 300px;
            height: 250px;
        }
    </style>
    <script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
    <!-- Game Loadouts Responsive Sidebar -->
    <ins class="adsbygoogle game-loadouts-responsive-sidebar"
    style="display:inline-block"
    data-ad-client="ca-pub-9067954073014278"
    data-ad-slot="2846988777"></ins>
    <script>
        ( adsbygoogle = window.adsbygoogle || []).push({});
    </script>
    @endif
    <h2>Top Loadouts</h2>
    <span class="line"> <span class="sub-line"></span> </span>
    @foreach($topLoadouts as $loadout)
    <?php
    $count = $loadout -> count;
    $loadout = Loadout::findOrFail($loadout -> id);
    ?>
    <a class="loadoutSmall block" href="{{ route('showLoadout', array(urlencode($game -> id), urlencode(Weapon::findOrFail($loadout -> weapon_id) -> name), urlencode($loadout ->id))) }}"> <h4 class="weaponSmall theme-color">{{ Weapon::findOrFail($loadout -> weapon_id) -> name }} <small class="pull-right"> ({{ $count }} votes)</small></h4> @foreach($loadout -> attachments as $attachment)
    <div class="attachmentSmall">
        <img src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" />
        {{ $attachment -> name }}
    </div> @endforeach </a>
    @endforeach
    <h2>Recent Loadouts</h2>
    <span class="line"> <span class="sub-line"></span> </span>
    @foreach($recentLoadouts as $loadout)
    <?php $loadout = Loadout::findOrFail($loadout -> id); ?>
    <a class="loadoutSmall block" href="{{ route('showLoadout', array(urlencode($game -> id), urlencode(Weapon::findOrFail($loadout -> weapon_id) -> name), urlencode($loadout ->id))) }}"> <h4 class="weaponSmall theme-color">{{ Weapon::findOrFail($loadout -> weapon_id) -> name }}</h4> @foreach($loadout -> attachments as $attachment)
    <div class="attachmentSmall">
        <img src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" />
        {{ $attachment -> name }}
    </div> @endforeach </a>
    @endforeach
</div>
@stop

@section('scripts')
<script type="text/javascript">
    $(document).ready(function() {
        $('#weaponSearch').keyup(function() {
            $query = $('#weaponSearch').val().toLowerCase();
            if ($query == '') {
                $('.type').removeClass('hide').addClass('show');
                $('#weapon-table').addClass('table-striped');
            } else {
                $('.type').removeClass('show').addClass('hide');
                $('#weapon-table').removeClass('table-striped');
            }
            $('.searchable').each(function() {
                if ($(this).text().toLowerCase().indexOf($query) != -1) {
                    $(this).removeClass('hide').addClass('show');
                } else {
                    $(this).removeClass('show').addClass('hide');
                }
            });
        });
    }); 
</script>
@stop
