<?php

class Loadout extends Eloquent {

    protected $table = 'loadouts';

    public function attachments() {
        return $this -> belongsToMany('Attachment') -> withTimestamps();
    }
    
    public function attachmentIDs() {
        return $this -> belongsToMany('Attachment') -> withTimestamps();
    }
    
    public function users() {
        return $this -> belongsToMany('Users');
    }

}
