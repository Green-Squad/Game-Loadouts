<?php

class LoadoutController extends BaseController {

    public function store(Game $game, $weaponName) {
        $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
        $attachments = Input::except('_token');

        $attachment_loadouts = DB::select('SELECT attachment_loadout.loadout_id, attachment_loadout.attachment_id
                                            FROM loadouts
                                            LEFT JOIN attachment_loadout
                                            ON loadouts.id = attachment_loadout.loadout_id
                                            
                                            UNION
                                            SELECT attachment_loadout.loadout_id, attachment_loadout.attachment_id
                                            FROM attachment_loadout
                                            LEFT JOIN loadouts
                                            ON loadouts.id = attachment_loadout.loadout_id;');

        /*
         * Above SQL outputs...
         * loadout_id   attachment_id
         * 1            3
         * 1            2
         * 2            7
         * 2            1
         * 3            3
         * 3            12
         */

        // loop through the array until you find a loadout_id that has all of the $attachments
        // then check to see if that loadout_id matches the correct weapon (perhaps we limit our search to this weapon first)

        // this code might be going somewhere or it could be way off.......
        //for ($i = 0; i < count($attachment_loadouts); $i+=count($attachments)) {
        //    foreach ($attachments as $key => $value) {
        //        $someBooleanVar = FALSE;
        //        for ($j = 0; $j < count($attachments); $j++) {
        //           if ($attachment_loadouts[$i + $j] == $attachments[$j])
        //               $someBooleanVar = TRUE; // WE FOUND ONE! BUT WE NEED TO FIND THEM ALL....
        //        }
        //    }
        //}

        // NEW IDEA! Turn SQL output into sexy array. It will look something like... (maybe sort value array)
        // array[1] = array(3,2)
        // array[2] = array(7,1)
        // array[3] = array(3,12)
        // Where the key is the loadout_id and the value is an array of the attachment_ids

        // NEWER IDEA!
        $weapon_loadouts = Loadout::where('weapon_id', $weapon -> id) -> get();
        
        // all loadouts for specific weapon
        $existingLoadout = NULL;

        foreach ($weapon_loadouts as $weapon_loadout) {

            $weapon_loadout_attachments = $this -> getAttachmentsArray($weapon_loadout -> attachmentIDs -> toArray());
            $boolean = $this -> isExistingLoadout($attachments, $weapon_loadout_attachments);
            if ($boolean) {
                ////we have found the matching loadout
                //we need to save that loadout and break out of this loop
                $existingLoadout = $weapon_loadout;
                break;
            }
        }
        if ($existingLoadout) {
            // we save the submission loadout user
        } else {
            try {
                $loadout = new Loadout;
                $loadout -> weapon_id = $weapon -> id;
                $loadout -> save();

                foreach ($attachments as $attachment) {
                    $attachment = Attachment::findOrFail($attachment);
                    $loadout -> attachments() -> save($attachment);
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

}
