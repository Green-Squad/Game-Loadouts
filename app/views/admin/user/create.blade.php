@extends('admin.layout')

@section('subtitle')
Create User
@stop

@section('content')

<div class="row">
  <div class="col-lg-12">
    <h1>User <small>Create New</small></h1>
    <ol class="breadcrumb">
      <li>
        <i class="fa fa-dashboard"></i> Dashboard
      </li>
      <li>
        <i class="icon-file-alt"></i> User
      </li>
      <li class="active">
        <i class="icon-file-alt"></i> Create
      </li>
    </ol>
  </div>
</div><!-- /.row -->

<div class="row">
  <div class="col-lg-7">
    <div class="well">
      {{ Form::open( array('action' => 'UserController@create', 'class' => 'form-horizontal')) }}
      <fieldset>
        <legend>
          <h2>Create User</h2>
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
            {{ Form::password('password', array('class' => 'form-control input-lg', 'placeholder' => 'Password')) }}
          </div>
        </div>
        <div class="form-group">
          <div class="col-lg-12">
            {{ Form::label('role', 'Role', array('control-label')) }}
            {{ Form::select('role', array('Standard' => 'Standard', 'Admin' => 'Admin'), 'Standard', array('class' => 'form-control')) }}
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
  </div>
</div><!-- /.row -->

@stop