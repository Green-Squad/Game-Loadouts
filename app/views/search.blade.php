@extends('layout')

@section('subtitle')
Search Game Loadouts
@stop

@section('description')
Game Loadouts search results page.
@stop

@section('content')
<div class="col-md-12">
	@if(HelperController::adsEnabled())
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
	@endif
        <h2>
            Search Results for '{{ $googleRequestDecoded -> queries -> request[0] -> searchTerms }}'
             @if($googleRequestDecoded -> queries -> request[0] -> totalResults > 0)
                <small class="pull-right">Top {{ count($googleRequestDecoded -> items) }} results</small>
             @endif
        </h2>
        
        <span class="line"> <span class="sub-line"></span> </span>
    <div class="col-md-8">
        @if($googleRequestDecoded -> queries -> request[0] -> totalResults > 0)
            @foreach($googleRequestDecoded -> items as $item)
                <div style="margin-bottom: 20px">
                <h4 class="no-margin">{{ link_to($item -> link, $item -> title) }}</h4>
                <p class="no-margin search-url">{{ $item -> link }}</p>
                <p class="no-margin">{{ $item -> snippet }}</p>    
                </div>
            @endforeach
        @else
            <h3>There are no search results.</h3>
        @endif
        
    </div>
    @if(HelperController::adsEnabled())
        <div class="col-md-4">
            <div class="pull-right">
            <style>
                .game-loadouts-responsive-sidebar {
                    width: 300px;
                    height: 600px;
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
</div>
@stop