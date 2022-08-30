<?php
namespace App\Rules;



use Illuminate\Contracts\Validation\Rule;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\UserContactMapping;
use App\UserContactMappingHistory;
use App\Users;
use Illuminate\Support\Facades\Auth;

class EmailVerificationUnique implements Rule

{

    public function __construct($param)
    {
        if($param != "") {
            $this->type = $param;
        }
    }
    public $type = "";
    /**

     * Determine if the validation rule passes.

     *

     * @param  string  $attribute

     * @param  mixed  $value

     * @return bool

     */

    public function passes($attribute, $email)
    {
        $userId = "";
        if($this->type != "") {
            $userId = $this->type;
        }
        /*if($userId != "") {
            $count = DB::select("SELECT count(*) as count FROM Users INNER JOIN UserContactMapping ON UserContactMapping.UserId = Users.Id where Users.Email = '".$value."' AND ( UserContactMapping.Status != 3 AND Users.Id != {$userId} )");
        } 
        else 
        {*/
            $emails_data = array(
                'm.artworkltk.com',
                'smtp.yopmail.com',
                '_dc-mx.9c0e61b219cd.matra.site',
                'prd-smtp.10minutemail.com',
                'mail.guerrillamail.com',
            );

            /*include_once base_path('sendgrid-API/sendgrid-php.php');

            $apiKey = env('SENDGRID_EMAIL_API_KEY');
            $sg = new \SendGrid($apiKey);
            $request_body = json_decode('{
                "email": "'.$email.'"
            }');*/

            $client = new \QuickEmailVerification\Client('24e00452c32d19d4f647fbedc26b80a709dd2986048917b6bb2633997c00');
            $quickemailverification = $client->quickemailverification();
            $response = $quickemailverification->verify($email);

            try {
                if($response->body['result'] == 'invalid'){
                    return false;
                }else{
                    $email = explode("@",$email);
                    if(isset($email[1])){
                        $domain = $email[1];
                        if(checkdnsrr($domain , "A")){
                            $dns = dns_get_record($domain,DNS_MX);
                            if(!empty($dns)){
                                $target = array();
                                foreach ($dns as $key => $value) {
                                    $target[] = $value['target'];
                                }
                                //dd($target);
                                $result = array_intersect($emails_data, $target);
                                if(!empty($result)){
                                    return false;
                                }else{
                                    return true;
                                }
                                
                            }else{
                                return false;
                            }
                            
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }
            } catch (Exception $ex) {
                return false;
            }
        //}
    }



    /**

     * Get the validation error message.

     *

     * @return string

     */

    public function message()

    {

        return 'Please enter a valid Email Address.';

    }

}
