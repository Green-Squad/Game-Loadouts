@extends('layout')

@section('subtitle')
{{ $game -> id . " - " . $weapon -> name }}
@stop

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('css/games/' . $game -> id . '.css') }}" />
@stop

@section('sub-header')
<section id="game">
    <!-- Sub Header -->
    <div class="sub-header">
        <div class="container">
            <div class="row">
                <ul class="sub-header-container">
                    <li>
                        <h3 class="title"><span class="game-title">{{ $game -> id }} \</span> {{ $weapon -> name }}</h3>
                    </li>
                    <li>
                        <ul class="custom-breadcrumb">
                            <li>
                                <h6>
                                <a href="{{ route('home') }}">
                                    Home
                                </a></h6>
                            </li>
                            <li>
                                <i class="separator entypo-play"></i>
                            </li>
                            <li>
                                <h6>
                                <a href="{{ route('showGame', urlencode($game -> id)) }}">
                                    {{ $game -> id }}
                                </a></h6>
                            </li>
                            <li>
                                <i class="separator entypo-play"></i>
                            </li>
                            <li>
                                <h6>{{ $weapon -> name }}</h6>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
@stop

@section('intro')
<div id="home">
    <div id="intro">
        <div class="container">
            <div class="row flex">
                <div class="col-md-6 vertical-center">
                    <img src="{{ asset($weapon -> image_url) }}" alt="{{ $weapon -> name }}" class="weapon-img"/>
                </div>
                <div class="col-md-6 vertical-center">
                    <div class="heading-box"> What is the best loadout for <em class="theme-color">{{ $weapon -> name }}</em> in {{ $game -> id }}? </div>
                </div>
            </div>
        </div>
    </div>
