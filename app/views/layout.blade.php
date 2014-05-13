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
                                    <h1 class="logo"><a href="{{ route('home') }}">Game Loadouts</a></h1>
                                </div>

                                <!-- Collect the nav links, forms, and other content for toggling -->
                                <div class="collapse navbar-collapse navbar-ex1-collapse">
                                    <ul class="nav navbar-nav navbar-right">
                                        <li>
                                            {{ HTML::linkRoute('home', 'Home') }}
                                        </li>
                                        <li class="dropdown">
                                            <a href="#" class="dropdown-toggle">Games <b class="caret"></b></a>
                                            <ul class="dropdown-menu">
                                                @foreach(GameController::listGames() as $game)
                                                <li class="">
                                                    <a href="{{ route('showGame', $game -> id) }}"> {{ $game -> id }} </a>
                                                </li>
                                                @endforeach
                                            </ul>
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
                                        <li class="dropdown">
                                            <a href="#" class="dropdown-toggle" data-toggle="dropdown"> {{ Auth::user() -> username }} <b class="caret"></b> </a>
                                            <ul class="dropdown-menu">
                                                <li class="">
                                                    {{ HTML::linkRoute('account', 'Account') }}
                                                </li>
                                                <li class="">
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
            <article class="article-container">
                <div class="container" >
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
                            <h3>About the Club</h3>
                            <p>
                                Phasellus sit amet justo sapien. Praesent bibendum, enim non fringilla vestibulum.
                            </p>
                            <p>
                                We can condimentum est lacus ut dolor. Sed facilisis ante felis, vitae mattis massa luctus sit amet. Vestibulum eu blandit ipsum. In ornare enim nunc.
                            </p>
                            <ul class="about" >
                                <li>
                                    <i class="entypo-location" ></i>Street 32165, 646 UK
                                </li>
                                <li>
                                    <i class="entypo-mobile" ></i>(62626) 5154 4545
                                </li>
                                <li>
                                    <i class="entypo-mail" ></i>email@democompany.com
                                </li>
                                <li>
                                    <i class="entypo-clock" ></i>From 10:15 AM to 7:30 PM
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-3">
                            <h3>Useful Links</h3>
                            <ul class="links" >
                                <li>
                                    <a href="#"><i class="entypo-right-open-mini" ></i>Meet the Coaches of the Club</a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-right-open-mini" ></i>Meet the Trainers</a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-right-open-mini" ></i>See the Club inside</a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-right-open-mini" ></i>Testimonials Videos</a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-right-open-mini" ></i>Meet the Trainers of the Club</a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-right-open-mini" ></i>Personal Coaching Video</a>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-3">
                            <h3>Newsletter</h3>
                            <p>
                                Suscribe to our ahasellus sit amet justo sapien and raesent bibendum you will get nim non fringilla vestibulum.
                            </p>
                            <form id="newsletter" role="form" action="newsletter.php" method="post" accept-charset="utf-8">
                                <div class="form-group">
                                    <label for="name" class="sr-only">Your Name</label>
                                    <input type="text" name="name" class="form-control" id="name" placeholder="Your name">
                                </div>
                                <div class="form-group">
                                    <label for="email" class="sr-only">Email address</label>
                                    <input type="email" name="email" class="form-control" id="email" placeholder="Enter email">
                                </div>
                                <input class="button-gym" type="submit" name="submit" value="Suscribe now" id="submit"/>
                            </form>
                        </div>
                        <div class="col-md-3">
                            <h3>Get Social</h3>
                            <p>
                                Follow us on the Social Networks to let all the news and win disccounts!
                            </p>
                            <ul class="social" >
                                <li>
                                    <a href="#"><i class="entypo-facebook" ></i></a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-twitter" ></i></a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-linkedin" ></i></a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-play" ></i></a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-tumblr" ></i></a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-gplus" ></i></a>
                                </li>
                                <li>
                                    <a href="#"><i class="entypo-pinterest-circled" ></i></a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="copyright" >
                    <div class="container">
                        <p class="pull-left" >
                            &copy; All rights reserved. Gym Website by <a href="#">Demo</a>
                        </p>
                        <ul class="main-links pull-right" >
                            <li>
                                <a href="#">Home</a>
                            </li>
                            <li>
                                <a href="#">Classes</a>
                            </li>
                            <li>
                                <a href="#">Trainers</a>
                            </li>
                            <li>
                                <a href="#">Club</a>
                            </li>
                            <li>
                                <a href="#">News</a>
                            </li>
                            <li>
                                <a href="#">Price</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </footer>
            <!-- End footer -->

            <a href="#" class="scrollup"><i class="entypo-up-open"></i></a>

            <!-- Javascript Files -->
            <!-- jQuery -->
            <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js" type="text/javascript" ></script>
            <script>
                window.jQuery || document.write("<script src=\"js/jquery-1.9.1.min.js\" type=\"text/javascript\"><\/script>")
            </script>

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

            <!-- Revolution Slider  -->
            <script type="text/javascript" src="{{ asset('rs-plugin/pluginsources/jquery.themepunch.plugins.min.js') }}"></script>
            <script type="text/javascript" src="{{ asset('rs-plugin/js/jquery.themepunch.revolution.min.js') }}"></script>

            <!-- Custom site js-->
            <script src="{{ asset('js/script.js') }}" type="text/javascript" ></script>
            <!-- End Javascript Files -->
            
            @yield('scripts')

    </body>
</html>