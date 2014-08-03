<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <meta name="author" content="">
        <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">

        <title>Admin - @yield('subtitle')</title>

        <!-- Bootstrap core CSS -->
        <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">

        <!-- Add custom CSS here -->
        <link href="{{ asset('css/sb-admin.css') }}" rel="stylesheet">
        <link href="{{ asset('font-awesome/css/font-awesome.min.css') }}" rel="stylesheet">
    </head>
    <body>
        <div id="wrapper">

            <!-- Sidebar -->
            <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
                <!-- Brand and toggle get grouped for better mobile display -->
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="{{ route('adminDashboard') }}">
                        Admin Dashboard
                    </a>
                </div>

                <!-- Collect the nav links, forms, and other content for toggling -->
                <div class="collapse navbar-collapse navbar-ex1-collapse">
                    <ul class="nav navbar-nav side-nav">
                        <li>
                            <a href="{{ route('adminDashboard') }}"><i class="fa fa-dashboard"></i> Dashboard</a>
                        </li>
                        <li class="dropdown open">
                            <a href="#" class="dropdown-toggle" data-toggle=""><i class="fa fa-caret-square-o-down"></i> Users <b class="caret"></b></a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a href="{{ route('createUser') }}">
                                        Create New
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('modUsers') }}">
                                        Users List
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('modGuests') }}">
                                        Guests List
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('modConverted') }}">
                                        Converted Guests List
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="dropdown open">
                            <a href="#" class="dropdown-toggle" data-toggle=""><i class="fa fa-caret-square-o-down"></i> Games <b class="caret"></b></a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a href="{{ action('GameController@create') }}">
                                        Create New
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ action('GameController@index') }}">
                                        Games List
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="dropdown open">
                            <a href="#" class="dropdown-toggle" data-toggle=""><i class="fa fa-caret-square-o-down"></i> Loadouts <b class="caret"></b></a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a href="{{ action('LoadoutController@listLoadouts') }}">
                                        View Loadouts
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ action('LoadoutController@listVotes') }}">
                                        View Votes
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>

                    <ul class="nav navbar-nav navbar-right navbar-user">
                        <li>
                            {{ HTML::linkRoute('home', 'Home') }}
                        </li>
                        <li class="dropdown user-dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-user"></i> {{ Auth::user() -> username }} <b class="caret"></b></a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a href="#"><i class="fa fa-user"></i> Profile</a>
                                </li>
                                <li>
                                    <a href="#"><i class="fa fa-envelope"></i> Inbox <span class="badge">7</span></a>
                                </li>
                                <li>
                                    <a href="#"><i class="fa fa-gear"></i> Settings</a>
                                </li>
                                <li class="divider"></li>
                                <li>
                                    <a href="{{ route('logout') }}"><i class="fa fa-power-off"></i> Logout</a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div><!-- /.navbar-collapse -->
            </nav>

            <div id="page-wrapper">

                @if(Session::has('alert'))
                <div class="alert alert-dismissable {{ Session::get('alert-class') }}">
                    <button type="button" class="close" data-dismiss="alert">
                        ×
                    </button>
                    {{ Session::get('alert') }}
                </div>
                @endif

                @yield('content')

            </div><!-- /#page-wrapper -->

        </div><!-- /#wrapper -->

        <!-- JavaScript -->
        <script src="{{ asset('js/jquery-1.10.2.js') }}"></script>
        <script src="{{ asset('js/bootstrap.min.js') }}"></script>

        @yield('page-scripts')

    </body>
</html>