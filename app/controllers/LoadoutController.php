<?php

class LoadoutController extends BaseController {

    public function store(Game $game, $weaponName) {
        $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
        $attachments = Input::except('_token');

        $weapon_loadouts = Loadout::where('weapon_id', $weapon -> id) -> get();

        // all loadouts for specific weapon
        $existingLoadout = NULL;

        foreach ($weapon_loadouts as $weapon_loadout) {

            $weapon_loadout_attachments = $this -> getAttachmentsArray($weapon_loadout -> attachmentIDs -> toArray());

            if ($this -> isExistingLoadout($attachments, $weapon_loadout_attachments)) {
                ////we have found the matching loadout
                //we need to save that loadout and break out of this loop
                $existingLoadout = $weapon_loadout;
                break;
            }
        }
        if ($existingLoadout) {
            // we save the submission loadout user
            if (Auth::check()) {
                $user = Auth::user();
                if ($this -> userHasLoadout($user -> loadouts, $existingLoadout -> id)) {
                    return Redirect::back() -> with(array('alert' => 'You have already submitted this loadout.', 'alert-class' => 'alert-warning'));
                } else {
                    $user -> loadouts() -> save($existingLoadout);
                    $user -> save();
                    return Redirect::back() -> with(array('alert' => 'Your vote for an existing loadout has been recorded.', 'alert-class' => 'alert-success'));
                }
            }
        } else {
            try {
                if (Auth::check()) {

                    $loadout = new Loadout;
                    $loadout -> weapon_id = $weapon -> id;
                    $loadout -> save();

                    foreach ($attachments as $attachment) {
                        $attachment = Attachment::findOrFail($attachment);
                        $loadout -> attachments() -> save($attachment);
                    }

                    $user = Auth::user();
                    $user -> loadouts() -> save($loadout);
                    $user -> save();
                }
            } catch (\Illuminate\Database\QueryException $e) {
                return Redirect::back() -> with(array('alert' => 'Error: Failed to create new loadout.', 'alert-class' => 'alert-danger'));
            }
        }
        return Redirect::back() -> with(array('alert' => 'Loadout has been successfully created.', 'alert-class' => 'alert-success'));

    }

    // $inputAttachments are attachments given by Input from form
    // $databaseAttachments are the existing attachments in the database
    public function isExistingLoadout($inputAttachments, $databaseAttachments) {

        $array1 = array_diff($inputAttachments, $databaseAttachments);
        $array2 = array_diff($databaseAttachments, $inputAttachments);

        if (empty($array1) && empty($array2)) {
            return true;
        } else {
            return false;
        }
    }

    public function getAttachmentsArray($attachments) {
        $attachmentsArray = array();
        foreach ($attachments as $attachment) {
            array_push($attachmentsArray, $attachment['id']);
        }
        return $attachmentsArray;
    }

    public static function userHasLoadout($loadouts, $id) {
        foreach ($loadouts as $loadout) {
            if ($loadout['id'] == $id) {
                return true;
            }
        }
        return false;
    }

    public static function countSubmissions($loadout_id) {
        return DB::table('loadout_user') -> where('loadout_id', $loadout_id) -> count();
    }

    public function show(Game $game, $weaponName, Loadout $loadout) {
        return View::make('loadout', compact('loadout'));
    }

    public function upvote(Game $game, $weaponName, Loadout $loadout) {
        if (Auth::check()) {
            $user = Auth::user();
            if (LoadoutController::userHasLoadout($user -> loadouts, $loadout -> id)) {
                return Redirect::back() -> with(array('alert' => 'You have already submitted this loadout.', 'alert-class' => 'alert-warning'));
            } else {
                $user -> loadouts() -> save($loadout);
                $user -> save();
                return Redirect::back() -> with(array('alert' => 'Your vote for an existing loadout has been recorded.', 'alert-class' => 'alert-success'));
            }
        }
    }

}
