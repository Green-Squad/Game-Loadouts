<?php

class GameSeeder extends Seeder {

    public function run() {
        $game = new Game;
        $game -> id = 'Titanfall';
        $game -> live = 1;
        $game -> save();
        
        $game = new Game;
        $game -> id = 'Battlefield 3';
        $game -> live = 0;
        $game -> save();
        
        $game = new Game;
        $game -> id = 'Battlefield 4';
        $game -> live = 0;
        $game -> save();
        
        $game = new Game;
        $game -> id = 'Call of Duty';
        $game -> live = 0;
        $game -> save();
        
        $game = new Game;
        $game -> id = 'Black Ops';
        $game -> live = 0;
        $game -> save();
    }

}
