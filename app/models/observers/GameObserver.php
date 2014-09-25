<?php

/**
 * Class GameObserver
 */
class GameObserver extends BaseObserver {

    public function created(Eloquent $model) {
        // forget from cache
        Cache::forget('games_slider');
        Cache::forget('games_page');
        Cache::forget('games_nav');
    }
    
    public function saved(Eloquent $model) {
        // forget from cache
        Cache::forget('games_slider');
        Cache::forget('games_page');
        Cache::forget('games_nav');
    }
    
    public function updated(Eloquent $model) {
        // forget from cache
        Cache::forget('games_slider');
        Cache::forget('games_page');
        Cache::forget('games_nav');
    }
    
    public function deleted(Eloquent $model) {
        // forget from cache
        Cache::forget('games_slider');
        Cache::forget('games_page');
        Cache::forget('games_nav');
    }

}
