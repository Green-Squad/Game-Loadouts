<!DOCTYPE html>
<html>
    <head>
        <!-- Define Charset -->
        <meta charset="utf-8">

        <!-- Page Title -->
        <title>@yield('subtitle', 'Game Loadouts')</title>

        <!-- Responsive Metatag -->
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />

        <!-- CSS -->
        <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" />
        <link rel="stylesheet" type="text/css" href="{{ asset('rs-plugin/css/settings.css') }}" media="screen" />
        <link href="{{ asset('css/styles.css') }}" rel="stylesheet" />
        @yield('css', '<link href="/css/color/green.css" rel="stylesheet" />')
        <link href="{{ asset('css/media-queries.css') }}" rel="stylesheet" />

        <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
        <!--[if lt IE 9]>
        <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

        <!-- Media queries -->
        <!--[if lt IE 9]>
        <script src="http://css3-mediaqueries-js.googlecode.com/svn/trunk/css3-mediaqueries.js"></script>
        <![endif]-->
    </head>
    <body>
        
        <!-- Header -->
        <header>
            <!-- Top Bar -->

            <!-- Main Menu -->
            <div class="main-menu" >
                <div class="main-menu-line" >
                    <div class="container">
                        <div class="row">
                            <nav class="navbar" role="navigation">
                                <div class="navbar-header">
                                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
                                        <span class="sr-only">Toggle navigation</span>
                                        <span class="icon-bar"></span>
                                        <span class="icon-bar"></span>
                                        <span class="icon-bar"></span>
                                    </button>
                                    <h1 class="logo"><a href="{{ route('home') }}"> Game Loadouts </a></h1>
                                </div>

                                <!-- Collect the nav links, forms, and other content for toggling -->
                                <div class="collapse navbar-collapse navbar-ex1-collapse">
                                    <ul class="nav navbar-nav navbar-right">
                                        {{ HTML::navLink("/", 'Home') }}
                                        <li class="dropdown">
                                            <a href="{{ route('showGames') }}" class="dropdown-toggle"> Games <b class="caret"></b> </a>
                                            <ul>
                                                @foreach(GameController::listGames() as $game)
                                                <li>
                                                    <a href="{{ route('showGame', $game -> id) }}"> {{ $game -> id }} </a>
                                                </li>
                                                @endforeach
                                            </ul>
                                        </li>
                                        @if(Auth::guest())
                                        {{ HTML::navLink("join", 'Join') }}
                                        {{ HTML::navLink("login", 'Login') }}
                                        @elseif(Auth::check())
                                        @if(Auth::user() -> role == 'Admin')
                                        <li>
                                            {{ HTML::linkRoute('adminDashboard', 'Admin') }}
                                        </li>
                                        @endif
                                        <li class="dropdown">
                                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"> {{ Auth::user() -> username }} <b class="caret"></b> </a>
                                            <ul>
                                                <li>
                                                    {{ HTML::linkRoute('account', 'Account') }}
                                                </li>
                                                <li>
                                                    {{ HTML::linkRoute('logout', 'Logout') }}
                                                </li>
                                            </ul>
                                        </li>
                                        @endif
                                    </ul>
                                </div><!-- /.navbar-collapse -->
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Main Menu -->
        </header>
        <!-- End header -->

        <!-- Content -->
        @yield('sub-header')
        @yield('intro')
        <article class="article-container">
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
            </div>
        </article>

        <!-- Footer -->
        <footer>
            <div class="container" >
                <div class="row misc">
                    <div class="col-md-3" >
                        <h3>About Game Loadouts</h3>
                        <p>Game Loadouts is a portal for finding the best ways to outfit your weapons in your favorite games.</p>
        				<p>This website comes in handy when you need to complete challenges with various weapons and need to know the best attachment combination for that weapon.</p>
        				<p>It is also great for fine-tuning your favorite gun to help improve your gameplay. This will save you rounds of trial and error to find the best loadout.</p>
                    </div>
                    <div class="col-md-3">
                        <h3>Feedback</h3>
                        <p>
                            Submit bug reports or suggest new features for the site!
                        </p>
                        <p>Use the link below and then select "Submit New Idea" on the top left.</p>
                        <p>
                            <a class="button-gym" href="http://greensquad.ideascale.com/a/ideafactory.do?id=29675&amp;mode=top&amp;discussionFilter=byids&amp;discussionID=7136"> Submit Feedback </a>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <h3>Get Social</h3>
                        <p>
                            Follow us on these social networks to keep up-to-date with the site!
                        </p>
                        <ul class="social" >
                            <li>
                                <a href="#"><i class="entypo-facebook" ></i></a>
                            </li>
                            <li>
                                <a href="https://twitter.com/GameLoadouts"><i class="entypo-twitter" ></i></a>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-3">
                        <h3>Games</h3>
                         <p></p>  
                         @foreach(GameController::listGames() as $game)
                                <p><a class="button-gym" href="{{ route('showGame', $game -> id)  }}">{{ $game -> id  }}</a></p>
                         @endforeach
                    </div>
                </div>
            </div>
            <div class="copyright" >
                <div class="container">
                    <p class="pull-left" >
                        Copyright &copy;
                        <a href="{{ route('home') }}"> Game Loadouts </a>
                        {{ date('Y') }}
                    </p>
                    <ul class="main-links pull-right" >
                        <li>
                            {{ HTML::linkRoute('home', 'Home') }}
                        </li>
                        <li>
                            {{ HTML::linkRoute('showGames', 'Games') }}
                        </li>
                        @if(Auth::guest())
                        <li>
                            {{ HTML::linkRoute('join', 'Join') }}
                        </li>
                        <li>
                            {{ HTML::linkRoute('login', 'Login') }}
                        </li>
                        @elseif(Auth::check())
                        @if(Auth::user() -> role == 'Admin')
                        <li>
                            {{ HTML::linkRoute('adminDashboard', 'Admin') }}
                        </li>
                        @endif
                        <li>
                            {{ HTML::linkRoute('account', 'Account') }}
                        </li>
                        <li>
                            {{ HTML::linkRoute('logout', 'Logout') }}
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </footer>
        <!-- End footer -->

        <a href="#" class="scrollup"><i class="entypo-up-open"></i></a>

        <!-- Javascript Files -->
        <!-- jQuery -->
        <script src="{{ asset('js/jquery-1.9.1.min.js') }}" type="text/javascript" ></script>

        <!-- Respond media queries for IE8 -->
        <script src="{{ asset('js/respond.min.js') }}"></script>

        <!-- Bootstrap -->
        <script src="{{ asset('js/bootstrap.min.js') }}" type="text/javascript" ></script>

        <!-- Easing -->
        <script type="text/javascript" src="{{ asset('js/jquery.easing.min.js') }}" ></script>

        <!-- Placeholder.js http://widgetulous.com/placeholderjs/ -->
        <script type="text/javascript" src="{{ asset('js/placeholder.js') }}" ></script>

        <!-- Retina Display -->
        <script type="text/javascript" src="{{ asset('js/retina.js') }}" ></script>

        <!-- Custom site js-->
        <script src="{{ asset('js/script.js') }}" type="text/javascript" ></script>
        <!-- End Javascript Files -->

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