</div>
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
<div class="row">
    <div class="col-md-6">
        <?php $count = 1; ?>

        <h2>Top voted loadouts</h2>
        <span class="line"> <span class="sub-line"></span> </span>
        @foreach($loadouts as $loadout)
        <div class="loadout">
            <a class="block" href="{{ route('showLoadout', array(urlencode($game -> id), urlencode($weapon -> name), $loadout['id'])) }}">
                <div class="col-md-2 rank theme-color">
                    {{ $count++ }}
                </div>
                <div class="col-md-10">
                    @foreach(Loadout::findOrFail($loadout['id']) -> attachments as $attachment)
                    <div class="attachment">
                        <img src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" />
                        {{ $attachment -> name }}
                    </div>
                    @endforeach
                </div>
                <div class="clearfix"></div>
            </a>
            <div class="col-md-12 loadoutButtons">
                @if (Auth::guest())
                <a class="btn btn-default" href="{{ route('login') }}">
                    <span class="glyphicon glyphicon-star-empty"></span> vote (<span id="count-{{ $loadout['id'] }}">{{ $loadout['count'] }}</span>)
                </a>
                @elseif ($loadout['upvoted'])
                <a class="btn btn-primary clickable" href="javascript:void(0)" id="upvote-{{ $loadout['id'] }}" data-loadout_id="{{ $loadout['id'] }}">
                    <span class="glyphicon glyphicon-star"></span> vote (<span id="count-{{ $loadout['id'] }}">{{ $loadout['count'] }}</span>)
                </a>
                @else
                <a class="btn btn-default clickable" href="javascript:void(0)" id="upvote-{{ $loadout['id'] }}" data-loadout_id="{{ $loadout['id'] }}">
                    <span class="glyphicon glyphicon-star-empty"></span> vote (<span id="count-{{ $loadout['id'] }}">{{ $loadout['count'] }}</span>)
                </a>
                @endif
                <a class="btn btn-default comment" href="{{ route('showLoadout', array(urlencode($game -> id), urlencode($weapon -> name), $loadout['id'])) }}" data-disqus-identifier="loadout-{{ $loadout['id'] }}">
                    @if (!isset($loadout['comments']))
                    <span class="glyphicon glyphicon-comment"></span>  0 Comments
                    @elseif ($loadout['comments'] == 1)
                    <span class="glyphicon glyphicon-comment"></span>  1 Comment
                    @else
                    <span class="glyphicon glyphicon-comment"></span>  {{ $loadout['comments'] }} Comments
                    @endif
                </a>
                <a class="btn btn-default" href="{{ route('showLoadout', array(urlencode($game -> id), urlencode($weapon -> name), $loadout['id'])) }}" data-disqus-identifier="loadout-{{ $loadout['id'] }}">
                    <span class="glyphicon glyphicon-screenshot"></span> View Loadout
                </a>
            </div>
        </div>
        @endforeach
    </div>
    <div class="col-md-6">
        <h2>Submit your own loadout!</h2>
        <span class="line"> <span class="sub-line"></span> </span>
                <h3>
                    Select one attachment for each slot.
                </h3>
                {{ Form::open( array('action' => array('LoadoutController@store', $game -> id, $weapon -> name), 'class' => 'form-horizontal', 'id' => 'storeForm')) }}
                <div class="form-group">
                    <ul class="nav nav-tabs nav-tabs-sec" id="myTab">
                    <?php $counter = 0; ?>
                        @foreach($attachmentsBySlot as $key => $slot)
                            <li class="
                            @if ($counter++ == 0)   
                            active
                            @endif
                            "><a href="#{{ $key }}" data-toggle="tab">{{ $key }}</a></li>
                        @endforeach
                    </ul>
                    <div class="tab-content nav-tabs-sec">
                    <?php $counter = 0; ?>
                    @foreach($attachmentsBySlot as $key => $slot)
                    
                        <div class="tab-pane 
                        @if ($counter++ == 0)   
                        active
                        @endif
                        " id="{{ $key }}">
                            <div class="btn-group-vertical" data-toggle="buttons">
                                @foreach($slot as $attachment)
                                <label class="btn btn-default">
                                    <input type="radio" value="{{ $attachment -> id }}" name="{{ $key }}" required="">
                                    {{ $attachment -> name }} </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    </div> 
                </div>
                <div class="form-group">
                    <div class="col-md-12">
                        <button type="submit" class="btn button-gym">
                            Submit Loadout
                        </button>
                    </div>
                </div>
                {{ Form::close() }}
            @if(HelperController::adsEnabled())
    		<h2>Advertisement</h2>
			<span class="line"> <span class="sub-line"></span> </span>
			<style>
				.game-loadouts-responsive-sidebar {
					width: 300px;
					height: 250px;
				}
				@media (min-width: 800px) {
					.game-loadouts-responsive-sidebar {
					width: 336px;
					height: 280px;
					}
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
    	</div>
	</div>
@stop

@section('scripts')
<script type="text/javascript">

    $('.comment').each(function() {
        var _href = $(this).attr('href');
        $(this).attr('href', _href + '#disqus_thread');
    });

    $('.clickable').click(function() {
        var game_id = '<?php echo $game -> id; ?>';
        var weapon_name = '<?php echo $weapon -> name; ?>';
        var loadout_id = $(this).data('loadout_id');
        
        if ($(this).hasClass("btn-default")) {
            $.post('/' + game_id + '/' + weapon_name + '/' + loadout_id + '/upvote', function(data) {
                $('#count-' + loadout_id).text(parseInt($('#count-' + loadout_id).text()) + 1);
                $('#upvote-' + loadout_id).attr("class", "btn btn-primary clickable");
            }, 'json');
        } else {
            $.post('/' + game_id + '/' + weapon_name + '/' + loadout_id + '/detach', function(data) {
                $('#count-' + loadout_id).text(parseInt($('#count-' + loadout_id).text()) - 1);
                $('#upvote-' + loadout_id).attr("class", "btn btn-default clickable");
            }, 'json');
        }
    });
</script>
@stop
