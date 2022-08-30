<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyTenancy extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'PropertyTenancy';

    /**
     * @var array
     */
    //protected $fillable = ["PropertyID", "Tenancy", "NumberOfTenants", "Units", "TenantNotes"];

    protected $fillable = ["PropertyId", "Tenancy", "NumberOfTenants", "Units", "LastModifiedAt"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
