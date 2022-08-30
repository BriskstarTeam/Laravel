<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class ListingStatusHistory extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'listingstatushistory';

    /**
     * @var array
     */
    protected $fillable = ["PropertyID", "StatusID", "UserID", "TotalActiveDays", "TotalInactiveDays", "UpdatedDateTime", "Stage"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
