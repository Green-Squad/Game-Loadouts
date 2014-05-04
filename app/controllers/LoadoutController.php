<?php

class LoadoutController extends BaseController {

    public function store(Game $game, $weaponName) {
        $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
        $attachments = Input::except('_token');
        //return var_dump($attachments);
        // keep going down the subsets of attachments until you reach the loadouts that only have those attachments
        //$attachment_loadouts = DB::table('attachment_loadout') -> all();
        //return var_dump($attachment_loadouts);
        //foreach ($attachments as $attachment) {

        //}
        // then check each of those loadouts to find if they have the right weapon

        try {
            $loadout = new Loadout;
            $loadout -> weapon_id = $weapon -> id;
            $loadout -> save();

            foreach ($attachments as $attachment) {
                $attachment = Attachment::findOrFail($attachment);
                $loadout -> attachments() -> save($attachment);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to create new loadout.',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::back() -> with(array(
            'alert' => 'Loadout has been successfully created.',
            'alert-class' => 'alert-success'
        ));
    }

}
