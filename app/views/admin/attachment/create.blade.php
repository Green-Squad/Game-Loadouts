@extends('admin.layout')

@section('subtitle')
Create Attachment
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>Attachment <small>New</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i>
                <a href="{{ route('adminDashboard') }}">
                    Dashboard
                </a>
            </li>
            <li>
                <i class="icon-file-alt"></i>
                <a href="{{ route('admin.game.index') }}">
                    Games
                </a>
            </li>
            <li>
                <i class="icon-file-alt"></i>
                <a href="{{ action('GameController@show', array('id' => $game -> id)) }}">
                    {{ $game -> id }}
                </a>
            </li>
            <li class="active">
                <i class="icon-file-alt"></i> Create Attachment
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-7">
        <div class="well">
            {{ Form::open( array('action' => array('AttachmentController@store', $game -> id), 'class' => 'form-horizontal', 'files' => true)) }}
            <fieldset>
                <div class="form-group">
                    <div class="col-lg-12">
                        {{ Form::text('name', '', array('class' => 'form-control input-lg', 'placeholder' => 'Attachment Name', 'required' => '')) }}
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-lg-12">
                        {{ Form::text('slot', '', array('class' => 'form-control input-lg', 'placeholder' => 'Attachment Slot Number', 'required' => '')) }}
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

@section('page-scripts')
<script type="text/javascript">
$('input[name=image]').change(function() {
var name = $('input[name=image]').val().replace(/C:\\fakepath\\/i, '').replace(/.png|.jpg|.gif|.jpeg/,'');
$('input[name=name]').val(name);
});
</script>
@stop
