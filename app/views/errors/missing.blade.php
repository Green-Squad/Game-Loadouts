@extends('layout')

@section('subtitle')

404

@stop

@section('content')

<!-- begin error page -->
<article class="article-container">
    <div class="container" >
        <div class="row errorpage">
            <div class="col-md-12">
                <strong>404</strong>
                <br />
                <b>Oops... Page Not Found!</b>

                <i>Sorry the page could not be found.</i>

                <p>
                    Try using the button below to go to home page of the site.
                </p>
                <a href="{{ route('home') }}" class="button-gym big-button"> Home </a>
            </div>
        </div>
    </div>
</article>
<!-- end error page -->

@stop