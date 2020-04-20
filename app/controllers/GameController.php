<?php
class GameController extends BaseController {
    private $thumb_dimension = 525;

    public function index() {
        // Returns a view with a list of all games (GET)
        $games = Game::all() -> reverse();
        return View::make('admin.games', compact('games'));
    }

    public function create() {
        // Return a view with a create new game form (GET)
        return View::make('admin.game.create');
    }

    public function store() {
        // Save a new game (POST)
        $id = Input::get('id');
        $live = Input::get('live');
        $image = Input::file('image');
        $header_image = Input::file('header_image');
        $short_name = Input::get('short_name');
        $theme_color = Input::get('theme_color');
        
        $destinationPath = public_path() . '/uploads/';
        $thumbPath = public_path() . '/uploads/thumb/';
        $headerPath = public_path() . '/uploads/header/';
        
        if (Input::hasFile('image')) {
            $fileExtension = $image -> getClientOriginalExtension();
            $fileName = urlencode($id) . '.' . $fileExtension;
            $image -> move($destinationPath, $fileName);
            copy($destinationPath . $fileName, $thumbPath . $fileName);
            HelperController::createThumbnail($thumbPath . $fileName, $fileExtension, $this -> thumb_dimension);
        } else {
            return Redirect::back() -> with(array (
                'alert' => 'Error: Failed to upload image',
                'alert-class' => 'alert-danger' 
            ));
        }

        if (Input::hasFile('header_image')) {
            $fileExtension = $header_image -> getClientOriginalExtension();
            $fileName = urlencode($id) . '.' . $fileExtension;
            $header_image -> move($headerPath, $fileName);
        } else {
            return Redirect::back() -> with(array (
                'alert' => 'Error: Failed to upload image',
                'alert-class' => 'alert-danger' 
            ));
        }
        
        try {
            $game = new Game();
            $game -> id = $id;
            $game -> live = $live;
            $game -> image_url = "uploads/$fileName";
            $game -> thumb_url = "uploads/thumb/$fileName";
            $game -> header_url = "uploads/header/$fileName";
            $game -> short_name = $short_name;
			$game -> theme_color = $theme_color;
            $game -> save();
		} catch ( \Illuminate\Database\QueryException $e ) {
            return Redirect::back() -> with(array (
                'alert' => 'Error: Failed to create new game',
                'alert-class' => 'alert-danger' 
            ));
        }
        return Redirect::route('admin.game.index') -> with(array (
            'alert' => 'Game has been successfully created.',
            'alert-class' => 'alert-success' 
        ));
    }

    public function show(Game $game) {
        // Return a view of a specific game (GET)
        $weapons = Weapon::where('game_id', $game -> id) -> get();
        $attachments = Attachment::where('game_id', $game -> id) -> get();
        return View::make('admin.game.show', array (
            'game' => $game,
            'weapons' => $weapons,
            'attachments' => $attachments 
        ));
    }

    public function edit(Game $game) {
        // Return a view with a populated form to edit a specific game (GET)
        return View::make('admin.game.edit', compact('game'));
    }

