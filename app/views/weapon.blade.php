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
                        <h3 class="title">{{ $game -> id }} \ {{ $weapon -> name }}</h3>
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
                                <a href="{{ route('showGame', $game -> id) }}">
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

@section('content')
<div class="col-lg-12">
    <div class="row">
        <div class="col-lg-6">
            <img src="{{ asset($weapon -> image_url) }}" alt="{{ $weapon -> name }}" style="max-width: 100%" />
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6">
            <?php $count = 1; ?>

            @foreach($loadouts as $loadout)
            <div class="loadout">
                <a class="block" href="{{ route('showLoadout', array($game -> id, $weapon -> name, $loadout['id'])) }}">
                    <div class="col-lg-3 rank">
                        {{ $count++ }}
                    </div>
                    <div class="col-lg-9">
                        @foreach(Loadout::findOrFail($loadout['id']) -> attachments as $attachment)
                        <div class="attachment">
                            <img src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" />
                            {{ $attachment -> name }}
                        </div>
                        @endforeach
                    </div>
                    <div class="clearfix"></div>
                </a>
                <div class="col-lg-12 loadoutButtons">
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
                    <a class="btn btn-default comment" href="{{ route('showLoadout', array($game -> id, $weapon -> name, $loadout['id'])) }}" data-disqus-identifier="loadout-{{ $loadout['id'] }}">
                        Comments
                    </a>
                </div>
            </div>
            @endforeach
        </div>
        <div class="col-lg-6">
            <p>
                Pick the attachments for your loadout submission!
            </p>
            {{ Form::open( array('action' => array('LoadoutController@store', $game -> id, $weapon -> name), 'class' => 'form-horizontal', 'id' => 'storeForm')) }}
            <div class="form-group">
                @foreach($attachmentsBySlot as $key => $slot)
                <div class="col-lg-3">
                    <div>
                        {{ Form::label('', 'Slot ' . $key, array('class' => 'control-label')) }}
                    </div>
                    <div class="btn-group-vertical" data-toggle="buttons">
                        @foreach($slot as $attachment)
                        <label class="btn btn-default">
                            <input type="radio" value="{{ $attachment -> id }}" name="attachment{{ $key }}" required="">
                            {{ $attachment -> name }} </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            <div class="form-group">
                <div class="col-lg-12">
                    <button type="submit" class="btn btn-primary">
                        Submit Loadout
                    </button>
                </div>
            </div>
            {{ Form::close() }}
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <p>
                What is the best loadout for {{ $weapon -> name }} in {{ $game -> id }}?
            </p>
        </div>
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
        $.post('/' + game_id + '/' + weapon_name + '/' + loadout_id + '/upvote', function(data) {
            $('#count-' + loadout_id).text(parseInt($('#count-' + loadout_id).text()) + 1);
            $('#upvote-' + loadout_id).attr("class", "btn btn-primary clickable");
        }, 'json');
    });

    /* * * CONFIGURATION VARIABLES: EDIT BEFORE PASTING INTO YOUR WEBPAGE * * */
    var disqus_shortname = 'tryharddev';
    // required: replace example with your forum shortname

    /* * * DON'T EDIT BELOW THIS LINE * * */
    ( function() {
            var s = document.createElement('script');
            s.async = true;
            s.type = 'text/javascript';
            s.src = 'http://' + disqus_shortname + '.disqus.com/count.js';
            (document.getElementsByTagName('HEAD')[0] || document.getElementsByTagName('BODY')[0]).appendChild(s);
        }()); 
</script>
@stop
