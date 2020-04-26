@extends('admin.layout')

@section('subtitle')
Import Weapons/Attachments
@stop

@section('content')

<div class="row">
    <div class="col-lg-12">
        <h1>Import</h1>
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
                <i class="icon-file-alt"></i> Import
            </li>
        </ol>
    </div>
</div><!-- /.row -->
<div class="row">
    <div class="col-lg-7">
        <div class="well">
            {{ Form::open( array('action' => array('ImportController@processImport', $game -> id), 'class' => 'form-horizontal', 'files' => true)) }}
            <fieldset>
                <div class="form-group">
                    <div class="col-lg-12">
                        {{ Form::label('Spreadsheet', 'Upload Spreadsheet: ', array('class' => 'control-label'))}}
                        {{ Form::file('spreadsheet', '', array('class' => 'form-control input-lg')) }}
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-lg-12">
                        <button type="submit" class="btn btn-primary">
                            Import
                        </button>
                    </div>
                </div>
            </fieldset>
            {{ Form::close() }}
        </div>
    </div>
    <div class="col-lg-5">
        <div class="well">
        <p>The import requires a .csv file in a certain format.</p>
        <p><strong><a href="/files/import example.csv">Download example file.</a></strong></p>
        </div>
    </div>
</div>

@stop
