<?php
class HelperController extends BaseController {
	
	function showHome() {
        $items = FeedReader::read('http://blog.gameloadouts.com/feed/') -> get_items();
		
		$topLoadoutsPerGame = Cache::remember('top_loadouts_per_game_home', $_ENV ['hour'], function () {
			$games = Cache::remember('games_home', $_ENV ['week'], function () {
				return Game::where('live', 1) -> orderBy(DB::raw('RAND()')) -> take(3) -> get();
			});		
			foreach($games as $game) {
				$game -> topLoadouts = GameController::topLoadouts($game);
			}
            return $games;
        });
        
        $recentLoadout = '';
        if(Auth::check() && Auth::user() -> role != 'Guest' && Auth::user() -> loadouts) {
            $recentLoadout = Auth::user() -> loadouts;
            $first = true;
            foreach(Auth::user() -> loadouts as $loadout) {
                if ($first) {
                    $recentLoadout = $loadout;
                    $first = false;
                    //return View::make('home', compact('games', 'items', 'recentLoadout'));
                    break;
                }
            }
            if (get_class($recentLoadout) != 'Loadout') {
            	$recentLoadout = NULL;
            }
           // return View::make('home', compact('games', 'items', 'recentLoadout', 'topLoadoutsPerGame'));
        }
		
        return View::make('home', compact('games', 'items', 'topLoadoutsPerGame', 'recentLoadout'));
	}

    public static function adsEnabled() {
        return $_ENV ['ads'];
    }

    public function toggleAds() {
        if ($_ENV ['ads']) {
            $_ENV ['ads'] = 0;
        } else {
            $_ENV ['ads'] = 1;
        }
        return Redirect::back();
    }
    
    // $list is the collection
    // $attribute is the attribute of the list item
    public static function listToString($list, $attribute) {
        $array = array ();
        $counter = 0;
        $string = '';
        foreach ( $list as $key => $value ) {
            $array [$counter ++] = $value -> $attribute;
        }
        if (count($array) > 1) {
            for($i = 0; $i < count($array); $i ++) {
                if ($i < count($array) - 2) {
                    $string .= $array [$i] . ', ';
                } elseif ($i == count($array) - 2) {
                    $string .= $array [$i] . ' ';
                } elseif ($i == count($array) - 1) {
                    $string .= 'and ' . $array [$i];
                }
            }
        } elseif (count($array) == 1) {
            $string = $array [0];
        }
        return $string;
    }

    public static function createThumbnail($thumb_url, $extension, $maxsize) {
        
        /* read the source image */
        if ($extension == 'png') {
            $source_image = imagecreatefrompng($thumb_url);
        } elseif ($extension == 'jpg' || $extension == 'jpeg') {
            $source_image = imagecreatefromjpeg($thumb_url);
        } elseif ($extension == 'gif') {
            $source_image = imagecreatefromgif($thumb_url);
        }
        
        $desired_width = $maxsize;
        
        $width = imagesx($source_image);
        $height = imagesy($source_image);
        
        /* find the "desired height" of this thumbnail, relative to the desired width */
        $desired_height = floor($height * ($desired_width / $width));
        
        /* create a new, "virtual" image */
        $virtual_image = imagecreatetruecolor($desired_width, $desired_height);
        
        imagealphablending($virtual_image, false);
        imagesavealpha($virtual_image, true);
        
        /* copy source image at a resized size */
        imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);
        
