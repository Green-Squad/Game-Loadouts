@extends('layout')

@section('subtitle')
Game Loadouts
@stop

@section('description')
Game Loadouts contains Titanfall, Battlefield 4 (BF4), and Call of Duty Ghosts (COD Ghosts) loadouts for which you can find the best way to outfit your favorite weapons.
@stop

@section('sub-header')
<div id="home">
    <div id="intro" style="padding-top:50px;padding-bottom:50px;text-align:left;">
        <div class="container">
            <div class="col-md-8 col-md-offset-2">
                <h2 style="margin-top:0; margin-bottom: 20px;">Enter a weapon to find loadouts</h2>
                {{ Form::open(array('action' => 'WeaponController@parseSearch', 'id' => 'searchForm')) }}
                <div class="input-group">
                    <input type="text" class="form-control input-lg" name="query" id="autocomplete-ajax" autofocus autocomplete="off" placeholder="e.g. ACE 23 / Battlefield 4" />
                    <span class="input-group-btn">
                        <button class="input-lg btn btn-primary" type="submit" style="font-size: 18px;">
                        Search
                        </button>
                    </span>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-md-8">
        <h2>What is Game Loadouts?</h2>
        <span class="line"> <span class="sub-line"></span> </span>
        <p>Game Loadouts is a portal for finding the best ways to outfit your weapons in your favorite games.</p>
        <p>This website comes in handy when you need to complete challenges with various weapons and need to know the best attachment combination for that weapon.</p>
        <p>It is also great for fine-tuning your favorite gun to help improve your gameplay. This will save you rounds of trial and error to find the best loadout.</p>
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
            @if($recentLoadout)
                <?php
                    $weapon = Weapon::findOrFail($recentLoadout -> weapon_id);
                ?>
                <p>Your <a href="{{ route('showLoadout', array(urlencode($weapon -> game_id), urlencode($weapon -> name), $recentLoadout -> id)) }}">last loadout</a> was for {{ link_to_route('showLoadouts', $weapon -> name, array(urlencode($weapon -> game_id), urlencode($weapon -> name))) }} in {{ link_to_route('showGame', $weapon -> game_id, urlencode($weapon -> game_id)) }} on {{ date('m/d/Y', strtotime($recentLoadout -> pivot -> updated_at )) }}.</p>
                    <a class="block loadoutSmall" href="{{ route('showLoadout', array(urlencode($weapon -> game_id), urlencode($weapon -> name), $recentLoadout -> id)) }}">
                         <div class="row">
                             <div class="col-md-12">
                                 <img src="{{ asset($weapon -> thumb_url) }}" alt="{{ $weapon -> name }}" class="pull-right" style="max-height: 30px;" />
                                 <h4 class="weaponSmall theme-color">{{ $weapon -> name }}</h4>
                             </div>
                         </div>
                            @foreach(Loadout::findOrFail($recentLoadout -> id) -> attachments as $attachment)
                            <div class="attachmentSmall">
                                <img src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" />
                                {{ $attachment -> name }}
                            </div>
                            @endforeach
                        <div class="clearfix"></div>
                    </a>  
                          
            @else
                <p>You have not submitted any loadouts yet. You can start by searching for a weapon in the search box.</p>
            @endif
        @endif
    </div>
</div>
<div class="row">
	<div class="col-md-12">
		 <h2>Popular Loadouts</h2>
		 <span class="line"> <span class="sub-line"></span> </span>
		<div class="row">
		@foreach($topLoadoutsPerGame as $game)
		    <div class="col-md-4">
				<a class="loadoutSmall block" href="{{ route('showGame', urlencode($game -> id)) }}">
						<h3 class="weaponSmall theme-color" style="margin: 0px 0 3px 0">{{ $game -> id }}
							<small class="pull-right" style="margin: 6px 0 0 0">All Loadouts</small>
						</h3>
					<img src="{{ $game -> thumb_url}}" alt="{{ $game -> id }}" style="width:100%;" />
		        </a>
				<?php 
		        $loadoutCount = 1;
		        $accordianId = "accordian" . preg_replace("/[\s]/", '', $game -> id);
		        ?>
		        <div class="panel-group" id="{{ $accordianId }}">
		        @foreach($game -> topLoadouts as $loadout)
				    <?php
				    $count = $loadout -> count;
				    $loadout = Loadout::findOrFail($loadout -> id);
				    
				    ?>
				   	<div class="panel">
					    <div class="loadoutSmall" style="margin:0">
					      	<h4 class="panel-title">
					        	<a data-toggle="collapse" data-parent="#{{ $accordianId }}" href="#collapse{{ $loadout -> id }}">
				    				<h4 class="weaponSmall theme-color">{{ Weapon::findOrFail($loadout -> weapon_id) -> name }} <small class="pull-right"> ({{ $count }} votes)</small></h4> 
				   				</a>
				   			</h4>
				   		</div>
				   		<div id="collapse{{ $loadout -> id }}" class="panel-collapse collapse @if($loadoutCount++ == 1)in@endif">
				    		<a class="loadoutSmall block" href="{{ route('showLoadout', array(urlencode($game -> id), urlencode(Weapon::findOrFail($loadout -> weapon_id) -> name), urlencode($loadout ->id))) }}"> 
					    	@foreach($loadout -> attachments as $attachment)
						    <div class="attachmentSmall">
						        <img src="{{ asset($attachment -> thumb_url) }}" alt="{{ $attachment -> name }}" />
						        {{ $attachment -> name }}
						    </div> @endforeach
								<div class="row">
									<div class="col-md-12">
										<button class="btn btn-primary pull-right" style="margin: 5px 0 0 0;" href="{{ route('showLoadout', array(urlencode($game -> id), urlencode(Weapon::findOrFail($loadout -> weapon_id) -> name), urlencode($loadout ->id))) }}">
                        					<span class="glyphicon glyphicon-screenshot"></span> View Loadout
                    					</button>
									</div>
								</div>
							</a>
				    	</div>
					</div>
			    @endforeach
		    	</div>
		    </div>
	    @endforeach
		</div>
	</div>
</div>
<div class="row">
    <div class="col-md-8">
        <h2>Site News</h2>
        <span class="line"> <span class="sub-line"></span> </span>
        @for($i = 0; $i < 1; $i++)
		<div class="item">
			<h4><a href="{{ $items[$i]->get_permalink() }}" class="green-a">{{ $items[$i]->get_title() }}</a></h4>
			<p>{{ $items[$i]->get_description() }}</p>
			<p><small>Posted on {{ $items[$i]->get_date('j F Y | g:i a') }}</small></p>
		</div>
	   @endfor
       <p>View more news on the <a href="http://blog.gameloadouts.com">Game Loadouts Blog</a>.</p>
    </div>
    <div class='ajax-poll' tclass='poll'></div>
</div>
@stop

@section('scripts')
<!-- <script type="text/javascript" src="{{ asset('rs-plugin/js/jquery.themepunch.plugins.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('rs-plugin/js/jquery.themepunch.revolution.min.js') }}"></script> -->
<script type="text/javascript" src="/poll/ajax-poll.php"></script>
<script>

$('#autocomplete-ajax').autocomplete({
    serviceUrl: '/search_weapons',
    onSearchStart: function () {
    	$(this).attr('autocomplete', 'off');
    }
});


$('#autocomplete-ajax').focus(function() {
	$('#autocomplete-ajax').attr('autocomplete', 'off');
});
$(document).ready(function() {
    $('#autocomplete-ajax').attr('autocomplete', 'off');
});

</script>
@stop