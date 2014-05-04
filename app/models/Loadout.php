<?php

class Loadout extends Eloquent {

    protected $table = 'loadouts';

    public function attachments() {
        return $this -> belongsToMany('Attachment');
    }
    
    public function users() {
        return $this -> belongsToMany('Users');
    }

}
