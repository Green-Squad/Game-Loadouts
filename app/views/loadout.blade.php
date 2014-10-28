@extends('layout')
<?php $prettyAttachments = HelperController::listToString(Loadout::findOrFail($loadout['id']) -> attachments, 'name') ?>
@section('subtitle')
{{ $game -> id . " - " . $weapon -> name . " - " . $prettyAttachments }}
@stop

@section('description')
The {{ $weapon -> name }} in {{ $game -> id }}@if($game -> short_name) ({{ $game -> short_name }})@endif with {{ $prettyAttachments }}  has @if($loadout -> count == 1) 1 vote. @else{{ $loadout -> count }} votes.@endif
@stop

@section('theme-color')
<body class="{{ $game -> theme_color }}">
@stop

@section('sub-header')
<section id="game">
    <!-- Sub Header -->
    <div class="{{ strtolower(preg_replace('/\s/','-', $game -> id))}}-sub-header sub-header">
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
                                <h5>
                                <a href="{{ route('showLoadouts', array(urlencode($game -> id), urlencode($weapon -> name))) }}">    
                                    {{ $weapon -> name }}
                                </a>                                           
                                </h5>
                            </li>
                            <li>
                                <i class="separator entypo-play"></i>
                            </li>
                            <li>
                                <h5>Loadout</h5>
                            </li>
                        </ul>
                    </li>
					<li>
						<h4 class="title" style="padding:5px 5px 4px 5px;">
							Originally submitted by <a href="" style="color:#fff">
								@if(User::find($loadout -> user_id) -> role == 'Guest')
									Guest
								@else
									{{ User::find($loadout -> user_id) -> username }}
								@endif
							</a>
						</h4>
					</li>
                </ul>
            </div>
        </div>
    </div>
</section>
@stop

@section('intro')
<div id="home">
    <div id="intro" style="padding: 15px 0">
        <div class="container">
            <div class="row flex">
                <div class="col-md-6 vertical-center">
                    <img src="{{ asset($weapon -> image_url) }}" alt="{{ $weapon -> name }}" class="weapon-img"/>
                </div>
                <div class="col-md-6 vertical-center">
                    <div class="loadout">
                        <div class="col-md-12">
                            @foreach(Loadout::findOrFail($loadout['id']) -> attachments as $attachment)
                            <div class="attachment">
                                <img src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" />
                                {{ $attachment -> name }}
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="trainers">
    <div id="suscribe">
        <div class="container">
            <div class="col-md-12">
                <div class="row flex padding-8px">
                    <div class="col-md-6 vertical-center">
                        @if ($loadout -> count == 1)
                        <h2><span id="count-{{$loadout -> id }}">1</span> vote for this loadout</h2>
                        @else
                        <h2><span id="count-{{$loadout -> id }}">{{ $loadout -> count }}</span> votes for this loadout</h2>
                        @endif
                    </div>
                    <div class="col-md-6 vertical-center margin-50px">
                        @if (Auth::guest() || Auth::user() -> role == 'Guest')
                            @if ($loadout -> upvoted == 1)
                            {{ Form::open(array('id' => "guestUnvote", 'url' => '/' . urlencode($game -> id) . '/' . urlencode($weapon -> name) . '/' . $loadout['id'] . '/upvoteGuest')) }}
                            <button style="margin: 0 25% 0;" type="submit" class="big-button button-gym nowrap">Remove Vote</button>
                            {{ Form::close() }}
                            @else
                            <a href="javascript:void(0)"  data-toggle="modal" data-target="#guest" class="big-button button-gym nowrap">Vote</a>
                            @endif
                        @elseif ($loadout -> upvoted == 1)
                        <a href="javascript:void(0)" class="big-button button-gym nowrap clickable" id="loadout-{{ $loadout -> id }}" data-loadout_id="{{ $loadout -> id }}">Remove Vote</a>
                        @else
                        <a href="javascript:void(0)" class="big-button button-gym nowrap clickable" id="loadout-{{ $loadout -> id }}" data-loadout_id="{{ $loadout -> id }}">Vote</a>
                        @endif
                    </div>
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
		<ins class="adsbygoogle"
		style="display:block"
		data-ad-client="ca-pub-9067954073014278"
		data-ad-slot="1230654771"
		data-ad-format="auto"></ins>
		<script>
			( adsbygoogle = window.adsbygoogle || []).push({});
		</script>
	</div>
