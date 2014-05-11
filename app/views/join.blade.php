@extends('layout')

@section('subtitle')
Join Us!
@stop

@section('content')

<div class="well">
    {{ Form::open(array('action' => 'UserController@join', 'id' => 'registerForm', 'class' => 'form-horizontal')) }}
    <fieldset>
        <legend>
            <h2>Account Registration</h2>
        </legend>
        <div class="form-group">
            <div class="col-lg-12">
                {{ Form::text('username', '', array('class' => 'form-control input-lg', 'placeholder' => 'Username', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-12">
                {{ Form::email('email', '', array('class' => 'form-control input-lg', 'placeholder' => 'Email', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-12">
                {{ Form::password('password', array('class' => 'form-control input-lg', 'placeholder' => 'Password', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-12">
                {{ Form::password('password_confirmation', array('class' => 'form-control input-lg', 'placeholder' => 'Confirm Password', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-12">
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