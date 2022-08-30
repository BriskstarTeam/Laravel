<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class ListingOffersContactMapping extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'listingofferscontactmapping';

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
