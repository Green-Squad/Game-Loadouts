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
    <div class="panel panel-info">
      <div class="panel-heading">
        <div class="row">
          <div class="col-xs-6">
            <i class="fa fa-user fa-5x"></i>
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
      </div> </a>
    </div>
  </div>
    <div class="col-lg-3">
    <div class="panel panel-success">
      <div class="panel-heading">
        <div class="row">
          <div class="col-xs-6">
            <i class="fa fa-play fa-5x"></i>
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
</div><!-- /.row -->

<div class="row">
  <div class="col-lg-6">
    <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-money"></i> Recent Users</h3>
      </div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th> Email </th>
                <th> First Name </th>
                <th> Last Name </th>
                <th> Role </th>
                <th> Created at </th>
              </tr>
            </thead>
            <tbody>
              @foreach(UserController::recentUsers(5) as $user)
              <tr>
                <td> {{ $user -> email }} </td>
                <td> {{ $user -> first_name }} </td>
                <td> {{ $user -> last_name }} </td>
                <td> {{ $user -> role }} </td>
                <td> {{ $user -> created_at }} </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="text-right">
          <a href="{{ route('modUsers') }}">View All Users <i class="fa fa-arrow-circle-right"></i></a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-money"></i> Recent Games</h3>
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
          <a href="{{ action('GameController@index') }}">View All Games <i class="fa fa-arrow-circle-right"></i></a>
        </div>
      </div>
    </div>
  </div>
</div><!-- /.row -->
@stop