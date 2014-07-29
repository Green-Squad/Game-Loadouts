@extends('layout')

@section('subtitle')
Game Loadouts
@stop

@section('description')
Game Loadouts is a portal for finding the best ways to outfit your weapons in your favorite games like Titanfall and Battlefield 4.
@stop

@section('sub-header')
<section id="home" >
    <!-- Sub Header -->
    <div class="sub-header" >
        <!-- Slider -->
        <div class="bannercontainer">
            <div class="banner">
                <ul>
                    @foreach($games as $game)
                    <li
                    class="slide1"
                    data-transition="slidehorizontal"
                    data-slotamount="1"
                    >
                        <img src="{{ asset($game -> image_url )}}" alt="{{ $game -> id }}" />
                        <a href="{{ route('showGame', urlencode($game -> id)) }}">
                            <div
                            class="caption lfb ltb"
                            data-x="525"
                            data-y="120"
                            data-start="500"
                            data-easing="easeOutBack"
                            >
                                <h2>{{ $game -> id }}</h2>
                            </div>
                            <div
                            class="caption lfb ltb"
                            data-x="525"
                            data-y="104"
                            data-start="500"
                            >
                                <p>
                                    View Loadouts
                                </p>
                            </div>
                        </a>
                    </li>
                    @endforeach
                </ul>
                <div class="tp-bannertimer tp-bottom"></div>
            </div>
        </div>
        <!-- End Slider -->
    </div>
</section>
@stop

@section('content')
<div class="row">
    <div class="col-md-4">
        <h2>What is Game Loadouts?</h2>
        <span class="line"> <span class="sub-line"></span> </span>
        <p>Game Loadouts is a portal for finding the best ways to outfit your weapons in your favorite games.</p>
        <p>This website comes in handy when you need to complete challenges with various weapons and need to know the best attachment combination for that weapon.</p>
        <p>It is also great for fine-tuning your favorite gun to help improve your gameplay. This will save you rounds of trial and error to find the best loadout.</p>
    </div>
    <div class="col-md-4">
        <h2>Site News</h2>
        <span class="line"> <span class="sub-line"></span> </span>
        <!-- start feedwind code --><script type="text/javascript" src="//feed.mikle.com/js/rssmikle.js"></script><script type="text/javascript">(function() {var params = {rssmikle_url: "http://blog.gameloadouts.com/feed/",rssmikle_frame_width: "390",rssmikle_frame_height: "400",rssmikle_target: "_blank",rssmikle_font: "Arial, Helvetica, sans-serif",rssmikle_font_size: "12",rssmikle_border: "off",responsive: "off",rssmikle_css_url: "",text_align: "left",text_align2: "left",corner: "off",scrollbar: "off",autoscroll: "off",scrolldirection: "up",scrollstep: "3",mcspeed: "20",sort: "New",rssmikle_title: "off",rssmikle_title_sentence: "",rssmikle_title_link: "",rssmikle_title_bgcolor: "#9ACD32",rssmikle_title_color: "#FFFFFF",rssmikle_title_bgimage: "",rssmikle_item_bgcolor: "#FFFFFF",rssmikle_item_bgimage: "",rssmikle_item_title_length: "55",rssmikle_item_title_color: "#1C9845",rssmikle_item_border_bottom: "on",rssmikle_item_description: "on",item_link: "off",rssmikle_item_description_length: "150",rssmikle_item_description_color: "#4c4f55",rssmikle_item_date: "gl1",rssmikle_timezone: "Etc/GMT",datetime_format: "%b %e, %Y %l:%M:%S %p",rssmikle_item_description_tag: "off",rssmikle_item_description_image_scaling: "off",article_num: "5",rssmikle_item_podcast: "off",keyword_inc: "",keyword_exc: ""};feedwind_show_widget_iframe(params);})();</script><div style="font-size:10px; text-align:center; width:390;"><a href="http://feed.mikle.com/" target="_blank" style="color:#CCCCCC;">RSS Feed Widget</a><!--Please display the above link in your web page according to Terms of Service.--></div><!-- end feedwind code -->


    </div>
    <div class="col-md-4">
    	@if(Auth::guest() || Auth::user() -> role == 'Guest')
        <h2>Login</h2>
        <span class="line"> <span class="sub-line"></span> </span>
        {{ Form::open(array('action' => 'UserController@login', 'id' => 'logInForm', 'class' => 'form-horizontal')) }}
        <fieldset>
            <div class="form-group">
                <div class="col-md-12">
                    {{ Form::text('emailOrUsername', '', array('class' => 'form-control input-lg', 'placeholder' => 'Username or Email', 'required' => '')) }}
                </div>
            </div>
            <div class="form-group">
                <div class="col-md-12">
                    {{ Form::password('password', array('class' => 'form-control input-lg', 'placeholder' => 'Password', 'required' => '')) }}
                </div>
            </div>
            <div class="form-group">
                <div class="col-md-12">
                    <label class="control-label" style="padding-top: 0" for="remember"> {{ Form::checkbox('remember', 'remember', false, array('class' => 'checkbox-inline', 'id' => 'remember')) }}
                        Keep me logged in </label>
                </div>
            </div>
            <div class="form-group">
                <div class="col-md-12">
                    <button type="reset" class="btn btn-default">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Login
                    </button>
                    <a href="{{ route('reminder') }}" class="btn btn-primary pull-right">
                        Forgot Password
                    </a>
                </div>
            </div>
        </fieldset>
        {{ Form::close() }}
        <h3>Don't have an account?</h3>
        <p>You can <a href="{{ route('join') }}">register</a> for free.</p>
        @else
        	<h2>Hello {{ Auth::user() -> username }}</h2>
        <span class="line"> <span class="sub-line"></span> </span>
        <p>Thank you for stopping by. We hope you enjoy your visit.</p>
        @endif
    </div>
</div>
@stop

@section('scripts')
<script type="text/javascript" src="{{ asset('rs-plugin/js/jquery.themepunch.plugins.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('rs-plugin/js/jquery.themepunch.revolution.min.js') }}"></script>
@stop