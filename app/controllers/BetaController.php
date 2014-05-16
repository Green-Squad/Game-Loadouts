<?php

class BetaController extends BaseController {

    public function create() {
        $id = str_random(16);    
        $beta = new Beta;
        $beta -> id = $id;
        $beta -> save();
        $beta = Beta::findOrFail($id);
        return $beta -> id;
    }

}
?>
