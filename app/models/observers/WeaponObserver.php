<?php

/**
 * Class WeaponObserver
 */
class WeaponObserver extends BaseObserver {

    public function created(Eloquent $model) {
        // forget from cache
    }
    
    public function saved(Eloquent $model) {
        // forget from cache
    }
    
    public function updated(Eloquent $model) {
        // forget from cache
    }
    
    public function deleted(Eloquent $model) {
        // forget from cache
    }

}
