<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TeamSubCategory extends Model
{
    public $timestamps = false;
    protected $table = 'TeamSubCategory';
    protected $fillable = ['Id', 'Name', 'ConfigurationId', 'IsDeleted', 'CreatedBy', "CreatedDate", "UpdatedBy", "UpdatedDate", "DeletedBy", "DeletedDate"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
