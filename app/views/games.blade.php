@extends('layout')

@section('subtitle')
Games
@stop

@section('description')
Find the best weapon loadouts for {{ HelperController::listToString($games, 'id') }}.
@stop

@section('content')
@if(HelperController::adsEnabled())
<div class="col-md-8">
	@else
	<div class="col-md-12">
		@endif
		<h2>Games</h2>
		<span class="line"> <span class="sub-line"></span></span>
		@for($i = 0; $i < 2; $i++)
							<?php $counter = 0 + $i; ?>	
							<div class="col-md-6">
		@foreach(GameController::listGames() as $game)
			@if($game -> live == 1)
				@if($counter++ % 2 == 0)
				<article class="post">
					<a href="{{ route('showGame', urlencode($game -> id)) }}"> <img src="{{ asset($game -> thumb_url) }}" alt="{{ $game -> id }}" />
						<header>
							<h3>{{ $game -> id }}</h3>
						</header></a>
				</article>
				@endif
			@elseif(Auth::check() && Auth::user() -> role == "Admin")
				@if($counter++ % 2 == 0)
				<article class="post">
					<a href="{{ route('showGame', urlencode($game -> id)) }}"> <img src="{{ asset($game -> thumb_url) }}" alt="{{ $game -> id }}" />
						<header>
							<h3>{{ $game -> id }}</h3>
						</header></a>
				</article>
				@endif
			@endif
		@endforeach
	</div>
	@endfor


</div>
@if(HelperController::adsEnabled())
<div class="col-md-4">
	<div class="right-ad-box">
		<style>
			.game-loadouts-responsive-sidebar {
				width: 300px;
				height: 250px;
			}
			@media (min-width: 800px) {
				.game-loadouts-responsive-sidebar {
					width: 300px;
					height: 600px;
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
	</div>
</div>
@endif
@stop