</div>
<div class="col-md-8">
@else
<div class="col-md-12">
@endif
    <h3>
        What situations do you like to use this {{ $weapon -> name }} loadout in {{ $game -> id }}? Is this a troll loadout? Is it over powered? Discuss below!
    </h3>

	<div class="well">
	    <div class="col-md-12">
	        <div class="row">
	            <?php
	            define('DISQUS_SECRET_KEY', $_ENV['DISQUS_SECRET_KEY']);
	            define('DISQUS_PUBLIC_KEY', 'HXGfl9wP4NuXd15qCTPIprKv2rJhzqCp38NlZs5YmA2i3LOlFzTnBAiiWK8MQKI9');
	
	            if (Auth::check() && Auth::user() -> role != 'Guest') {
	                $username = Auth::user() -> username;
	                $email = Auth::user() -> email;
	                $id = Auth::user() -> email;
	
	                $data = array(
	                    "id" => $id,
	                    "username" => $username,
	                    "email" => $email
	                );
	
	                function dsq_hmacsha1($data, $key) {
	                    $blocksize = 64;
	                    $hashfunc = 'sha1';
	                    if (strlen($key) > $blocksize)
	                        $key = pack('H*', $hashfunc($key));
	                    $key = str_pad($key, $blocksize, chr(0x00));
	                    $ipad = str_repeat(chr(0x36), $blocksize);
	                    $opad = str_repeat(chr(0x5c), $blocksize);
	                    $hmac = pack('H*', $hashfunc(($key ^ $opad) . pack('H*', $hashfunc(($key ^ $ipad) . $data))));
	                    return bin2hex($hmac);
	                }
	
	                $message = base64_encode(json_encode($data));
	                $timestamp = time();
	                $hmac = dsq_hmacsha1($message . ' ' . $timestamp, DISQUS_SECRET_KEY);
	            } else {
	                $message = NULL;
	                $timestamp = NULL;
	                $hmac = NULL;
	            }
	            ?>
	
	            <script type="text/javascript">
	                var disqus_config = function() {
	                    this.page.remote_auth_s3 = "<?php echo "$message $hmac $timestamp"; ?>";
	                    this.page.api_key = "<?php echo DISQUS_PUBLIC_KEY; ?>";
	
	                    this.sso = {
	                    name:    "Game Loadouts",
	                    button:  "http://www.gameloadouts.com/img/login.png",
	                    url:     "http://www.gameloadouts.com/login/",
	                    logout:  "http://www.gameloadouts.com/logout/",
	                    width:   "800",
	                    height:  "400"
	                    };
	                };
	            </script>
	
	            <div id="disqus_thread"></div>
	            <script type="text/javascript">
	                /* * * CONFIGURATION VARIABLES: EDIT BEFORE PASTING INTO YOUR WEBPAGE * * */
	                var disqus_shortname = 'tryharddev';
	                var disqus_identifier = 'loadout-' + {{ $loadout -> id }};
	                /* * * DON'T EDIT BELOW THIS LINE * * */
	                (function() {
	                    var dsq = document.createElement('script');
	                    dsq.type = 'text/javascript';
	                    dsq.async = true;
	                    dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
	                    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
	                })();
	            </script>
	            <noscript>
	                Please enable JavaScript to view the <a href="http://disqus.com/?ref_noscript">comments powered by Disqus.</a>
	            </noscript>
	            <a href="http://disqus.com" class="dsq-brlink">comments powered by <span class="logo-disqus">Disqus</span></a>
	        </div>
	    </div>
	</div>
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

	<div class="modal fade" id="guest" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
  {{ Form::open(array('id' => "guestVote", 'url' => '/' . urlencode($game -> id) . '/' . urlencode($weapon -> name) . '/' . $loadout['id'] . '/upvoteGuest')) }}
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title" id="myModalLabel">
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
            <div id="recaptcha"></div>
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
<script type="text/javascript" src="http://www.google.com/recaptcha/api/js/recaptcha_ajax.js"></script>
<style>
@media (max-width: 444px) {
    #recaptcha_challenge_image {
        margin: 0 !important;
        width: 200px !important;
    }
    
    .recaptchatable .recaptcha_r1_c1, .recaptchatable .recaptcha_r3_c1, .recaptchatable .recaptcha_r3_c2, .recaptchatable .recaptcha_r7_c1, .recaptchatable .recaptcha_r8_c1, .recaptchatable .recaptcha_r3_c3, .recaptchatable .recaptcha_r2_c1, .recaptchatable .recaptcha_r4_c1, .recaptchatable .recaptcha_r4_c2, .recaptchatable .recaptcha_r4_c4, .recaptchatable .recaptcha_r2_c2, .recaptchatable .recaptcha_image_cell {
        background: none !important;
    }
}
</style>
<script type="text/javascript">
    $('.clickable').click(function() {
        var game_id = '<?php echo $game -> id; ?>';
        var weapon_name = '<?php echo $weapon -> name; ?>';
        var loadout_id = $(this).data('loadout_id');
        
        if ($(this).text() == 'Vote') {
            $.post('/' + game_id + '/' + weapon_name + '/' + loadout_id + '/upvote', function(data) {
                $('#count-' + loadout_id).text(parseInt($('#count-' + loadout_id).text()) + 1);
                $('#loadout-' + loadout_id).text('Remove Vote');
            }, 'json');
        } else {
            $.post('/' + game_id + '/' + weapon_name + '/' + loadout_id + '/detach', function(data) {
                $('#count-' + loadout_id).text(parseInt($('#count-' + loadout_id).text()) - 1);
                $('#loadout-' + loadout_id).text('Vote');
            }, 'json');
        }
    });
    Recaptcha.create("6Lf9-_YSAAAAAJ9k2G_qXohJi74-pDe8V4NuUdzJ",
            "recaptcha",
            {
        	 theme: "white",
            }
          );
</script>
@stop
