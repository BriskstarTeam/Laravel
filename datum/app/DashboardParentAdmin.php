<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DashboardParentAdmin extends Model
{
    public $timestamps = false;
    protected $table = 'dashboardparent_admin';
    protected $fillable = [
        "adminid",
        "firstname",
        "lastname",
        "createdby",
        "createddate",
        "GuId",
        "profile_image",
        "configurationid",
        "is_super_authorized_account",
        "UserType",
        "Email",
        "Status",
        "Title",
        "Company",
        "Street",
        "Suite",
        "City",
        "State",
        "ZipCode",
        "Address",
        "Country",
        "WorkPhone",
        "MobilePhone",
        "IndustryRoleId",
        "InvestorTypeId",
        "BrokerTypeId",
        "LinkedIn",
        "TeamName",
        "ExchangeStatusId",
        "CompanyId"
    ];

    protected $primaryKey = 'adminid';
}
