<?php
class LoadoutController extends BaseController {

    public function store(Game $game, $weaponName) {
        
        if (Auth::guest()) {
            $this -> validateGuest();     
        }

        if (Auth::check()) {
            if (Auth::user() -> role == 'Guest') {
                $response = $this -> captchaCheck();
                if (!$response -> isValid()) {
                    // return with error
                    return Redirect::back() -> with(array (
                        'alert' => 'Error: Incorrect CAPTCHA. Please try again.',
                        'alert-class' => 'alert-danger'
                    ));
                }
            }
            $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
            $attachments = Input::except('_token', 'recaptcha_challenge_field', 'recaptcha_response_field');
            
            $weapon_loadouts = Loadout::where('weapon_id', $weapon -> id) -> get();
            
            if (count($attachments) >= $weapon -> min_attachments && count($attachments) <= $weapon -> max_attachments) {
                // for each attachment
                // if it isnt in possible attachments
                // return error message
                $possibleAttachments = $weapon -> attachments;
                $possibleAttachments = $this -> getAttachmentsArray($possibleAttachments);
                foreach ( $attachments as $attachment ) {
                    if (! in_array($attachment, $possibleAttachments)) {
                        return Redirect::back() -> with(array (
                            'alert' => 'This is not a valid loadout. Good attempt at hacking us though.',
                            'alert-class' => 'alert-danger' 
                        ));
                    }
                }
                
                // for each attachment in slot
                // if it isnt in possible attachments for slot
                // return error message
                $attachmentsBySlot = WeaponController::getAttachmentsBySlot($weapon);
                foreach ( $attachments as $key => $attachment ) {
                    // return var_dump($attachment) . var_dump($this -> getAttachmentsArray($attachmentsBySlot[$key]));
                    if (! in_array($attachment, $this -> getAttachmentsArray($attachmentsBySlot [$key]))) {
                        return Redirect::back() -> with(array (
                            'alert' => 'This is not a valid loadout. Good attempt at hacking us though.',
                            'alert-class' => 'alert-danger' 
                        ));
                    }
                }
                
                // all loadouts for specific weapon
                $existingLoadout = NULL;
                
                foreach ( $weapon_loadouts as $weapon_loadout ) {
                    
                    $weapon_loadout_attachments = $this -> getAttachmentsArray($weapon_loadout -> attachmentIDs -> toArray());
                    
                    if ($this -> isExistingLoadout($attachments, $weapon_loadout_attachments)) {
                        // //we have found the matching loadout
                        // we need to save that loadout and break out of this loop
                        $existingLoadout = $weapon_loadout;
                        break;
                    }
                }
                if ($existingLoadout) {
                    // we save the submission loadout user
                    
                    $user = Auth::user();
                    if ($this -> userHasLoadout($user -> loadouts, $existingLoadout -> id)) {
                        return Redirect::back() -> with(array (
                            'alert' => 'You have already submitted this loadout.',
                            'alert-class' => 'alert-warning' 
                        ));
                    } else {
                        $user -> loadouts() -> save($existingLoadout);
                        $user -> save();
                        return Redirect::route('showLoadout', array (
                            $game -> id,
                            $weapon -> name,
                            $existingLoadout -> id 
                        )) -> with(array (
                            'alert' => 'Your vote for an existing loadout has been recorded.',
                            'alert-class' => 'alert-success' 
                        ));
                    }
                } else {
                    try {
                        
                        $loadout = new Loadout();
                        $loadout -> weapon_id = $weapon -> id;
                        $loadout -> save();
                        
                        foreach ( $attachments as $attachment ) {
                            $attachment = Attachment::findOrFail($attachment);
                            $loadout -> attachments() -> save($attachment);
                        }
                        
                        $user = Auth::user();
                        $user -> loadouts() -> save($loadout);
                        $user -> save();
                    } catch ( \Illuminate\Database\QueryException $e ) {
                        return Redirect::back() -> with(array (
                            'alert' => 'Error: Failed to create new loadout.',
                            'alert-class' => 'alert-danger' 
                        ));
                    }
                }
                return Redirect::route('showLoadout', array (
                    $game -> id,
                    $weapon -> name,
                    $loadout -> id 
                )) -> with(array (
                    'alert' => 'Loadout has been successfully created.',
                    'alert-class' => 'alert-success' 
                ));
            } elseif (count($attachments) <= $weapon -> min_attachments) {
                return Redirect::back() -> with(array (
                    'alert' => 'You have submitted too few attachments.',
                    'alert-class' => 'alert-danger' 
                ));
            } else {
                return Redirect::back() -> with(array (
                    'alert' => 'You have submitted too many attachments.',
                    'alert-class' => 'alert-danger' 
                ));
            }
        } else {
            return Redirect::back() -> with(array (
                'alert' => 'You must be logged in to do that.',
                'alert-class' => 'alert-danger' 
            ));
        }
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
        $attachmentsArray = array ();
        foreach ( $attachments as $attachment ) {
            array_push($attachmentsArray, $attachment ['id']);
        }
        return $attachmentsArray;
    }
    public static function userHasLoadout($loadouts, $id) {
        foreach ( $loadouts as $loadout ) {
            if ($loadout ['id'] == $id) {
                return true;
            }
        }
        return false;
    }
    public static function countSubmissions($loadout_id) {
        return DB::table('loadout_user') -> where('loadout_id', $loadout_id) -> count();
    }
    public function show(Game $game, $weaponName, Loadout $loadout) {
        $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
        $loadout -> count = LoadoutController::countSubmissions($loadout -> id);
        
        if (Auth::check()) {
            $user = Auth::user();
            if (LoadoutController::userHasLoadout($user -> loadouts, $loadout -> id)) {
                $loadout -> upvoted = 1;
            } else {
                $loadout -> upvoted = 0;
            }
        } else {
            $loadout -> upvoted = 0;
        }
        
        return View::make('loadout', compact('loadout', 'game', 'weapon'));
    }
    public function show2(Game $game, $weaponName, Loadout $loadout) {
        $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
        $loadout -> count = LoadoutController::countSubmissions($loadout -> id);
        
        if (Auth::check()) {
            $user = Auth::user();
            if (LoadoutController::userHasLoadout($user -> loadouts, $loadout -> id)) {
                $loadout -> upvoted = 1;
            } else {
                $loadout -> upvoted = 0;
            }
        } else {
            $loadout -> upvoted = 0;
        }
        
        return View::make('loadout2', compact('loadout', 'game', 'weapon'));
    }
    public function upvote(Game $game, $weaponName, Loadout $loadout) {
        if (Auth::check()) {
            $user = Auth::user();
            if (LoadoutController::userHasLoadout($user -> loadouts, $loadout -> id)) {
                $response = array (
                    'success' => 0 
                );
                return Response::json($response);
            } else {
                $user -> loadouts() -> save($loadout);
                $user -> save();
                $response = array (
                    'success' => 1 
                );
                return Response::json($response);
            }
        } else {
            $response = array (
                'success' => 0 
            );
            return Response::json($response);
        }
    }
    public function upvoteGuest(Game $game, $weaponName, Loadout $loadout) {

        $this -> validateGuest();     
        $user = Auth::user();
        
        if (LoadoutController::userHasLoadout($user -> loadouts, $loadout -> id)) {
            $user -> loadouts() -> detach($loadout);
            $user -> save();
            // return removed your vote
            return Redirect::back() -> with(array (
                'alert' => 'You have successfully removed your vote.',
                'alert-class' => 'alert-success' 
            ));
        }
        
        $response = $this -> captchaCheck();
        
        if ($response -> isValid()) {
            
            if (! LoadoutController::userHasLoadout($user -> loadouts, $loadout -> id)) {
                $user -> loadouts() -> save($loadout);
                $user -> save();
                // return saved your vote
                return Redirect::back() -> with(array (
                    'alert' => 'You have successfully voted.',
                    'alert-class' => 'alert-success' 
                ));
            }
        } else {
            // return with error
            return Redirect::back() -> with(array (
                'alert' => 'Error: Incorrect CAPTCHA. Please try again.',
                'alert-class' => 'alert-danger' 
            ));
        }
    }
    