    public function update(Game $game) {
        // Update a specific game (PUT/PATCH)
        $id = Input::get('id');
        $live = Input::get('live');
        $image = Input::file('image');
        $header_image = Input::file('header_image');
        $short_name = Input::get('short_name');
		$theme_color = Input::get('theme_color');
        
        try {
            $game -> id = $id;
            $game -> live = $live;

            $destinationPath = public_path() . '/uploads/';
            $thumbPath = public_path() . '/uploads/thumb/';
            $headerPath = public_path() . '/uploads/header/';
            
            if (Input::hasFile('image')) {
                $fileExtension = $image -> getClientOriginalExtension();
                $fileName = urlencode($id) . '.' . $fileExtension;
                $image -> move($destinationPath, $fileName);
                
                copy($destinationPath . $fileName, $thumbPath . $fileName);
                HelperController::createThumbnail($thumbPath . $fileName, $fileExtension, $this -> thumb_dimension);
                
                $game -> thumb_url = "uploads/thumb/$fileName";
                $game -> image_url = "uploads/$fileName";
            }

            if (Input::hasFile('header_image')) {
                $fileExtension = $header_image -> getClientOriginalExtension();
                $fileName = urlencode($id) . '.' . $fileExtension;
                $header_image -> move($headerPath, $fileName);

                $game -> header_url = "uploads/header/$fileName";
            } else {
                return Redirect::back() -> with(array (
                    'alert' => 'Error: Failed to upload image',
                    'alert-class' => 'alert-danger' 
                ));
            }
            
            $game -> short_name = $short_name;
			$game -> theme_color = $theme_color;
            $game -> save();
        } catch ( \Illuminate\Database\QueryException $e ) {
            return Redirect::back() -> with(array (
                'alert' => 'Error: Failed to update game',
                'alert-class' => 'alert-danger' 
            ));
        }
        return Redirect::route('admin.game.index') -> with(array (
            'alert' => 'Game has been successfully updated.',
            'alert-class' => 'alert-success' 
        ));
    }

    public function destroy(Game $game) {
        // Delete a specific game (DELETE)
        try {
            $gameID = $game -> id;
            $game -> delete();
        } catch ( \Illuminate\Database\QueryException $e ) {
            return Redirect::back() -> with(array (
                'alert' => 'Error: Failed to delete game.',
                'alert-class' => 'alert-danger' 
            ));
        }
        return Redirect::route('admin.game.index') -> with(array (
            'alert' => "You have successfully deleted game $gameID.",
            'alert-class' => 'alert-success' 
        ));
    }

    public function showGames() {
        // $games = Game::where('live', 1) -> get();
        $games = Cache::remember('games_page', $_ENV ['week'], function () {
            return Game::where('live', 1) -> get();
        });
        return View::make('games', compact('games'));
    }

    public static function gameCount() {
        $gameCount = Game::all() -> count();
        return $gameCount;
    }

    public static function recentGames($num) {
        $recentGames = Game::orderBy('created_at', 'DESC') -> take($num) -> get();
        return $recentGames;
    }
    
    // lists the games for the public navigation
    public static function listGames() {
        //$games = Game::all();
        $games = Cache::remember('games_nav', $_ENV ['week'], function () {
            return Game::all();
        });
        return $games;
    }

    public function listWeapons(Game $game) {
        $weaponsByType = $this -> getWeaponsByType($game);
        $recentLoadouts = DB::select('SELECT * FROM weapons w JOIN loadouts l ON l.weapon_id = w.id WHERE game_id = \'' . $game -> id . '\' ORDER BY l.updated_at DESC LIMIT 5');
        $topLoadouts = GameController::topLoadouts($game);
		if ($game -> live == 0) {
			if (Auth::guest() || Auth::user() -> role != 'Admin') {
				return Response::view('errors.missing', array (), 404);
			}
		}
       	return View::make('game', compact('game', 'weaponsByType', 'recentLoadouts', 'topLoadouts'));
    }
    
    public static function topLoadouts(Game $game) {
    	$topLoadouts = DB::select('SELECT lu.loadout_id, l.id , COUNT( lu.loadout_id ) as count
                                        FROM weapons w
                                        JOIN loadouts l ON l.weapon_id = w.id
                                        JOIN loadout_user lu ON l.id = lu.loadout_id
                                        WHERE game_id =  \'' . $game -> id . '\'
                                        GROUP BY lu.loadout_id
                                        ORDER BY COUNT( lu.loadout_id ) DESC
                                        LIMIT 5');
    	return $topLoadouts;
    }

    public function getWeaponsByType(Game $game) {
        $weapons = Weapon::where('game_id', $game -> id) -> orderBy('name') -> get();
        $weaponsByType = array ();
        foreach ( $weapons as $weapon ) {
            $type = $weapon -> type;
            if (isset($weaponsByType [$type])) {
                array_push($weaponsByType [$type], $weapon);
            } else {
                $weaponsByType [$type] = array (
                    $weapon 
                );
            }
        }
        ksort($weaponsByType);
        return $weaponsByType;
    }
}
