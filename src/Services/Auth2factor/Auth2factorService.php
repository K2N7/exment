<?php

namespace Exceedone\Exment\Services\Auth2factor;

use Exceedone\Exment\Services\MailSender;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\UserSetting;
use Exceedone\Exment\Enums\Login2FactorProviderType;
use Exceedone\Exment\Auth\ProviderAvatar;
use Exceedone\Exment\Auth\ThrottlesLogins;
use Exceedone\Exment\Providers\CustomUserProvider;
use Encore\Admin\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Request as Req;
use Exceedone\Exment\Controllers\AuthTrait;

/**
 * For login 2 factor
 */
class Auth2factorService
{
    protected static $providers = [
    ];

    /**
     * Register providers.
     *
     * @param string $abstract
     * @param string $class
     *
     * @return void
     */
    public static function providers($abstract, $class)
    {
        static::$providers[$abstract] = $class;
    }

    public static function getProvider(){
        $provider = \Exment::user()->getSettingValue(
            implode(".", [UserSetting::USER_SETTING, 'login_2factor_provider']),
            System::login_2factor_provider() ?? Login2FactorProviderType::EMAIL
        );

        if(!array_has(static::$providers, $provider)){
            throw new \Exception("Login 2factor provider [$provider] does not exist.");
        }

        return new static::$providers[$provider];        
    }

    /**
     * Verify code
     *
     * @param string $verify_type
     * @param string $verify_code
     * @param bool $matchDelete if true, remove match records
     * @return bool
     */
    public static function verifyCode($verify_type, $verify_code, $matchDelete = false){
        $loginuser = \Admin::user();

        // remove old datetime value
        \DB::table('login_2factor_verifies')
            ->where('valid_period_datetime', '<', \Carbon\Carbon::now())
            ->delete();

        // get from database
        $query = \DB::table(SystemTableName::LOGIN_2FACTOR_VERIFY)
            ->where('verify_code', $verify_code)
            ->where('verify_type', $verify_type)
            ->where('email', $loginuser->email)
            ->where('login_user_id', $loginuser->id);

        if($query->count() == 0){
            return false;
        }

        $verify = $query->first();

        if($matchDelete){
            static::deleteCode($verify_type, $verify_code);
        }

        return $verify;
    }

    /**
     * Add database and Send verify
     *
     * @param string $verify_type
     * @param string $verify_code
     * @param bool $matchDelete if true, remove match records
     * @return bool
     */
    public static function addAndSendVerify($verify_type, $verify_code, $valid_period_datetime, $mail_template, $mail_prms = []){
        $loginuser = \Admin::user();

        // set database
        \DB::table(SystemTableName::LOGIN_2FACTOR_VERIFY)
            ->insert(
                [
                    'login_user_id' => $loginuser->id,
                    'email' => $loginuser->email,
                    'verify_code' => $verify_code,
                    'verify_type' => $verify_type,
                    'valid_period_datetime' => $valid_period_datetime->format('Y/m/d H:i'),
                ]
            );

        // send mail
        try {
            MailSender::make($mail_template, $loginuser->email)
                ->prms($mail_prms)
                ->send();

                return true;
        }
        // throw mailsend Exception
        catch (\Swift_TransportException $ex) {
            return false;
        }
    }

    public static function deleteCode($verify_type, $verify_code){
        $loginuser = \Admin::user();
        \DB::table(SystemTableName::LOGIN_2FACTOR_VERIFY)
            ->where('verify_code', $verify_code)
            ->where('verify_type', $verify_type)
            ->where('email', $loginuser->email)
            ->where('login_user_id', $loginuser->id)
            ->delete();
    }
}
