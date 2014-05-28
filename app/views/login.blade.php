@extends('layout')

@section('subtitle')
Login to Game Loadouts
@stop

@section('description')
Login with your Game Loadouts account here.
@stop

@section('content')

<div class="col-md-12">
    <h2>Login</h2>
    <span class="line"> <span class="sub-line"></span> </span>
</div>
<div class="col-md-7">
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
                <a href="{{ route('reminder') }}" class="btn btn-primary pull-right"> Forgot Password </a>
            </div>
        </div>
    </fieldset>
    {{ Form::close() }}
    <h3>Don't have an account?</h3>
    <p>
        You can <a href="{{ route('join') }}">register</a> for free.
    </p>
</div>

@stop