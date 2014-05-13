<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <meta name="author" content="">

        <title>Tryhard Loadouts - @yield('subtitle')</title>

        <!-- Bootstrap core CSS -->
        <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
        <style>
            body {
                padding-top: 50px;
                padding-bottom: 20px;
            }

            .alert {
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
            <div class="container">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href=""> Tryhard Loadouts </a>
                </div>
                <!-- Collect the nav links, forms, and other content for toggling -->
                <div class="collapse navbar-collapse">
                    <ul class="nav navbar-nav">
                        <li>
                            {{ HTML::linkRoute('home', 'Home') }}                        </li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"> Games <b class="caret"></b> </a>
                            <ul class="dropdown-menu">
                                @foreach(GameController::listGames() as $game)
                                <li class="">
                                    <a href="{{ route('showGame', $game -> id) }}"> {{ $game -> id }} </a>
                                </li>
                                @endforeach
                            </ul>
                        </li>
                    </ul>
                    <ul class="nav navbar-nav navbar-right">
                        @if(Auth::guest())                        <li>
                            {{ HTML::linkRoute('join', 'Join') }}                        </li>
                        <li>
                            {{ HTML::linkRoute('login', 'Login') }}                        </li>                        @elseif(Auth::check())                        @if(Auth::user() -> role == 'Admin')
                        <li>
                            {{ HTML::linkRoute('adminDashboard', 'Admin') }}
                        </li>                        @endif                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"> {{ Auth::user() -> username }} <b class="caret"></b> </a>
                            <ul class="dropdown-menu">
                                <li class="">
                                    {{ HTML::linkRoute('account', 'Account') }}
                                </li>
                                <li class="">
                                    {{ HTML::linkRoute('logout', 'Logout') }}
                                </li>
                            </ul>
                        </li>                        @endif                    </ul>
                </div>
                <!-- /.navbar-collapse -->
            </div>
            <!-- /.container -->
        </nav>

        @yield('header')

        <div class="container">
            @if(Session::has('alert'))
            <div class="alert alert-dismissable {{ Session::get('alert-class') }}">
                <button type="button" class="close" data-dismiss="alert">
                    ×
                </button>
                {{ Session::get('alert') }}
            </div>
            @endif
            @if( $errors->count() > 0 )

            @foreach( $errors -> all() as $message )
            <div class="alert alert-dismissable alert-danger">
                <button type="button" class="close" data-dismiss="alert">
                    ×
                </button>
                {{ $message }}
            </div>
            @endforeach
            @endif

            @yield('content')
            <hr>
            <footer>
                <div class="row">
                    <div class="col-sm-12">
                        <p>
                            Copyright &copy; Green Squad 2014                        </p>
                    </div>
                </div>
            </footer>
        </div>
        <!-- /.container -->
        <!-- JavaScript -->
        <script src="{{ asset('js/jquery-1.10.2.js') }}"></script>
        <script src="{{ asset('js/bootstrap.min.js') }}"></script>

        @if(Auth::guest() || Auth::user() -> role != 'Admin')
        <script>
            (function(i, s, o, g, r, a, m) {
                i['GoogleAnalyticsObject'] = r;
                i[r] = i[r] ||
                function() {
                    (i[r].q = i[r].q || []).push(arguments)
                }, i[r].l = 1 * new Date();
                a = s.createElement(o), m = s.getElementsByTagName(o)[0];
                a.async = 1;
                a.src = g;
                m.parentNode.insertBefore(a, m)
            })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

            ga('create', 'UA-50909901-1', 'gameloadouts.com');
            ga('send', 'pageview');

        </script>
        @endif
        @yield('scripts')
    </body>
</html>