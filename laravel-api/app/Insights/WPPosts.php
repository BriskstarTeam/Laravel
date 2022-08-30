<?php

namespace App\Insights;

use Illuminate\Database\Eloquent\Model;
use App\Congigurations;
class WPPosts extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'WPPosts';

    /**
     * @var array
     */
    protected $fillable = ["Id","PostAuthor","PostDate", "PostTitle", "PostExcerpt", "PostStatus", "CommentStatus", "PostModified", "PostContentFiltered", "Guid", "CommentCount", "Authors", "FeaturedInsight", "Category", "PostTags", "DocumentPermission", "PublishedDate", "KeyPhrase","MetaDescription", "DisplayOrder", "InsightVideoLink", "IsDeleted", "DeletedDate", "LastModifiedAt", "PostDateGMT", "PostModifiedGMT", "PostVisibility", "ConfigurationId", "InsightElements", "HasImageRights", "PrivateListingUrl", "BlobStorageUrl", "UrlSlug","HashId"];


    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return mixed
     */
    public function getInsightElements() {
        return $this->hasMany(InsightElements::class, 'InsightId')->select(['InsightElements.InsightId', 'InsightElements.InsightElementTypeId', 'InsightElements.ElementOrder', 'InsightElements.Content', 'InsightElementTypes.Name AS InsightElementType'])->join('InsightElementTypes', 'InsightElementTypes.Id', '=', 'InsightElements.InsightElementTypeId')->orderBy('InsightElements.ElementOrder', 'ASC');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getInsightTags() {
        return $this->hasMany(InsightTags::class, 'InsightId')->select(['InsightTags.InsightId', 'InsightTags.TagName'])->where('InsightTags.IsSelected','=','1')->where('InsightTags.IsDeleted','!=','1');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getConfigurationName() {
        return $this->hasOne(Congigurations::class, 'ConfigurationId', 'ConfigurationId')->select(['Configurations.SiteName', 'Configurations.ConfigurationId']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getInsightImages(){
        return $this->hasMany(InsightImages::class, 'InsightId')->select(['InsightImages.InsightId', 'InsightImages.ImageId','InsightImages.Filename','InsightImages.ImageMode','InsightImages.FilePath'])->where('IsUploaded','=',1)->where('IsDelete','=',0)->where('ImageMode','=',5);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getInsightBannerImages(){
        return $this->hasOne(InsightImages::class, 'InsightId')->select(['InsightImages.InsightId', 'InsightImages.ImageId','InsightImages.Filename','InsightImages.ImageMode','InsightImages.FilePath'])->where('IsUploaded','=',1)->where('IsDelete','=',0)->where('ImageMode','=',2);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getInsightDocumentPreviewImage(){
        return $this->hasOne(InsightImages::class, 'InsightId')->select(['InsightImages.InsightId', 'InsightImages.ImageId','InsightImages.Filename','InsightImages.ImageMode','InsightImages.FilePath'])->where('IsUploaded','=',1)->where('IsDelete','=',0)->where('ImageMode','=',6);
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
    public function getInsightContent(){
        return $this->hasMany(InsightElements::class, 'InsightId')->select(['InsightElements.InsightId', 'InsightElements.InsightElementTypeId', 'InsightElements.Content'])->where('InsightElementTypeId','=',7)->orderBy('InsightElements.ElementOrder', 'ASC');   
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getInsightDocument(){
        return $this->hasOne(InsightImages::class, 'InsightId')->select(['InsightImages.InsightId', 'InsightImages.ImageId','InsightImages.Filename','InsightImages.ImageMode','InsightImages.FilePath','InsightImages.PageCount'])->where('IsUploaded','=',1)->where('IsDelete','=',0)->where('ImageMode','=',4);
    }
}
