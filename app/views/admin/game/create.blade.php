@extends('admin.layout')

@section('subtitle')
Create Game
@stop

@section('content')
<div class="row">
    <div class="col-lg-12">
        <h1>Game <small>Create New</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
            <li>
                <i class="icon-file-alt"></i> Game
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
            {{ Form::open( array('action' => 'GameController@store', 'class' => 'form-horizontal', 'files' => true)) }}
            <fieldset>
                <legend>
                    <h2>Create Game</h2>
                </legend>
                <div class="form-group">
                    <div class="col-lg-7">
                        {{ Form::text('id', '', array('class' => 'form-control input-lg', 'placeholder' => 'Game Name', 'required' => '')) }}
                    </div>
					<div class="col-lg-5">
                        {{ Form::text('short_name', '', array('class' => 'form-control input-lg', 'placeholder' => 'Short Name')) }}
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-lg-6">
                        {{ Form::text('theme_color', '', array('class' => 'form-control input-lg', 'placeholder' => 'Theme Color', 'required' => '')) }}
                    </div>
                    <div class="col-lg-6">
                        {{ Form::text('live', '0', array('class' => 'form-control input-lg', 'placeholder' => 'Live', 'required' => '')) }}
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-lg-12">
                        {{ Form::label('image', 'Upload Image: ', array('class' => 'control-label'))}}
                        {{ Form::file('image', '', array('class' => 'form-control input-lg')) }}
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