        /* create the physical thumbnail image to its destination */
        if ($extension == 'png') {
            imagepng($virtual_image, $thumb_url);
        } elseif ($extension == 'jpg' || $extension == 'jpeg') {
            imagejpeg($virtual_image, $thumb_url);
        } elseif ($extension == 'gif') {
            imagegif($virtual_image, $thumb_url);
        }
    }
	
	public function compileCSS() {
		
		// GitHub: oyejorge/less.php
		$options = array( 'compress'=>true );
		$parser = new Less_Parser( $options );
		$parser->parseFile(app_path() . '/less/styles.less', '/css/' );
		$css = $parser->getCss();
		$cssFile = fopen(public_path() . '/css/styles.min.css', "w") or die("Unable to open file!");
		fwrite($cssFile, $css);
		fclose($cssFile);
		
		// GitHub: leafo/lessphp
		//$less = new lessc; // Compile a file '
		//$less->compileFile(app_path() . '/less/red.less', public_path() . '/css/color/red.css');
		return "It probably compiled...";
	}

    public function sitemap() {
        // use this package for the easy sitemap creation in Laravel 4.*: https://github.com/RoumenDamianoff/laravel4-sitemap
        // then, do something like this for all your dynamic and static content:
        
        // Place the following code in a route or controller that should return a sitemap
        $sitemap = App::make("sitemap");
        
        $time = '2014-05-20T12:30:00+02:00';
        // Add static pages like this:
        $sitemap -> add(URL::to('/'), $time, '1.0', 'daily');
        $sitemap -> add(URL::to('login'), $time, '0.8', 'weekly');
        $sitemap -> add(URL::to('join'), $time, '0.8', 'weekly');
        $sitemap -> add(URL::to('games'), $time, '0.8', 'weekly');
        
        $games = Game::all();
        
        foreach ( $games as $game ) {
            $sitemap -> add(URL::route('showGame', array (
                urlencode($game -> id) 
            )), $time, '0.8', 'daily');
            $weapons = Weapon::where('game_id', $game -> id) -> get();
            foreach ( $weapons as $weapon ) {
                $sitemap -> add(URL::route('showLoadouts', array (
                    urlencode($game -> id),
                    urlencode($weapon -> name) 
                )), $time, '0.8', 'weekly');
                $loadouts = Loadout::where('weapon_id', $weapon -> id) -> get();
                foreach ( $loadouts as $loadout ) {
                    $sitemap -> add(URL::route('showLoadout', array (
                        urlencode($game -> id),
                        urlencode($weapon -> name),
                        urlencode($loadout -> id) 
                    )), $time, '0.5', 'weekly');
                }
            }
        }
        
        // Now, output the sitemap:
        return $sitemap -> render('xml');
    }

    public function stats() {
        $submissionsPerGame = Cache::remember('submissionsPerGame', $_ENV ['hour'], function () {
            return DB::select('SELECT A.game_id as game, A.votes as votes, B.loadouts as loadouts
                                            FROM (
        
                                            SELECT w.game_id, COUNT( lu.loadout_id ) AS votes
                                            FROM weapons w
                                            JOIN loadouts l ON l.weapon_id = w.id
                                            JOIN loadout_user lu ON l.id = lu.loadout_id
                                            GROUP BY w.game_id
                                            ) AS A
                                            JOIN (
        
                                            SELECT w.game_id, COUNT( l.id ) AS loadouts
                                            FROM weapons w
                                            JOIN loadouts l ON l.weapon_id = w.id
                                            GROUP BY w.game_id
                                            ) AS B ON A.game_id = B.game_id');
        });
        
        $submissions = Cache::remember('submissionsStats', $_ENV ['hour'], function () {
            return DB::select('SELECT w.game_id AS game, DATE( lu.created_at ) AS date, COUNT( lu.loadout_id ) AS votes
                                            FROM weapons w
                                            JOIN loadouts l ON l.weapon_id = w.id
                                            JOIN loadout_user lu ON l.id = lu.loadout_id
                                            WHERE DATE(lu.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                            GROUP BY w.game_id, DATE( lu.created_at ) 
                                            ORDER BY DATE( lu.created_at )');
        });
        
        $submissionsPerDay = array ();
        $games = array ();
        
        foreach ( $submissions as $submission ) {
            $game = $submission -> game;
            $submissionsPerDay [$submission -> date] [$game] = intval($submission -> votes);
            if (! in_array($game, $games)) {
                array_push($games, $game);
            }
        }
        $days = array_keys($submissionsPerDay);
        
        $gamesVotes = array ();
        foreach ( $days as $day ) {
            foreach ( $games as $game ) {
                if (! isset($gamesVotes [$game])) {
                    $gamesVotes [$game] = array ();
                }
                if (isset($submissionsPerDay [$day] [$game])) {
                    array_push($gamesVotes [$game], $submissionsPerDay [$day] [$game]);
                } else {
                    array_push($gamesVotes [$game], 0);
                }
            }
        }
        $daysJSON = json_encode($days);
        $gamesVotesJSON = json_encode($gamesVotes);
        $colors = array (
            "rgba(27,130,212,1)",
            "rgba(165,191,114,1)",
            "rgba(176,50,50,1)",
            "rgba(199,182,37,1)",
            "rgba(191,43,133,1)",
            "rgba(90,190,202,1)",
            "rgba(200,200,200,1)",
            "rgba(29,187,133,1)",
            "rgba(100,95,170,1)",
            "rgba(140,100,27,1)" 
        );
        
        return View::make('stats', compact('submissionsPerGame', 'daysJSON', 'gamesVotes', 'colors'));
    }
}
