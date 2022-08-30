<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class ListingContactMapping extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'listingcontactmapping';

    /**
     * @var array
     */
    protected $fillable = ["ContactType", "ListingId", "UserId", "CreatedBy", "CreatedDate"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
