@extends('admin.layout')

@section('subtitle')
Create Weapon
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>Weapon <small>Create</small></h1>
        <ol class="breadcrumb">
            <li>
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
            <li>
                <i class="icon-file-alt"></i> Weapon
            </li>
            <li class="active">
                <i class="icon-file-alt"></i> Create
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-12">
        <div class="well">
            {{ Form::open( array('action' => array('WeaponController@store', $game -> id), 'class' => 'form-horizontal', 'files' => true)) }}
            <fieldset>
                <legend>
                    <h2>New Weapon for {{ $game -> id }}</h2>
                </legend>
                <div class="form-group">
                    <div class="col-lg-12">
                        {{ Form::text('name', '', array('class' => 'form-control input-lg', 'placeholder' => 'Weapon Name', 'required' => '')) }}
                    </div>
                </div>
                <div class="form-group">
                    @foreach($attachmentsBySlot as $key => $slot)
                    <div class="col-lg-2">
                        <div>
                            {{ Form::label('', 'Slot ' . $key, array('class' => 'control-label')) }}
                        </div>
                        <div class="btn-group-vertical" data-toggle="buttons">
                            @foreach($slot as $attachment)
                            <label class="btn btn-primary">
                                <input type="checkbox" value="{{ $attachment -> id }}" name="attachments[]">
                                {{ $attachment -> name }} </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
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
