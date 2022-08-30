<?php



namespace App\Rules;



use Illuminate\Contracts\Validation\Rule;

use Illuminate\Support\Facades\Hash;

use App\Password\PasswordHash;

/**
 * Class MatchOldPassword
 * @package App\Rules
 */
class MatchOldPassword implements Rule

{
    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $passwordHash = new PasswordHash(8, TRUE);
        $ds = $passwordHash->CheckPassword($value, auth()->user()->Password);
        if($ds == 1) {
            return true;
        } else {
            return md5($value) == auth()->user()->Password;
        }
    }

    /**
     * @return array|string
     */
    public function message()

    {

        return 'The :attribute is match with old password.';

    }

}
