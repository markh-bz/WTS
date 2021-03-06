<?php

use ICanBoogie\CLDR\Provider,
    ICanBoogie\CLDR\RunTimeCache,
    ICanBoogie\CLDR\FileCache,
    ICanBoogie\CLDR\Retriever,
    ICanBoogie\CLDR\Repository,
    ICanBoogie\CLDR\LocalizedDateTime,
    ICanBoogie\DateTime;

class UserController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Displays the form for account creation
     *
     */
    public function create($data = false)
    {
        $providers = Config::Get('wts.providers');
        $view = array('providers' => $providers);
        $this->theme->asset()->container('header')->add('webicon_css', 'themes/babelzilla/assets/css/webicons.css');
        $this->theme->breadcrumb()->add(array(
            array(
                'label' => 'Home',
                'url' => '/'
            ),
            array(
                'label' => 'Sign Up',
            )
        ));
        $this->theme->setTitle('Sign up');
        return $this->theme->of(Config::get('confide::signup_form'), $view)->render();
    }

    /**
     * Stores new account
     *
     */
    public function store()
    {
        $user = new User;

        $user->username = Input::get('username');
        $user->email = Input::get('email');
        $user->password = Input::get('password');


        // The password confirmation will be removed from model
        // before saving. This field will be used in Ardent's
        // auto validation.
        $user->password_confirmation = Input::get('password_confirmation');

        // Save if valid. Password field will be hashed before save
        $user->save();

        if ($user->id) {
            $notice = Lang::get('confide::confide.alerts.account_created') . ' ' . Lang::get('confide::confide.alerts.instructions_sent');

            // Redirect with success message, You may replace "Lang::get(..." for your custom message.
            return Redirect::action('UserController@login')
                ->with('notice', $notice);
        } else {
            // Get validation errors (see Ardent package)
            $error = $user->errors()->all(':message');

            return Redirect::action('UserController@create')
                ->withInput(Input::except('password'))
                ->with('error', $error);
        }
    }

    /**
     * Displays the login form
     *
     */
    public function login()
    {
        if (Confide::user()) {
            // If user is logged, redirect to internal 
            // page, change it to '/admin', '/dashboard' or something
            return Redirect::to('/');
        } else {
            //return View::make(Config::get('confide::login_form'));
            $providers = Config::Get('wts.providers');
            $view = array('providers' => $providers);
            $this->theme->asset()->container('header')->add('webicon_css', 'themes/babelzilla/assets/css/webicons.css');
            $this->theme->breadcrumb()->add(array(
                array(
                    'label' => 'Home',
                    'url' => '/'
                ),
                array(
                    'label' => 'Login',
                )
            ));
            $this->theme->setTitle('Login');
            return $this->theme->of(Config::get('confide::login_form'), $view)->render();
        }
    }

    /**
     * Attempt to do login
     *
     */
    public function do_login()
    {
        $input = array(
            'email' => Input::get('email'), // May be the username too
            'username' => Input::get('email'), // so we have to pass both
            'password' => Input::get('password'),
            'remember' => Input::get('remember'),
        );

        // If you wish to only allow login from confirmed users, call logAttempt
        // with the second parameter as true.
        // logAttempt will check if the 'email' perhaps is the username.
        // Get the value from the config file instead of changing the controller
        if (Confide::logAttempt($input, Config::get('confide::signup_confirm'))) {
            // Redirect the user to the URL they were trying to access before
            // caught by the authentication filter IE Redirect::guest('user/login').
            // Otherwise fallback to '/'
            // Fix pull #145
            // If the session 'loginRedirect' is set, then redirect
            // to that route. Otherwise redirect to '/'
            $r = Session::get('loginRedirect');
            if (!empty($r)) {
                Session::forget('loginRedirect');
                return Redirect::to($r);
            }
            return Redirect::intended('/'); // change it to '/admin', '/dashboard' or something
        } else {
            $user = new User;

            // Check if there was too many login attempts
            if (Confide::isThrottled($input)) {
                $err_msg = Lang::get('confide::confide.alerts.too_many_attempts');
            } elseif ($user->checkUserExists($input) and !$user->isConfirmed($input)) {
                $err_msg = Lang::get('confide::confide.alerts.not_confirmed');
            } else {
                $err_msg = Lang::get('confide::confide.alerts.wrong_credentials');
            }

            return Redirect::action('UserController@login')
                ->withInput(Input::except('password'))
                ->with('error', $err_msg);
        }
    }

    /**
     * Attempt to confirm account with code
     *
     * @param  string $code
     */
    public function confirm($code)
    {
        if (Confide::confirm($code)) {
            $notice_msg = Lang::get('confide::confide.alerts.confirmation');
            return Redirect::action('UserController@login')
                ->with('notice', $notice_msg);
        } else {
            $error_msg = Lang::get('confide::confide.alerts.wrong_confirmation');
            return Redirect::action('UserController@login')
                ->with('error', $error_msg);
        }
    }

    /**
     * Displays the forgot password form
     *
     */
    public function forgot_password()
    {
        $view = array();
        $this->theme->breadcrumb()->add(array(
            array(
                'label' => 'Home',
                'url' => '/'
            ),
            array(
                'label' => 'Forgot password',
            )
        ));
        $this->theme->setTitle('Forgot password');
        return $this->theme->of(Config::get('confide::forgot_password_form'), $view)->render();
    }

    /**
     * Attempt to send change password link to the given email
     *
     */
    public function do_forgot_password()
    {
        if (Confide::forgotPassword(Input::get('email'))) {
            $notice_msg = Lang::get('confide::confide.alerts.password_forgot');
            return Redirect::action('UserController@login')
                ->with('notice', $notice_msg);
        } else {
            $error_msg = Lang::get('confide::confide.alerts.wrong_password_forgot');
            return Redirect::action('UserController@forgot_password')
                ->withInput()
                ->with('error', $error_msg);
        }
    }

    /**
     * Shows the change password form with the given token
     *
     */
    public function reset_password($token)
    {
        $view = array('token' => $token);
        $this->theme->breadcrumb()->add(array(
            array(
                'label' => 'Home',
                'url' => '/'
            ),
            'label' => Trans('user.resetpassword'),
        ));
        $this->theme->setTitle(Trans('user.resetpassword'));
        return $this->theme->of(Config::get('confide::reset_password_form'), $view)->render();
    }

    /**
     * Attempt change password of the user
     *
     */
    public function do_reset_password()
    {
        $input = array(
            'token' => Input::get('token'),
            'password' => Input::get('password'),
            'password_confirmation' => Input::get('password_confirmation'),
        );

        // By passing an array with the token, password and confirmation
        if (Confide::resetPassword($input)) {
            $notice_msg = Lang::get('confide::confide.alerts.password_reset');
            return Redirect::action('UserController@login')
                ->with('notice', $notice_msg);
        } else {
            $error_msg = Lang::get('confide::confide.alerts.wrong_password_reset');
            return Redirect::action('UserController@reset_password', array('token' => $input['token']))
                ->withInput()
                ->with('error', $error_msg);
        }
    }

    /**
     * Log the user out of the application.
     *
     */
    public function logout()
    {
        Confide::logout();

        return Redirect::to('/');
    }


    public function settings()
    {

        $this->theme->breadcrumb()->add(array(
            array(
                'label' => 'Home',
                'url' => '/'
            ),
            array('label' => Trans('user.yoursettings'))
        ));
        $user = $this->user;
        $timezoneselect = WtsHelper::getHtmlTimeZoneDropDown($this->usersettings['timezone']);
        $langselect = WtsHelper::getHtmlLangAvailDropDown($this->usersettings['locale']);
        $dateselect = WtsHelper::getHtmlDateSelectDropDown($this->usersettings['dateformat']);
        $licenseselect = WtsHelper::getHtmlLicenseDropdown($this->usersettings['license']);

        $view = array('timezoneselect' => $timezoneselect,
            'langselect' => $langselect,
            'dateselect' => $dateselect,
            'licenseselect' => $licenseselect,
        );
        $this->theme->asset()->container('footer')->add('usettings_js', 'themes/babelzilla/assets/js/usersettings.js');
        return $this->theme->of('user.settings', $view)->render();
    }

    public function profile($id = Null)
    {
        if (!$id) $id = Auth::user()->id;
        $user = User::find($id);
        if (!$user) App::abort(404, 'The requested user does not exist.');

        $profile = $user->Profile()->get();

        $projects = $user->Project()->count();
        $translations = $user->Translations()->count();
        $langs = $user->Languages()->get()->all();
        $provider = new Provider
        (
            new RunTimeCache(new FileCache(app_path() . '/cldr_cache')),
            new Retriever
        );
        $repository = new Repository($provider);
        $locale = $repository->locales[App::getLocale()];
        //print_r($locale['languages']);
        foreach ($langs as $language) {
            $languages[] = array('language' => $locale['languages'][$language->language],
                'level' => $language->level);
        }
        $view = array('user' => $user,
            'profile' => $profile,
            'projects' => $projects,
            'translations' => $translations,
            'languages' => $languages,
        );
        $this->theme->breadcrumb()->add(array(
            array(
                'label' => 'Home',
                'url' => '/'
            ),
            array('label' => Trans('user.profile'))
        ));

        $this->theme->setTitle(Trans('user.profileview'));
        return $this->theme->of('user.profile', $view)->render();
    }
}
