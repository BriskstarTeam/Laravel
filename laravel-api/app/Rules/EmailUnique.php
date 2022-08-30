<?php
namespace App\Rules;



use Illuminate\Contracts\Validation\Rule;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\UserContactMapping;
use App\UserContactMappingHistory;
use App\Users;
use Illuminate\Support\Facades\Auth;

class EmailUnique implements Rule

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

    public function passes($attribute, $value)
    {
        $userId = "";
        if($this->type != "") {
            $userId = $this->type;
        }
        
        if($userId != "") {
            $count = DB::select("SELECT count(*) as count FROM Users INNER JOIN UserContactMapping ON UserContactMapping.UserId = Users.Id where Users.Email = '".$value."' AND ( UserContactMapping.Status != 3 AND Users.Id != {$userId} )");
        } else {
            $count = DB::select("SELECT count(*) as count FROM Users INNER JOIN UserContactMapping ON UserContactMapping.UserId = Users.Id where Users.Email = '".$value."' AND ( UserContactMapping.Status != 3 )");
        }

        if($count[0]->count > 0) {
            return false;
        } else {
            return true;
        }
    }



    /**

     * Get the validation error message.

     *

     * @return string

     */

    public function message()

    {

        return 'The :attribute has already been taken.';

    }

}
