@extends('admin.layout')

@section('subtitle')
Edit Attachment {{ $attachment -> name }}
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>Attachment <small>Edit</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
            <li>
                <i class="icon-file-alt"></i> Attachment
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
            {{ Form::open( array('url' => action('AttachmentController@update', array('id' => $game -> id, 'attachmentID' => $attachment -> id)), 'class' => 'form-horizontal', 'files' => true)) }}
            <fieldset>
                <legend>
                    <h2>Edit Attachment</h2>
                </legend>
                <div class="form-group">
                    <div class="col-lg-12">
                        {{ Form::text('name', $attachment -> name, array('class' => 'form-control input-lg', 'placeholder' => 'Game Name', 'required' => '')) }}
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-lg-12">
                        {{ Form::text('slot', $attachment -> slot, array('class' => 'form-control input-lg', 'placeholder' => 'Attachment Slot Number', 'required' => '')) }}
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