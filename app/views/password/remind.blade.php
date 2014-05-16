@extends('layout')

@section('subtitle')
Password Reminder
@stop

@section('content')
<div class="col-md-12">
    <h2>Password Reminder</h2>
    <span class="line"> <span class="sub-line"></span> </span>
</div>
<div class="col-md-7">
    {{ Form::open(array('action' => 'RemindersController@postRemind', 'id' => 'reminderForm', 'class' => 'form-horizontal')) }}
    <fieldset>
        <div class="form-group">
            <div class="col-lg-12">
                {{ Form::text('email', '', array('class' => 'form-control input-lg', 'placeholder' => 'Email', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    Send Reminder
                </button>
            </div>
        </div>
    </fieldset>
    {{ Form::close() }}
</div>
@stop