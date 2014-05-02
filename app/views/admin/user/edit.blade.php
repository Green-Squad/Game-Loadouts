@extends('admin.layout')

@section('subtitle')
Edit User
@stop

@section('content')

<div class="well">
    {{ Form::open( array('url' => route('editUser', array('email' => $user -> email)), 'class' => 'form-horizontal')) }}
    <fieldset>
        <legend>
            <h2>Edit User</h2>
        </legend>
        <div class="form-group">
            <div class="col-lg-6">
                {{ Form::text('first_name', $user -> first_name, array('class' => 'form-control input-lg', 'placeholder' => 'First Name', 'required' => '')) }}
            </div>
            <div class="col-lg-6">
                {{ Form::text('last_name', $user -> last_name, array('class' => 'form-control input-lg', 'placeholder' => 'Last Name', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-12">
                {{ Form::email('email', $user -> email, array('class' => 'form-control input-lg', 'placeholder' => 'Email', 'required' => '')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-12">
                {{ Form::password('password', array('class' => 'form-control input-lg', 'placeholder' => 'Password')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-6">
                {{ Form::label('role', 'Role', array('control-label')) }}
                {{ Form::select('role', array('Standard' => 'Standard', 'Admin' => 'Admin'), $user -> role, array('class' => 'form-control')) }}
            </div>
            <div class="col-lg-6">
                {{ Form::label('disabled_until', 'Disable For', array('control-label')) }}
                {{ Form::select('disabled_until', array('0' => 'None', '30' => '30 Minutes', '60' => '1 Hour', '720' => '12 Hours', '1440' => '1 Day', '10080' => '1 Week', '43200' => '1 Month', '525600000' => '1000 Years'), $user -> role, array('class' => 'form-control')) }}
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-12">
                <button type="submit" class="btn btn-primary">
                    Save
                </button>
            </div>
        </div>
    </fieldset>
    {{ Form::close() }}
</div>

@stop