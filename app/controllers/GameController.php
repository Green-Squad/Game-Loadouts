<?php

class GameController extends BaseController {

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

        try {
            $game = new Game;
            $game -> id = $id;
            $game -> live = $live;
            $game -> save();
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array('alert' => 'Error: Failed to create new game', 'alert-class' => 'alert-danger'));
        }
        return Redirect::route('admin.game.index') -> with(array('alert' => 'Game has been successfully created.', 'alert-class' => 'alert-success'));
    }

    public function show(Game $game) {
        // Return a view of a specific game (GET)
        $weapons = Weapon::where('game_id', $game -> id) -> get();
        $attachments = Attachment::where('game_id', $game -> id) -> get();
        return View::make('admin.game.show', array('game' => $game, 'weapons' => $weapons, 'attachments' => $attachments));
    }

    public function edit(Game $game) {
        // Return a view with a populated form to edit a specific game (GET)
        return View::make('admin.game.edit', compact('game'));
    }

    public function update(Game $game) {
        // Update a specific game (PUT/PATCH)
        $id = Input::get('id');
        $live = Input::get('live');

        try {
            $game -> id = $id;
            $game -> live = $live;
            $game -> save();
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array('alert' => 'Error: Failed to update game', 'alert-class' => 'alert-danger'));
        }
        return Redirect::route('adminDashboard') -> with(array('alert' => 'Game has been successfully updated.', 'alert-class' => 'alert-success'));
    }

    public function destroy(Game $game) {
        // Delete a specific game (DELETE)
        try {
            $gameID = $game -> id;
            $game -> delete();
        } catch(\Illuminate\Database\QueryException $e) {
            return Redirect::to('admin/game') -> with(array('alert' => 'Error: Failed to delete game.', 'alert-class' => 'alert-danger'));
        }
        return Redirect::to('admin/game') -> with(array('alert' => "You have successfully deleted game $gameID.", 'alert-class' => 'alert-success'));
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
        return View::make('game', compact('game', 'weapons'));
    }

}
