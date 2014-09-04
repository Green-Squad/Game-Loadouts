@extends('admin.layout')

@section('subtitle')
Dashboard
@stop

@section('content')
<div class="row">
    <div class="col-lg-12">
        <h1>Dashboard <small>Statistics Overview</small></h1>
        <ol class="breadcrumb">
            <li class="active">
                <i class="fa fa-dashboard"></i> Dashboard
            </li>
        </ol>
    </div>
</div><!-- /.row -->

<div class="row">
    <div class="col-lg-3">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-6">
                        <i class="fa fa-users fa-5x"></i>
                    </div>
                    <div class="col-xs-6 text-right">
                        <p class="announcement-heading">
                            {{ UserController::userCount() }}
                        </p>
                        <p class="announcement-text">
                            Registered Users
                        </p>
                    </div>
                </div>
            </div>
            <a href="{{ route('modUsers') }}">
                <div class="panel-footer announcement-bottom">
                    <div class="row">
                        <div class="col-xs-6">
                            View Users
                        </div>
                        <div class="col-xs-6 text-right">
                            <i class="fa fa-arrow-circle-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="panel panel-info">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-6">
                        <i class="fa fa-user fa-5x"></i>
                    </div>
                    <div class="col-xs-6 text-right">
                        <p class="announcement-heading">
                            {{ UserController::guestCount() }}
                        </p>
                        <p class="announcement-text">
                            Guests
                        </p>
                    </div>
                </div>
            </div>
            <a href="{{ route('modGuests') }}">
            <div class="panel-footer announcement-bottom">
                <div class="row">
                    <div class="col-xs-6">
                        View Guests
                    </div>
                    <div class="col-xs-6 text-right">
                        <i class="fa fa-arrow-circle-right"></i>
                    </div>
                </div>
            </div> </a>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="panel panel-warning">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-6">
                        <i class="fa fa-refresh fa-5x"></i>
                    </div>
                    <div class="col-xs-6 text-right">
                        <p class="announcement-heading">
                            {{ UserController::convertedCount() }}
                        </p>
                        <p class="announcement-text">
                            Converted Guests
                        </p>
                    </div>
                </div>
            </div>
            <a href="{{ route('modConverted') }}">
                <div class="panel-footer announcement-bottom">
                    <div class="row">
                        <div class="col-xs-6">
                            View Converted Guests
                        </div>
                        <div class="col-xs-6 text-right">
                            <i class="fa fa-arrow-circle-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="panel panel-success">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-6">
                        <i class="fa fa-gamepad fa-5x"></i>
                    </div>
                    <div class="col-xs-6 text-right">
                        <p class="announcement-heading">
                            {{ GameController::gameCount() }}
                        </p>
                        <p class="announcement-text">
                            Games
                        </p>
                    </div>
                </div>
            </div>
            <a href="{{ action('GameController@index') }}">
            <div class="panel-footer announcement-bottom">
                <div class="row">
                    <div class="col-xs-6">
                        View Games
                    </div>
                    <div class="col-xs-6 text-right">
                        <i class="fa fa-arrow-circle-right"></i>
                    </div>
                </div>
            </div> </a>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-3">
        @if(HelperController::adsEnabled())
        <div class="panel panel-success">
            @else
            <div class="panel panel-danger">
                @endif
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-6">
                            <i class="fa fa-usd fa-5x"></i>
                        </div>
                        <div class="col-xs-6 text-right">
                            <p class="announcement-heading">
                                @if(HelperController::adsEnabled())
                                On
                                @else
                                Off
                                @endif
                            </p>
                            <p class="announcement-text">
                                Advertisements
                            </p>
                        </div>
                    </div>
                </div>
                <a href="http://www.google.com/adsense"> 
                <div class="panel-footer announcement-bottom">
                    <div class="row">
                        <div class="col-xs-6">
                            Adsense
                        </div>
                        <div class="col-xs-6 text-right">
                            <i class="fa fa-arrow-circle-right"></i>
                        </div>
                    </div>
                </div> </a>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-6">
                            <i class="fa fa-suitcase fa-5x"></i>
                        </div>
                        <div class="col-xs-6 text-right">
                            <p class="announcement-heading">
                                {{ LoadoutController::loadoutCount() }}
                            </p>
                            <p class="announcement-text">
                                Loadouts
                            </p>
                        </div>
                    </div>
                </div>
                <a href="{{ action('LoadoutController@listLoadouts') }}">
                <div class="panel-footer announcement-bottom">
                    <div class="row">
                        <div class="col-xs-6">
                            View Loadouts
                        </div>
                        <div class="col-xs-6 text-right">
                            <i class="fa fa-arrow-circle-right"></i>
                        </div>
                    </div>
                </div> </a>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-6">
                            <i class="fa fa-check-square-o fa-5x"></i>
                        </div>
                        <div class="col-xs-6 text-right">
                            <p class="announcement-heading">
                                {{ LoadoutController::voteCount() }}
                            </p>
                            <p class="announcement-text">
                                Votes
                            </p>
                        </div>
                    </div>
                </div>
                <a href="{{ action('LoadoutController@listVotes') }}">
                <div class="panel-footer announcement-bottom">
                    <div class="row">
                        <div class="col-xs-6">
                            View Votes
                        </div>
                        <div class="col-xs-6 text-right">
                            <i class="fa fa-arrow-circle-right"></i>
                        </div>
                    </div>
                </div> </a>
            </div>
        </div>
		<div class="col-lg-3">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-6">
                            <i class="fa fa-check-square-o fa-5x"></i>
                        </div>
                        <div class="col-xs-6 text-right">
                            <p class="announcement-heading">
                                Compile CSS
                            </p>
                            <p class="announcement-text">
                                Compile CSS
                            </p>
                        </div>
                    </div>
                </div>
                <a href="{{ action('HelperController@compileCSS') }}">
                <div class="panel-footer announcement-bottom">
                    <div class="row">
                        <div class="col-xs-6">
                            Compile CSS
                        </div>
                        <div class="col-xs-6 text-right">
                            <i class="fa fa-arrow-circle-right"></i>
                        </div>
                    </div>
                </div> </a>
            </div>
        </div>
    </div><!-- /.row -->

    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-users"></i> Recent Users</h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th> Username </th>
                                    <th> Email </th>
                                    <th> Role </th>
                                    <th> Created at </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(UserController::recentUsers(5) as $user)
                                <tr>
                                    <td> {{ $user -> username }} </td>
                                    <td> {{ $user -> email }} </td>
                                    <td> {{ $user -> role }} </td>
                                    <td> {{ $user -> created_at }} </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-right">
                        <a href="{{ route('modUsers') }}"> View All Users <i class="fa fa-arrow-circle-right"></i> </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-gamepad"></i> Recent Games</h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th> ID </th>
                                    <th> Live </th>
                                    <th> Created at </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(GameController::recentGames(5) as $game)
                                <tr>
                                    <td> {{ $game -> id }} </td>
                                    <td> {{ $game -> live }} </td>
                                    <td> {{ $user -> created_at }} </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-right">
                        <a href="{{ action('GameController@index') }}"> View All Games <i class="fa fa-arrow-circle-right"></i> </a>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.row -->
    @stop
