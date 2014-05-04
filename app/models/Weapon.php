<?php

class Weapon extends Eloquent {

    protected $table = 'weapons';

    public function attachments() {
        return $this -> belongsToMany('Attachment');
    }

}
