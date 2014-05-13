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
                                <h6>
                                <a href="{{ route('showLoadouts', array($game -> id, $weapon -> name)) }}">    
                                    {{ $weapon -> name }}
                                </a>                                           
                                </h6>
                            </li>
                            <li>
                                <i class="separator entypo-play"></i>
                            </li>
                            <li>
                                <h6>Loadout</h6>
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
    <h2><small>Loadout</small></h2>
    @foreach($loadout -> attachments as $attachment)
    <p>
        {{ $attachment -> name }}
    </p>
    @endforeach

    <p>
        What situations do you like to use this {{ $weapon -> name }} loadout in {{ $game -> id }}? Is this a troll loadout? Is it over powered? Discuss below!
    </p>
</div>
<div class="col-lg-12">
    <div class="well">
        <div class="row">
            <?php
            define('DISQUS_SECRET_KEY', 'YswO6okCvBMxLMP0Gf2JHpymuW6Nxx8UYVEwE2h44Xxgej7dziGgEbet2GwokkUP');
            define('DISQUS_PUBLIC_KEY', 'COZqmFYdQiyinQ5MzXSOqRbxOQ8grhFvSd0dYb0Zc37wJSRxzDCCtIUhV83AyUM8');

            if (Auth::check()) {
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
                this.page.remote_auth_s3 = "<?php echo "$message $hmac $timestamp"; ?>
                    ";
                    this.page.api_key = "
                <?php echo DISQUS_PUBLIC_KEY; ?>
                    ";

                    this.sso = {
                    name:    "Game Loadouts",
                    button:  "http://tryhard.dornblaser.me/i/login.png",
                    icon:    "http://tryhard.dornblaser.me/i/login.png",
                    url:     "http://tryhard.dornblaser.me/login/",
                    logout:  "http://tryhard.dornblaser.me/logout/",
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

@stop
