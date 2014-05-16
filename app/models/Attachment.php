<?php

class Attachment extends Eloquent {

    protected $table = 'attachments';

    public function weapons() {
        return $this -> belongsToMany('Weapon') -> withTimestamps();
    }
    
    public function loadouts() {
        return $this -> belongsToMany('Loadout') -> withTimestamps();
    }

}
