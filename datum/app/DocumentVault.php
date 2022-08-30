<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentVault extends Model
{
    public $timestamps = false;
    protected $table = 'DocumentVault';

    protected $fillable = ["PropertyId", "DownloadDateTime", "UserId", "DocumentId", "DocumentType", "FileType", "FilePath", "DirectoryFileId", "LastModifiedAt", "ConfigurationId", "HostedConfigurationId"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
