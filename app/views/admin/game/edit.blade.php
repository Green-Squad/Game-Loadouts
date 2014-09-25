@extends('admin.layout')

@section('subtitle')
Edit Game {{ $game -> id }}
@stop

@section('content')
<div class="row">
    <div class="col-lg-12">
        <h1>Game <small>Edit</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
            <li>
                <i class="icon-file-alt"></i> Game
            </li>
            <li class="active">
                <i class="icon-file-alt"></i> Edit
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-7">
        <div class="well">
            {{ Form::open( array('url' => action('GameController@update', array('id' => $game -> id)), 'method' => 'PUT', 'class' => 'form-horizontal', 'files' => true)) }}
            <fieldset>
                <legend>
                    <h2>Edit Game</h2>
                </legend>
                <div class="form-group">
                    <div class="col-lg-7">
                        {{ Form::text('id', $game -> id, array('class' => 'form-control input-lg', 'placeholder' => 'Game Name', 'required' => '')) }}
                    </div>
					<div class="col-lg-5">
                        {{ Form::text('short_name', $game -> short_name, array('class' => 'form-control input-lg', 'placeholder' => 'Short Name')) }}
                    </div>
                </div>
                <div class="form-group">
					<div class="col-lg-6">
                        {{ Form::text('theme_color', $game -> theme_color, array('class' => 'form-control input-lg', 'placeholder' => 'Theme Color', 'required' => '')) }}
                    </div>
                    <div class="col-lg-6">
                        {{ Form::text('live', $game -> live, array('class' => 'form-control input-lg', 'placeholder' => 'Live', 'required' => '')) }}
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
</div>

@stop