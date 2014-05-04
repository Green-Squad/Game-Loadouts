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
            <div class="col-lg-6">
                {{ Form::text('first_name', '', array('class' => 'form-control input-lg', 'placeholder' => 'First Name', 'required' => '')) }}
            </div>
            <div class="col-lg-6">
                {{ Form::text('last_name', '', array('class' => 'form-control input-lg', 'placeholder' => 'Last Name', 'required' => '')) }}
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
                {{ Form::password('confirm_password', array('class' => 'form-control input-lg', 'placeholder' => 'Confirm Password', 'required' => '')) }}
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