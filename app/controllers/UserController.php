<?php
/*
 * This is the controller for the User model.
 */
use Illuminate\Database\Eloquent\ModelNotFoundException;
class UserController extends BaseController {
    
    // Attempt to login a user
    public function login() {
        try {
            $foundUser = User::where('email', Input::get('emailOrUsername')) -> orWhere('username', Input::get('emailOrUsername')) -> first();
            if ($foundUser != NULL) {
                
                $failedAttempts = $foundUser -> failed_attempts;
                $disabledUntil = strtotime($foundUser -> disabled_until);
                $user = array (
                    'email' => $foundUser -> email,
                    'password' => Input::get('password'),
                    'confirm_token' => 1 
                );
                if (time() >= $disabledUntil) {
                    if (Auth::attempt($user, Input::has('remember'))) {
                        $loggedInUser = Auth::user();
                        $loggedInUser -> failed_attempts = 0;
                        $loggedInUser -> save();
                        return Redirect::intended('/') -> with(array (
                            'alert' => 'You are successfully logged in.',
                            'alert-class' => 'alert-success' 
                        ));
                    } elseif ($foundUser -> confirm_token != 1) {
                        return Redirect::back() -> with(array (
                            'alert' => 'Your email address has not been confirmed. <strong>If you do not see it within 5 minutes, then please check your Spam folder.</strong>',
                            'alert-class' => 'alert-warning' 
                        )) -> withInput();
                    } else {
                        $foundUser -> failed_attempts = $failedAttempts + 1;
                        $foundUser -> save();
                        $failedAttempts = $foundUser -> failed_attempts;
                        if ($failedAttempts >= 5) {
                            $foundUser -> disabled_until = date('Y-m-d H:i:s', time() + (60 * 15));
                            $foundUser -> failed_attempts = 0;
                            $foundUser -> save();
                            $disabledUntil = strtotime($foundUser -> disabled_until);
                            return Redirect::back() -> with(array (
                                'alert' => 'Your account is disabled until ' . date("F j, Y, g:i a", $disabledUntil) . ' EST',
                                'alert-class' => 'alert-danger' 
                            ));
                        }
                        return Redirect::back() -> with(array (
                            'alert' => 'Your username/email and password combination was incorrect. You have ' . (5 - $failedAttempts) . ' login attempts remaining.',
                            'alert-class' => 'alert-danger' 
                        )) -> withInput();
                    }
                } else {
                    return Redirect::back() -> with(array (
                        'alert' => 'Your account is disabled until ' . date("F j, Y, g:i a", $disabledUntil) . ' EST',
                        'alert-class' => 'alert-danger' 
                    ));
                }
            } else {
                return Redirect::back() -> with(array (
                    'alert' => 'That username or email is not in our database.',
                    'alert-class' => 'alert-danger' 
                )) -> withInput();
            }
        } catch ( ModelNotFoundException $mnfe ) {
            return Redirect::back() -> with(array (
                'alert' => 'That username or email is not in our database.',
                'alert-class' => 'alert-danger' 
            )) -> withInput();
        }
    }
    
    // Logout a logged in user
    public function logout() {
        Auth::logout();
        return Redirect::route('home') -> with(array (
            'alert' => 'You are successfully logged out.',
            'alert-class' => 'alert-success' 
        ));
    }
    
    // Attempt to register a new user
    
    public function join() {
        $rules = array (
            'username' => 'Required|Between:4,128|AlphaNum|Unique:users',
            'email' => 'Required|Between:4,128|Email|Unique:users',
            'password' => 'Required|Between:4,64|Confirmed',
            'password_confirmation' => 'Required|Between:4,64' 
        );
        
        $v = Validator::make(Input::all(), $rules);
        if ($v -> passes()) {
            $username = Input::get('username');
            $email = Input::get('email');
            $password = Input::get('password');
            
            try {
                // Attempt to saved a new user to the database
                $hashed_password = Hash::make($password);
                
                if (Auth::check() && Auth::user() -> role == 'Guest') {
                    $user = Auth::user();
                    $user -> converted_guest = 1;
                } else {
                    $user = new User();
                    $user -> converted_guest = 0;
                }
                $user -> email = $email;
                $user -> username = $username;
                $user -> password = $hashed_password;
                $user -> role = 'Standard';
                $user -> disabled_until = date('Y-m-d H:i:s');
                $user -> failed_attempts = 0;
                $user -> confirm_token = str_random(100);
                $user -> save();
                
                // WTF laravel? Why do I need to find the user I just saved to access the email
                $user = User::findOrFail($email);
                
                $data = array (
                    'token' => $user -> confirm_token,
                    'username' => $user -> username 
                );
                Mail::send('emails.auth.confirm', $data, function ($message) use($user) {
                    $message -> to($user -> email, $user -> username) -> subject('Confirm your email address for Game Loadouts.');
                });
            } catch ( \Illuminate\Database\QueryException $e ) {
                return Redirect::back() -> with(array (
                    'alert' => 'Error: Failed to register user in database.',
                    'alert-class' => 'alert-danger' 
                ));
            }
            
            return Redirect::route('home') -> with(array (
                'alert' => 'Welcome! You have successfully created an account and a confirmation email has been sent to ' . $user -> email . '. Please click the link in the email to confirm your account.',
                'alert-class' => 'alert-success' 
            ));
        } else {
            return Redirect::back() -> withErrors($v);
        }
    }

