@extends('layout')

@section('subtitle')

Account

@stop

@section('content')
<div class="col-md-12">
    <h2>Account Information</h2>
    <span class="line"> <span class="sub-line"></span> </span>
</div>
<div class="col-md-7">
    {{ Form::open( array('route' => 'account', 'class' => 'form-horizontal')) }}
    <fieldset>
        <div class="form-group">
            <div class="col-md-12">
                {{ Form::password('password', array('class' => 'form-control input-lg', 'placeholder' => 'New Password')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-12">
                {{ Form::password('confirm_password', array('class' => 'form-control input-lg', 'placeholder' => 'Confirm New Password')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    Save
                </button>
            </div>
        </div>
    </fieldset>
    {{ Form::close() }}
</div>

@stop