@extends('layout')

@section('subtitle')
{{ $game -> id . " - " . $weapon -> name }}
@stop

@section('description')
{{ $game -> id }}@if($game -> short_name) ({{ $game -> short_name }})@endif {{ $weapon -> name }} loadouts are located on this page.
Find the best setup for the {{ $game -> id }}@if($game -> short_name) ({{ $game -> short_name }})@endif {{ $weapon -> name }} here.
@stop

@section('theme-color')
<body class="{{ $game -> theme_color }}">
    @stop

    @section('sub-header')
    <section id="game">
        <!-- Sub Header -->
        <div class="sub-header" style="background-image: url('/{{ $game -> header_url }}')">
            <div class="container">
                <div class="row">
                    <ul class="sub-header-container">
                        <li>
                            <h1 class="title"><span class="game-title">{{ $game -> id }} \</span> {{ $weapon -> name }}</h1>
                        </li>
                        <li>
                            <ul class="custom-breadcrumb">
                                <li>
                                    <h5>
                                        <a href="{{ route('home') }}">
                                            Home
                                        </a></h5>
                                </li>
                                <li>
                                    <i class="separator entypo-play"></i>
                                </li>
                                <li>
                                    <h5>
                                        <a href="{{ route('showGame', urlencode($game -> id)) }}">
                                            {{ $game -> id }}
                                        </a></h5>
                                </li>
                                <li>
                                    <i class="separator entypo-play"></i>
                                </li>
                                <li>
                                    <h5>{{ $weapon -> name }}</h5>
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
                        <img src="{{ asset($weapon -> image_url) }}" alt="{{ $weapon -> name }}" class="weapon-img" />
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
        <div class="top-ad-box">
            <script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
            <!-- Game Loadouts Leaderboard -->
            <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-9067954073014278" data-ad-slot="1230654771" data-ad-format="auto"></ins>
            <script>
                (adsbygoogle = window.adsbygoogle || []).push({});

            </script>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <?php $count = 1; ?>

            <h2>Top voted loadouts</h2>
            <span class="line"> <span class="sub-line"></span> </span>

            @if(empty($loadouts))
            <p>There are no loadouts for the {{ $weapon -> name }} yet. Be the first to submit your favorite loadout!</p>
            @else
            @foreach($loadouts as $loadout)
            <div class="loadout">
                <a class="block" href="{{ route('showLoadout', array(urlencode($game -> id), urlencode($weapon -> name), $loadout['id'])) }}">
                    <div class="col-md-2 rank theme-color">
                        {{ $count++ }}
                    </div>
                    <div class="col-md-10">
                        @foreach(Loadout::findOrFail($loadout['id']) -> attachments as $attachment)
                        <div class="attachment">
                            @if ($attachment -> thumb_url)
                            <img class="mr-5" src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" />
                            @endif
                            {{ $attachment -> name }}
                        </div>
                        @endforeach
                    </div>
                    <div class="clearfix"></div>
                </a>
                <div class="col-md-12 loadoutButtons">
                    @if (Auth::guest() || Auth::user() -> role == 'Guest')

                    @if($loadout['upvoted'])
                    {{ Form::open(array('id' => "guestUnvote", 'class' => 'pull-left', 'style' => 'margin-right: 4px','url' => '/' . $game -> id . '/' . $weapon -> name . '/' . $loadout['id'] . '/upvoteGuest')) }}
                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-star-empty"></span> vote (<span id="count-{{ $loadout['id'] }}">{{ $loadout['count'] }}</span>)
                    </button>
                    {{ Form::close() }}
                    @else
                    <button class="btn btn-default" data-toggle="modal" data-target="#guest" onclick="guestLoadoutId({{$loadout['id']}})">
                        <span class="glyphicon glyphicon-star-empty"></span> vote (<span id="count-{{ $loadout['id'] }}">{{ $loadout['count'] }}</span>)
                    </button>
                    @endif

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
                        <span class="glyphicon glyphicon-comment"></span> 0 Comments
                        @elseif ($loadout['comments'] == 1)
                        <span class="glyphicon glyphicon-comment"></span> 1 Comment
                        @else
                        <span class="glyphicon glyphicon-comment"></span> {{ $loadout['comments'] }} Comments
                        @endif
                    </a>
                    <a class="btn btn-default" href="{{ route('showLoadout', array(urlencode($game -> id), urlencode($weapon -> name), $loadout['id'])) }}" data-disqus-identifier="loadout-{{ $loadout['id'] }}">
                        <span class="glyphicon glyphicon-screenshot"></span> View Loadout
                    </a>
                </div>
            </div>
            @endforeach
            @endif
        </div>

        <div class="col-md-6">
            <h2>Submit your own loadout!</h2>
            <span class="line"><span class="sub-line"></span></span>
            <h3>
                @if ($weapon -> min_attachments == $weapon -> max_attachments)
                @if ($weapon -> min_attachments == 1)
                Select 1 attachment.
                @else
                Select {{ $weapon -> min_attachments }} attachments.
                @endif
                @elseif ($weapon -> max_attachments - $weapon -> min_attachments == 1)
                Select {{ $weapon -> min_attachments }} or {{ $weapon -> max_attachments }} attachments.
                @else
                Select between {{ $weapon -> min_attachments }} and {{ $weapon -> max_attachments }} attachments.
                @endif
            </h3>

            {{ Form::open( array('action' => array('LoadoutController@store', $game -> id, $weapon -> name), 'class' => 'form-horizontal', 'id' => 'storeForm')) }}
            <div class="form-group">
                <ul class="nav nav-tabs nav-tabs-sec" id="myTab">
                    <?php $counter = 0; ?>
                    @foreach($attachmentsBySlot as $key => $slot)
                    <li class="@if ($counter++ == 0) active @endif">
                        <a href="#{{ $key }}" data-toggle="tab">
                            {{ $key }}
                            <span id="{{ $key }}-active" style="display:none" class="glyphicon glyphicon-check"></span>
                        </a>
                    </li>
                    @endforeach
                </ul>
                <div class="tab-content nav-tabs-sec">
                    <?php $counter = 0; ?>
                    @foreach($attachmentsBySlot as $key => $slot)
                    <div class="tab-pane @if ($counter++ == 0) active @endif" id="{{ $key }}">
                        <div class="btn-group-vertical" data-toggle="buttons">
                            @foreach($slot as $attachment)
                            <label class="btn btn-default loadoutButton" data-slot="{{ $key }}">
                                @if ($attachment -> thumb_url)
                                <img src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" style="margin-right: 10px">
                                @endif
                                <input id="{{ $attachment -> id }}" type="checkbox" value="{{ $attachment -> id }}" name="{{ $key }}">
                                {{ $attachment -> name }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="form-group">
                <div class="col-md-12">
                    @if (Auth::guest() || Auth::user() -> role == 'Guest')
                    <button class="btn button-gym" data-toggle="modal" data-target="#guestLoadout">Submit Loadout</button>
                    @else
                    <button type="submit" class="btn button-gym">
                        Submit Loadout
                    </button>
                    @endif
                </div>
            </div>

            @if (Auth::guest() || Auth::user() -> role == 'Guest')
            <div class="modal fade" id="guestLoadout" tabindex="-1" role="dialog" aria-labelledby="guestSubmitModal" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <h4 class="modal-title" id="guestSubmitModal">
                                You are not logged in
                            </h4>
                        </div>
                        <div class="modal-body">
                            <div class="col-lg-4">
                                <p><strong>Use an account</strong></p>
                                <p>
                                    <a href="{{route('login')}}" class="btn btn-primary">Login</a>
                                    <a href="{{route('register')}}" class="btn btn-primary">Join</a>
                                </p>
                                <p><strong>Benefits of an account</strong></p>
                                <ul style="padding-left: 20px">
                                    <li>No CAPTCHA</li>
                                    <li>Faster voting</li>
                                    <li>View your submissions</li>
                                    <li>Custom username</li>
                                    <li>Comment on loadouts</li>
                                </ul>
                            </div>
                            <div class="col-lg-8">
                                <p><strong>Continue as guest</strong></p>
                                <p>You can submit your vote without registering by entering the CAPTCHA below.</p>
                                <div id="recaptchaSubmit" class="g-recaptcha"></div>
                            </div>
                            <div style="clear:both;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Submit Loadout</button>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            {{ Form::close() }}

            @if(HelperController::adsEnabled())
            <div class="right-ad-box">
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
                <ins class="adsbygoogle game-loadouts-responsive-sidebar" style="display:inline-block" data-ad-client="ca-pub-9067954073014278" data-ad-slot="2846988777"></ins>
                <script>
                    (adsbygoogle = window.adsbygoogle || []).push({});
                </script>
            </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="guest" tabindex="-1" role="dialog" aria-labelledby="guestVoteModal" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            {{ Form::open(array('id' => "guestVote")) }}
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                    <h4 class="modal-title" id="guestVoteModal">
                        You are not logged in
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="col-lg-4">
                        <p><strong>Use an account</strong></p>
                        <p>
                            <a href="{{route('login')}}" class="btn btn-primary">Login</a>
                            <a href="{{route('register')}}" class="btn btn-primary">Join</a>
                        </p>
                        <p><strong>Benefits of an account</strong></p>
                        <ul style="padding-left: 20px">
                            <li>No CAPTCHA</li>
                            <li>Faster voting</li>
                            <li>View your submissions</li>
                            <li>Custom username</li>
                            <li>Comment on loadouts</li>
                        </ul>
                    </div>
                    <div class="col-lg-8">
                        <p><strong>Continue as guest</strong></p>
                        <p>You can submit your vote without registering by entering the CAPTCHA below.</p>
                        <div id="recaptchaVote" class="g-recaptcha"></div>
                    </div>

                    <div style="clear:both;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Vote</button>
                </div>
            </div>
            {{ Form::close() }}
        </div>
    </div>
    @stop

    @section('scripts')
    <script src="https://www.google.com/recaptcha/api.js?onload=initRecaptcha&render=explicit" async defer></script>
    <script type="text/javascript">
        var modal_type;

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

        $('input[type="checkbox"]').change(function() {
            $('input[name="' + this.name + '"]').not(this).parent().removeClass("active");

            let $this = $(this);
            setTimeout(function() {
                let labels = document.querySelectorAll('.active[data-slot="' + $this.attr('name') + '"]');
                if (labels.length > 0) {
                    $('span#' + $this.attr('name') + '-active').show();
                } else {
                    $('span#' + $this.attr('name') + '-active').hide();
                }
            }, 10);
        });

        function guestLoadoutId(id) {
            var game_id = '<?php echo $game->id; ?>';
            var weapon_name = '<?php echo $weapon->name; ?>';
            $('#guestVote').attr("action", '/' + game_id + '/' + weapon_name + '/' + id + '/upvoteGuest');
        }

        var recaptcha1;
        var recaptcha2;
        var initRecaptcha = function() {
            recaptcha1 = grecaptcha.render('recaptchaVote', {
                'sitekey': '6LdAYgITAAAAAIiGNHQnJvj0JYEW9xG7loWs7fls'
                , 'theme': 'light'
            });

            recaptcha2 = grecaptcha.render('recaptchaSubmit', {
                'sitekey': '6LdAYgITAAAAAIiGNHQnJvj0JYEW9xG7loWs7fls'
                , 'theme': 'light'
            });
        };

    </script>
    @stop
