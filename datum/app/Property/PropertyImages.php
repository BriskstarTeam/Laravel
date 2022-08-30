<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyImages extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'PropertyImages';

    /**
     * @var array
     */
    //protected $fillable = ["PropertyID", "ImageURL", "IsProcess", "IsDelete", "IsSaveFTP", "CreatedDate", "UserID", "Filename", "ImageMode", "Thumbnail", "ImageOrder"];
    protected $fillable = ["PropertyID", "ImageURL", "IsDelete", "IsUploaded", "CreatedDate", "UserId", "Filename", "ImageMode", "Thumbnail", "ImageOrder", "LastModifiedAt","ImageAltText"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
