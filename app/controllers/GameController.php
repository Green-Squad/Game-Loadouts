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

        if (Input::hasFile('image')) {
            $destinationPath = public_path() . '/uploads/';
            $thumbPath = public_path() . '/uploads/thumb/';

            $fileExtension = $image -> getClientOriginalExtension();
            $fileName = $id . '.' . $fileExtension;

            $image -> move($destinationPath, $fileName);

            copy($destinationPath . $fileName, $thumbPath . $fileName);
            HelperController::createThumbnail($thumbPath . $fileName, $fileExtension, $this -> thumb_dimension);
        } else {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to upload image',
                'alert-class' => 'alert-danger'
            ));
        }

        try {
            $game = new Game;
            $game -> id = $id;
            $game -> live = $live;
            $game -> image_url = "uploads/$fileName";
            $game -> thumb_url = "uploads/thumb/$fileName";
            $game -> save();
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to create new game',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::route('admin.game.index') -> with(array(
            'alert' => 'Game has been successfully created.',
            'alert-class' => 'alert-success'
        ));
    }

    public function show(Game $game) {
        // Return a view of a specific game (GET)
        $weapons = Weapon::where('game_id', $game -> id) -> get();
        $attachments = Attachment::where('game_id', $game -> id) -> get();
        return View::make('admin.game.show', array(
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

        try {
            $game -> id = $id;
            $game -> live = $live;

            if (Input::hasFile('image')) {
                $destinationPath = public_path() . '/uploads/';
                $thumbPath = public_path() . '/uploads/thumb/';
                $fileExtension = $image -> getClientOriginalExtension();
                $fileName = $id . '.' . $fileExtension;
                $image -> move($destinationPath, $fileName);

                copy($destinationPath . $fileName, $thumbPath . $fileName);
                HelperController::createThumbnail($thumbPath . $fileName, $fileExtension, $this -> thumb_dimension);

                $game -> thumb_url = "uploads/thumb/$fileName";
                $game -> image_url = "uploads/$fileName";
            }

            $game -> save();
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to update game',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::route('adminDashboard') -> with(array(
            'alert' => 'Game has been successfully updated.',
            'alert-class' => 'alert-success'
        ));
    }

    public function destroy(Game $game) {
        // Delete a specific game (DELETE)
        try {
            $gameID = $game -> id;
            $game -> delete();
        } catch(\Illuminate\Database\QueryException $e) {
            return Redirect::to('admin/game') -> with(array(
                'alert' => 'Error: Failed to delete game.',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::to('admin/game') -> with(array(
            'alert' => "You have successfully deleted game $gameID.",
            'alert-class' => 'alert-success'
        ));
    }

    public function showGames() {
        $games = Game::where('live', 1) -> get();
        return View::make('games', compact('games'));
    }

    public static function gameCount() {
        $gameCount = Game::all() -> count();
        return $gameCount;
    }

    public static function recentGames($num) {
        $recentGames = Game::all() -> reverse() -> take($num);
        return $recentGames;
    }

    // lists the games for the public navigation
    // It only returns 'live' games
    public static function listGames() {
        $games = Game::where('live', 1) -> get();
        return $games;
    }

    public static function listWeapons(Game $game) {
        $weapons = Weapon::where('game_id', $game -> id) -> orderBy('name') -> get();
        //$recentLoadouts = DB::table('loadouts') -> join('weapons', 'loadouts.weapon_id', '=', 'weapons.id') -> where('game_id',$game -> id) -> distinct() -> get();
        //$recentLoadouts = Loadout::join('weapons', 'loadout.weapon_id', '=', 'weapons.id') -> where('game_id',$game -> id) -> take(5) -> get();
        $recentLoadouts = DB::select('SELECT * FROM weapons w JOIN loadouts l ON l.weapon_id = w.id WHERE game_id = \'' . $game -> id . '\' ORDER BY l.updated_at DESC LIMIT 5');
        //return var_dump($recentLoadouts);
        return View::make('game', compact('game', 'weapons', 'recentLoadouts'));
    }

}
