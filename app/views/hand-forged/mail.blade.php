@extends('layout')
@section('content')
<h2>Local demo mail inbox</h2>
<p>These messages were captured in your demo session; no email was sent.</p>
@foreach($messages as $message)
<article><h3>{{{ $message['subject'] }}}</h3><iframe title="Captured demo email" sandbox="allow-same-origin" srcdoc="{{{ $message['body'] }}}" style="width:100%;height:340px;border:1px solid #ddd"></iframe></article>
@endforeach
@stop
