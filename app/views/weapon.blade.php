@extends('layout')

@section('subtitle')
{{ $game -> id }}
@stop

@section('content')

<div class="well">
    <div class="row">
        <div class="col-lg-12">
            <h2>{{ $game -> id}} <small>Weapons</small></h2>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6">
            @foreach($loadouts as $loadout)
            <p>
                @foreach($loadout -> attachments as $attachment)
                {{ $attachment -> name }}
                @endforeach
            </p>
            @endforeach
        </div>
        <div class="col-lg-6">
            <p>
                Pick the attachments for your loadout submission!
            </p>
            {{ Form::open( array('action' => array('LoadoutController@store', $game -> id, $weapon -> name), 'class' => 'form-horizontal')) }}
            <div class="form-group">
                @foreach($attachmentsBySlot as $key => $slot)
                <div class="col-lg-3">
                    <div>
                        {{ Form::label('', 'Slot ' . $key, array('class' => 'control-label')) }}
                    </div>
                    <div class="btn-group-vertical" data-toggle="buttons">
                        @foreach($slot as $attachment)
                        <label class="btn btn-default">
                            <input type="radio" value="{{ $attachment -> id }}" name="attachment{{ $key }}">
                            {{ $attachment -> name }} </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            <div class="form-group">
                <div class="col-lg-12">
                    <button type="submit" class="btn btn-primary">
                        Submit Loadout
                    </button>
                </div>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

@stop