    public function captchaCheck() {
        $captcha = new Captcha\Captcha();
        $captcha -> setPublicKey('6Lf9-_YSAAAAAJ9k2G_qXohJi74-pDe8V4NuUdzJ');
        $captcha -> setPrivateKey($_ENV ['RECAPTCHA_PRIVATE_KEY']);
        
        $recaptcha_challenge = Input::get('recaptcha_challenge_field');
        $recaptcha_response = Input::get('recaptcha_response_field');
        
        return $captcha -> check($recaptcha_challenge, $recaptcha_response);
    }
    
    public function validateGuest()
    {
        if (Auth::guest()) {
            // make account
        
            $newUserId = str_random(128);
            // checks for duplicate user
            while ( User::find($newUserId) ) {
                $newUserId = str_random(128);
            }
        
            $user = new User();
            $user -> email = $newUserId;
            $user -> username = $newUserId;
            $user -> password = Hash::make(str_random(64));
            $user -> role = 'Guest';
            $user -> disabled_until = date('Y-m-d H:i:s');
            $user -> failed_attempts = 0;
            $user -> confirm_token = 1;
            $user -> save();
        
            // WTF LARAVEL WHY DO I NEED TO FIND IT AGAIN
            $user = User::findOrFail($newUserId);
            // log them in and remember
            Auth::login($user, true);
            $user = Auth::user();
        } else {
            // validate that they are a guest
            $user = Auth::user();
            if ($user -> role != 'Guest') {
                // return error
                return Redirect::back() -> with(array (
                    'alert' => 'Error: You tried to vote as a guest, but you are not using a guest account.',
                    'alert-class' => 'alert-danger'
                ));
            }
        }
    }
    
    public function detach(Game $game, $weaponName, Loadout $loadout) {
        if (Auth::check()) {
            $user = Auth::user();
            if (LoadoutController::userHasLoadout($user -> loadouts, $loadout -> id)) {
                $user -> loadouts() -> detach($loadout);
                $user -> save();
                $response = array (
                    'success' => 1 
                );
                return Response::json($response);
            } else {
                $response = array (
                    'success' => 0 
                );
                return Response::json($response);
            }
        } else {
            $response = array (
                'success' => 0 
            );
            return Response::json($response);
        }
    }
    public function showDelete(Game $game, $weaponName, Loadout $loadout) {
        $weapon = Weapon::where('game_id', $game -> id, 'AND') -> where('name', $weaponName) -> first();
        return View::make('admin.loadout.delete', compact('game', 'weapon', 'loadout'));
    }
    public function delete(Game $game, $weaponName, Loadout $loadout) {
        try {
            $loadout -> delete();
            return Redirect::route('weaponLoadouts', array (
                'id' => $game -> id,
                'name' => $weaponName 
            )) -> with(array (
                'alert' => 'Successfully deleted loadout.',
                'alert-class' => 'alert-success' 
            ));
        } catch ( \Illuminate\Database\QueryException $e ) {
            return Redirect::back() -> with(array (
                'alert' => 'Error: Failed to delete loadout.',
                'alert-class' => 'alert-danger' 
            ));
        }
    }
}
