<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Companies extends Model
{
    public $timestamps = false;

    protected $table = 'Companies';

    protected $fillable = [
        "ConfigurationId",
        "CompanyName",
        "CompanyUrl",
        "CompanyLogo",
        "WorkPhone",
        "LinkedinUrl",
        "Street",
        "Suite",
        "City",
        "State",
        "Country",
        "Zipcode",
        "Email",
        "IndustryRoleId",
        "InvestorTypeId",
        "BrokerTypeId",
        "CompanyDescription",
        "CreatedBy",
        "CreatedDate",
        "UpdatedBy",
        "UpdatedDate",
        "IsDelete",
        "DeletedDate",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
