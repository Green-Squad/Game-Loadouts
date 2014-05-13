<?php

class WeaponController extends BaseController {

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Game $game) {
        $attachments = Attachment::where('game_id', $game -> id) -> orderBy('name') -> get();

        $attachmentsBySlot = array();
        foreach ($attachments as $attachment) {
            $slot = $attachment -> slot;
            if (isset($attachmentsBySlot[$slot])) {
                array_push($attachmentsBySlot[$slot], $attachment);
            } else {
                $attachmentsBySlot[$slot] = array($attachment);
            }
        }

        ksort($attachmentsBySlot);

        return View::make('admin.weapon.create', compact('game', 'attachmentsBySlot'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Game $game) {
        $name = Input::get('name');
        $image = Input::file('image');
        $attachments = Input::get('attachments');
        $game_id = $game -> id;

        if (Input::hasFile('image')) {
            $destinationPath = public_path() . '/uploads/';
            $thumbPath = public_path() . '/uploads/thumb/';

            $fileExtension = $image -> getClientOriginalExtension();
            $fileName = $game -> id . '-' . $name . '.' . $fileExtension;

            $image -> move($destinationPath, $fileName);

            copy($destinationPath . $fileName, $thumbPath . $fileName);
            HelperController::createThumbnail($thumbPath . $fileName, $fileExtension, 128);
        } else {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to upload image',
                'alert-class' => 'alert-danger'
            ));
        }

        try {
            $weapon = new Weapon;
            $weapon -> name = $name;
            $weapon -> game_id = $game_id;
            $weapon -> image_url = "uploads/$fileName";
            $weapon -> thumb_url = "uploads/thumb/$fileName";
            $weapon -> save();

            foreach ($attachments as $attachment) {
                $attachment = Attachment::findOrFail($attachment);
                $weapon -> attachments() -> save($attachment);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to create new weapon',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::route('admin.game.show', array('game' => $game_id)) -> with(array(
            'alert' => 'Weapon has been successfully created.',
            'alert-class' => 'alert-success'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Game $game, $weaponName) {
        $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
        $attachments = Attachment::where('game_id', $game -> id) -> orderBy('name') -> get();

        $attachmentsBySlot = array();
        foreach ($attachments as $attachment) {
            $slot = $attachment -> slot;

            $active = DB::table('attachment_weapon') -> where('attachment_id', $attachment -> id, 'AND') -> where('weapon_id', $weapon -> id) -> get();

            if ($active) {
                $attachment -> checked = "active";
            } else {
                $attachment -> checked = "";
            }
            if (isset($attachmentsBySlot[$slot])) {
                array_push($attachmentsBySlot[$slot], $attachment);
            } else {
                $attachmentsBySlot[$slot] = array($attachment);
            }
        }

        ksort($attachmentsBySlot);

        return View::make('admin.weapon.edit', compact('game', 'weapon', 'attachmentsBySlot'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Game $game, $weaponID) {
        $name = Input::get('name');
        $attachments = Input::get('attachments');
        $image = Input::file('image');

        try {
            $weapon = Weapon::findOrFail($weaponID);
            $weapon -> attachments() -> detach();
            $weapon -> name = $name;

            if (Input::hasFile('image')) {
                $destinationPath = public_path() . '/uploads/';
                $thumbPath = public_path() . '/uploads/thumb/';
                $fileExtension = $image -> getClientOriginalExtension();
                $fileName = $game -> id . '-' . $name . '.' . $fileExtension;
                $image -> move($destinationPath, $fileName);

                copy($destinationPath . $fileName, $thumbPath . $fileName);
                HelperController::createThumbnail($thumbPath . $fileName, $fileExtension, 128);

                $weapon -> thumb_url = "uploads/thumb/$fileName";
                $weapon -> image_url = "uploads/$fileName";
            }

            $weapon -> save();

            foreach ($attachments as $attachment) {
                $attachment = Attachment::findOrFail($attachment);
                $weapon -> attachments() -> save($attachment);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to update weapon',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::route('admin.game.show', $game -> id) -> with(array(
            'alert' => 'Weapon has been successfully updated.',
            'alert-class' => 'alert-success'
        ));
    }

    /**
     * Show the form for deleting the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete(Game $game, $weaponName) {
        $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
        return View::make('admin.weapon.delete', compact('game', 'weapon'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Game $game, $weaponID) {
        try {
            $weapon = Weapon::findOrFail($weaponID);
            $weaponName = $weapon -> name;
            $weapon -> delete();
        } catch(\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to delete weapon.',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::route('admin.game.show', $game -> id) -> with(array(
            'alert' => "You have successfully deleted weapon $weaponName.",
            'alert-class' => 'alert-success'
        ));
    }

    public static function listLoadouts(Game $game, $weaponName) {

        $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
        $loadouts = Loadout::where('weapon_id', $weapon -> id) -> get();

        foreach ($loadouts as $loadout) {
            $loadout -> count = LoadoutController::countSubmissions($loadout -> id);
            if (Auth::check() && LoadoutController::userHasLoadout(Auth::user() -> loadouts, $loadout -> id)) {
                $loadout -> upvoted = 1;
            } else {
                $loadout -> upvoted = 0;
            }
            //return var_dump(DB::table('loadout_user') -> where('user_id', Auth::user() -> id, 'AND') -> where('loadout_id', $loadout -> id) -> get());
        }

        $loadouts = $loadouts -> toArray();
        uasort($loadouts, "WeaponController::countSort");
        // sort by Loadout submission count

        $attachments = $weapon -> attachments -> sortBy('name');

        $attachmentsBySlot = array();
        foreach ($attachments as $attachment) {
            $slot = $attachment -> slot;
            if (isset($attachmentsBySlot[$slot])) {
                array_push($attachmentsBySlot[$slot], $attachment);
            } else {
                $attachmentsBySlot[$slot] = array($attachment);
            }
        }
        ksort($attachmentsBySlot);

        return View::make('weapon', compact('game', 'weapon', 'loadouts', 'attachmentsBySlot'));
    }

    public static function countSort($a, $b) {
        return $b['count'] - $a['count'];
    }

}
