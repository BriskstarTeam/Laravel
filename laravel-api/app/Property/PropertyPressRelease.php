<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyPressRelease extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    public $timestamps = false;

    protected $table = 'PropertyPressRelease';

    protected $fillable = ["PropertyId", "IsPressReleaseFile", "PressReleaseFile", "PressReleaseLink", "CreatedDate", "CreatedBy"];

    protected $primaryKey = 'Id';
}
