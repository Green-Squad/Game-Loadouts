@extends('layout')

@section('subtitle')
Join Game Loadouts
@stop

@section('description')
Join Game Loadouts. You can sign up for free here.
@stop

@section('content')
<div class="col-md-5">
    <h2>Benefits</h2>
    <span class="line"> <span class="sub-line"></span> </span>
    <div class="row join-row flex">
        <div class="col-xs-1 flex">    
            <span class="glyphicon glyphicon-ban-circle glyphicon-big vertical-center"></span>
        </div>
        <div class="col-xs-11 flex">
            <p class="vertical-center"><strong>No CAPTCHA</strong> when submitting and voting on loadouts</p>
        </div>
    </div>
    <div class="row join-row flex">
        <div class="col-xs-1 flex">    
            <span class="glyphicon glyphicon-arrow-up glyphicon-big vertical-center"></span>
        </div>
        <div class="col-xs-11 flex">
            <p class="vertical-center"><strong>Faster voting</strong> without needing to refresh the page.</p>
        </div>
    </div>
    
    <div class="row join-row flex">
        <div class="col-xs-1 flex">    
            <span class="glyphicon glyphicon-list glyphicon-big vertical-center"></span>
        </div>
        <div class="col-xs-11 flex">
            <p class="vertical-center"><strong>View your submissions</strong> on a dedicated page where you can view and remove them.</p>
        </div>
    </div>
    
    <div class="row join-row flex">
        <div class="col-xs-1 flex">    
            <span class="glyphicon glyphicon-comment glyphicon-big vertical-center"></span>
        </div>
        <div class="col-xs-11 flex">
            <p class="vertical-center"><strong>Comment on loadouts</strong> to further express your thoughts.</p>
        </div>
    </div>
</div>
<div class="col-md-7">
    <h2>Account Registration</h2>
    <span class="line"> <span class="sub-line"></span> </span>
    {{ Form::open(array('action' => 'UserController@join', 'id' => 'registerForm', 'class' => 'form-horizontal')) }}
    <fieldset>
        <div class="form-group">
            <div class="col-md-12">
                {{ Form::text('username', '', array('class' => 'form-control input-lg', 'placeholder' => 'Username', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-12">
                {{ Form::email('email', '', array('class' => 'form-control input-lg', 'placeholder' => 'Email', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-12">
                {{ Form::password('password', array('class' => 'form-control input-lg', 'placeholder' => 'Password', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-12">
                {{ Form::password('password_confirmation', array('class' => 'form-control input-lg', 'placeholder' => 'Confirm Password', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-12">
                <p>By registering, you are agreeing to our <a href="{{ route('terms') }}">Terms of Use</a>.</p>
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-12">
                <button class="btn btn-default btn-lg" type="reset">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary btn-lg">
                    Sign up!
                </button>
            </div>
        </div>
    </fieldset>
    {{ Form::close() }}
</div>

@stop