    public function confirm($token) {
        $user = User::where('confirm_token', $token) -> first();
        if ($user == null) {
            return Redirect::route('home') -> with(array (
                'alert' => 'There are no accounts associated with that confirmation token. They either do not exist or have already been confirmed.',
                'alert-class' => 'alert-danger' 
            ));
        } else {
            $user -> confirm_token = 1;
            $user -> save();
            Auth::login($user);
            return Redirect::route('home') -> with(array (
                'alert' => 'Welcome! You have successfully confirmed your email. You have been logged in.',
                'alert-class' => 'alert-success' 
            ));
        }
    }

    public function create() {
        $username = Input::get('username');
        $email = Input::get('email');
        $password = Input::get('password');
        $role = Input::get('role');
        $hashed_password = Hash::make($password);
        $user = new User();
        $user -> email = $email;
        $user -> username = $username;
        $user -> password = $hashed_password;
        $user -> role = $role;
        $user -> disabled_until = date('Y-m-d H:i:s');
        $user -> failed_attempts = 0;
        $user -> confirm_token = 1;
        $user -> save();
        return Redirect::route('modUsers') -> with(array (
            'alert' => 'Successfully created user',
            'alert-class' => 'alert-success' 
        ));
    }

    public function save(User $user) {
        $email = Input::get('email');
        $username = Input::get('username');
        $password = Input::get('password');
        $role = Input::get('role');
        $disabled_until = Input::get('disabled_until');
        
        $user -> email = $email;
        $user -> username = $username;
        if (! empty($password)) {
            $hashed_pw = Hash::make($password);
            $user -> password = $hashed_pw;
        }
        $user -> role = $role;
        $user -> disabled_until = date('Y-m-d H:i:s', time() + ($disabled_until * 60));
        
        $user -> save();
        return Redirect::route('modUsers') -> with(array (
            'alert' => 'Successfully edited user',
            'alert-class' => 'alert-success' 
        ));
    }

    public function delete(User $user) {
        try {
            $userId = $user -> email;
            $user -> delete();
        } catch ( \Illuminate\Database\QueryException $e ) {
            return Redirect::route('modUsers') -> with(array (
                'alert' => 'Error: Failed to delete user.',
                'alert-class' => 'alert-danger' 
            ));
        }
        return Redirect::route('modUsers') -> with(array (
            'alert' => "You have successfully deleted user $userId.",
            'alert-class' => 'alert-success' 
        ));
    }

    public function listUsers() {
        $users = User::where('role', '!=', 'Guest') -> get() -> reverse();
        return View::make('admin.users', compact('users'));
    }

    public static function userCount() {
        $userCount = User::where('role', '!=', 'Guest') -> count();
        return $userCount;
    }

    public function listGuests() {
        $users = User::where('role', 'Guest') -> get() -> reverse();
        return View::make('admin.users', compact('users'));
    }

    public static function guestCount() {
        $userCount = User::where('role', 'Guest') -> count();
        return $userCount;
    }

    public function listConverted() {
        $users = User::where('converted_guest', '1') -> get() -> reverse();
        return View::make('admin.users', compact('users'));
    }

    public static function convertedCount() {
        $userCount = User::where('converted_guest', '1') -> count();
        return $userCount;
    }

    public static function recentUsers($num) {
        $recentUsers = User::where('role', '!=', 'Guest') -> get() -> sortBy('created_at') -> reverse() -> take($num);
        return $recentUsers;
    }

    public function showAccount() {
        $user = Auth::user();
        return View::make('account', compact('user'));
    }

    public function saveAccount() {
        $user = Auth::user();
        
        $password = Input::get('password');
        $confirm_password = Input::get('confirm_password');
        
        if (! empty($password)) {
            if (strcmp($password, $confirm_password) == 0) {
                $hashed_pw = Hash::make($password);
                $user -> password = $hashed_pw;
                $user -> save();
            } else {
                return Redirect::route('account') -> with(array (
                    'alert' => 'Error: Passwords do not match!',
                    'alert-class' => 'alert-danger' 
                ));
            }
        }
        
        return Redirect::route('home') -> with(array (
            'alert' => 'You have successfully updated your account information.',
            'alert-class' => 'alert-success' 
        ));
    }

    public function showSubmissions() {
        $loadouts = Auth::user() -> loadouts;
        return View::make('submissions', compact('loadouts'));
    }

    public function dashboardSubmissions(User $user) {
        $loadouts = $user -> loadouts;
        return View::make('admin.user.submissions', compact('user', 'loadouts'));
    }
}
