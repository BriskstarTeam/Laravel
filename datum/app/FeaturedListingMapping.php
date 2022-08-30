<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FeaturedListingMapping extends Model
{
    public $timestamps = false;
    protected $table = 'FeaturedListingMapping';
    protected $fillable = ['MainFeaturedListing', 'ClosedFeaturedListing', 'ConfigurationId', 'LastModifiedAt'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

}
