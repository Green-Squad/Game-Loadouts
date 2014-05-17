<?php

class AttachmentController extends BaseController {

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Game $game) {
        return View::make('admin.attachment.create', compact('game'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Game $game) {
        $name = Input::get('name');
        $slot = Input::get('slot');
        $image = Input::file('image');
        $game_id = $game -> id;

        if (Input::hasFile('image')) {
            $destinationPath = public_path() . '/uploads/';
            $thumbPath = public_path() . '/uploads/thumb/';
            $fileExtension = $image -> getClientOriginalExtension();
            $fileName = $game -> id . '-' . $name . '-' . $slot . '.' . $fileExtension;
            $fileName = preg_replace('/[\[\]]/', '', $fileName);
            $fileName = urlencode($fileName);
            
            $image -> move($destinationPath, $fileName);

            copy($destinationPath . $fileName, $thumbPath . $fileName);
            HelperController::createThumbnail($thumbPath . $fileName, $fileExtension, 64);
        } else {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to upload image',
                'alert-class' => 'alert-danger'
            ));
        }

        try {
            $attachment = new Attachment;
            $attachment -> name = $name;
            $attachment -> slot = $slot;
            $attachment -> game_id = $game_id;
            $attachment -> image_url = "uploads/$fileName";
            $attachment -> thumb_url = "uploads/thumb/$fileName";
            $attachment -> save();
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to create new attachment',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::route('admin.game.show', array('game' => $game_id)) -> with(array(
            'alert' => 'Attachment has been successfully created.',
            'alert-class' => 'alert-success'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Game $game, $attachmentName) {
        $attachment = Attachment::where('game_id', $game -> id, 'AND') -> where('name', $attachmentName) -> first();
        return View::make('admin.attachment.edit', compact('game', 'attachment'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Game $game, $attachmentID) {
        $name = Input::get('name');
        $slot = Input::get('slot');
        $image = Input::file('image');

        try {
            $attachment = Attachment::findOrFail($attachmentID);
            $attachment -> name = $name;
            $attachment -> slot = $slot;

            if (Input::hasFile('image')) {
                $destinationPath = public_path() . '/uploads/';
                $thumbPath = public_path() . '/uploads/thumb/';
                $fileExtension = $image -> getClientOriginalExtension();
                $fileName = $game -> id . '-' . $name . '-' . $slot . '.' . $fileExtension;
                $fileName = preg_replace('/[\[\]]/', '', $fileName);
                $fileName = urlencode($fileName);
                
                $image -> move($destinationPath, $fileName);

                copy($destinationPath . $fileName, $thumbPath . $fileName);
                HelperController::createThumbnail($thumbPath . $fileName, $fileExtension, 64);

                $attachment -> image_url = "uploads/$fileName";
                $attachment -> thumb_url = "uploads/thumb/$fileName";
            }

            $attachment -> save();
        } catch (\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to update attachment',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::route('admin.game.show', $game -> id) -> with(array(
            'alert' => 'Attachment has been successfully updated.',
            'alert-class' => 'alert-success'
        ));
    }

    /**
     * Show the form for deleting the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete(Game $game, $attachmentName) {
        $attachment = Attachment::where('game_id', $game -> id, 'AND') -> where('name', $attachmentName) -> first();
        return View::make('admin.attachment.delete', compact('game', 'attachment'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Game $game, $attachmentID) {
        try {
            $attachment = Attachment::findOrFail($attachmentID);
            
            $loadouts = $attachment -> loadouts;
            
            foreach($loadouts as $loadout) {
                $loadout -> delete();
            }
                        
            $attachmentName = $attachment -> name;
            $attachment -> delete();
        } catch(\Illuminate\Database\QueryException $e) {
            return Redirect::back() -> with(array(
                'alert' => 'Error: Failed to delete attachment.',
                'alert-class' => 'alert-danger'
            ));
        }
        return Redirect::route('admin.game.show', $game -> id) -> with(array(
            'alert' => "You have successfully deleted attachment $attachmentName.",
            'alert-class' => 'alert-success'
        ));
    }

}
