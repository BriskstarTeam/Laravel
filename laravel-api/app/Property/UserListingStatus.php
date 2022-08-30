<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class UserListingStatus extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'userlistingstatus';

    /**
     * @var array
     */
    protected $fillable = ["UserID", "ListingStatusID", "CreatedDate"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
