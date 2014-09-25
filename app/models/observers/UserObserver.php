<?php

/**
 * Class UserObserver
 */
class UserObserver extends BaseObserver {

    public function saved(Eloquent $model) {
        // forget from cache
    }

    public function updated(Eloquent $model) {
        // forget from cache
    }

}
