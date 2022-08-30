<?php

namespace App\Insights;

use Illuminate\Database\Eloquent\Model;
use App\Congigurations;
class InsightsMapping extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'InsightsMapping';

    /**
     * @var array
     */
    protected $fillable = ["InsightId","ConfigurationId","StageStatusId","IsFeatured", "DisplayOrder","CreatedBy","CreatedDate","UpdatedBy","UpdatedDate","LastModifiedAt"];


    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getConfigurationName() {
        return $this->hasOne(Congigurations::class, 'ConfigurationId', 'ConfigurationId')->select(['Configurations.SiteName', 'Configurations.ConfigurationId']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getInsightTitleImages(){
        return $this->hasOne(InsightImages::class, 'InsightId')->select(['InsightImages.InsightId', 'InsightImages.ImageId','InsightImages.Filename','InsightImages.ImageMode','InsightImages.FilePath'])->where('IsUploaded','=',1)->where('IsDelete','=',0)->where('ImageMode','=',1);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getInsightTags() {
        return $this->hasMany(InsightTags::class, 'InsightId')->select(['InsightTags.InsightId', 'InsightTags.TagName'])->where('InsightTags.IsSelected','=','1')->where('InsightTags.IsDeleted','!=','1');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getInsightContent(){
        return $this->hasMany(InsightElements::class, 'InsightId')->select(['InsightElements.InsightId', 'InsightElements.InsightElementTypeId', 'InsightElements.Content'])->where('InsightElementTypeId','=',7)->orderBy('InsightElements.ElementOrder', 'ASC');   
    }
}
