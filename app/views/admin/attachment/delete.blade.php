@extends('admin.layout')

@section('subtitle')
Delete Attachment {{ $attachment -> name }}
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>Attachment <small>Delete</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
            <li>
                <i class="icon-file-alt"></i> Attachment
            </li>
            <li class="active">
                <i class="icon-file-alt"></i> Delete
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-7">
        <div class="well">
            {{ Form::open( array('url' => action('AttachmentController@destroy', array('id' => $game -> id, 'attachmentID' => $attachment -> id)), 'class' => 'form-horizontal')) }}
            <fieldset>
                <legend>
                    <h2>Delete Attachment</h2>
                    <p>
                        Are you sure you want to delete Attachment {{ $attachment -> name }}? This process is irreversible.
                    </p>
                </legend>
                <div class="form-group">
                    <div class="col-lg-6">
                        <a class="btn btn-default" href="{{ action('GameController@index') }}">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-danger">
                            Delete
                        </button>
                    </div>
                </div>
            </fieldset>
            {{ Form::close() }}
        </div>
    </div>
</div>

@stop