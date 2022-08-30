<?php 
namespace App\Http\Controllers; 

use App\Congigurations;
use App\Database\DatabaseConnection;
use App\PluginActivation;
use Illuminate\Support\Facades\DB;
use App\Insights\WPPosts;
use App\Insights\InsightImages;
use App\Insights\InsightsMapping;
use App\Insights\InsightPageTracking;
use App\Insights\InsightDocumentTracking;
use App\Insights\InsightsPageTrackHistory;
use Illuminate\Http\Request;
use App\Property\Property;
use Illuminate\Support\Facades\Storage;
use Hashids\Hashids;
use App\Traits\Common;
use App\Traits\EncryptionDecryption;

/**
 * Class InsightListingController
 * @package App\Http\Controllers
 */
class InsightListingController extends Controller {
    use Common, EncryptionDecryption;
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function insightListing(Request $request)
    {
        $AzImage = env('AZURE_STOGARGE_CONTAINER_INSIGHTS_IMAGES');
        $databaseConnection = new DatabaseConnection();
        $configurations     = $databaseConnection->getConfiguration();
        $configurationsId   = $configurations->ConfigurationId;

        $query = WPPosts::query()->with('getInsightTags', 'getConfigurationName','getInsightContent','getInsightTitleImages');

        $query->leftJoin('InsightsMapping', 'WPPosts.Id', '=', 'InsightsMapping.InsightId');

        //$query->where('WPPosts.IsDeleted', 0);
        $query->where(function ($q) use ($configurationsId) {
            $q->where(function ($q1) use ($configurationsId) {
                $q1->where('WPPosts.PostStatus', '=', 'Active');
                $q1->where('WPPosts.ConfigurationId', $configurationsId);
            });
            $q->orWhere(function ($q2) use ($configurationsId) {
                $q2->where('InsightsMapping.StageStatusId', 1);
                $q2->where('InsightsMapping.ConfigurationId', $configurationsId);
            });
        });
        $query->select([
            "WPPosts.Id",
            DB::raw('CAST(WPPosts.PostTitle AS VARCHAR(MAX)) AS PostTitle'),
            "WPPosts.PostAuthor",
            "WPPosts.PostDate",
            "WPPosts.PostExcerpt",
            "WPPosts.PostStatus",
            "WPPosts.CommentStatus",
            "WPPosts.PostModified",
            DB::raw('CAST(WPPosts.PostContentFiltered AS VARCHAR(MAX)) AS PostContentFiltered'),
            "WPPosts.Guid",
            "WPPosts.CommentCount",
            "WPPosts.Authors",
            "WPPosts.FeaturedInsight",
            "WPPosts.Category",
            "WPPosts.PostTags",
            "WPPosts.DocumentPermission",
            "WPPosts.PublishedDate",
            "WPPosts.KeyPhrase",
            "WPPosts.MetaDescription",
            "WPPosts.DisplayOrder",
            "WPPosts.InsightVideoLink",
            "WPPosts.IsDeleted",
            "WPPosts.DeletedDate",
            "WPPosts.LastModifiedAt",
            "WPPosts.PostDateGMT",
            "WPPosts.PostModifiedGMT",
            "WPPosts.PostVisibility",
            "WPPosts.ConfigurationId",
            "WPPosts.InsightElements",
            "WPPosts.HasImageRights",
            "WPPosts.PrivateListingUrl",
            "WPPosts.BlobStorageUrl",
            "WPPosts.UrlSlug",
            "WPPosts.HashId",
        ]);
        
        $query->groupBy(
            "WPPosts.Id",
            DB::raw('CAST(WPPosts.PostTitle AS VARCHAR(MAX))'),
            "WPPosts.PostAuthor",
            "WPPosts.PostDate",
            "WPPosts.PostExcerpt",
            "WPPosts.PostStatus",
            "WPPosts.CommentStatus",
            "WPPosts.PostModified",
            DB::raw('CAST(WPPosts.PostContentFiltered AS VARCHAR(MAX))'),
            "WPPosts.Guid",
            "WPPosts.CommentCount",
            "WPPosts.Authors",
            "WPPosts.FeaturedInsight",
            "WPPosts.Category",
            "WPPosts.PostTags",
            "WPPosts.DocumentPermission",
            "WPPosts.PublishedDate",
            "WPPosts.KeyPhrase",
            "WPPosts.MetaDescription",
            "WPPosts.DisplayOrder",
            "WPPosts.InsightVideoLink",
            "WPPosts.IsDeleted",
            "WPPosts.DeletedDate",
            "WPPosts.LastModifiedAt",
            "WPPosts.PostDateGMT",
            "WPPosts.PostModifiedGMT",
            "WPPosts.PostVisibility",
            "WPPosts.ConfigurationId",
            "WPPosts.InsightElements",
            "WPPosts.HasImageRights",
            "WPPosts.PrivateListingUrl",
            "WPPosts.BlobStorageUrl",
            "WPPosts.UrlSlug",
            "WPPosts.HashId",
        );

        if(isset($request->Category) && $request->Category == "latestInsight"){
            $query->where('WPPosts.PostDate','<=', date('Y-m-d'));
            $query->orderBy('WPPosts.PublishedDate', 'DESC');
            $insight = $query->paginate(4);
        }
        else {
            $query->where('WPPosts.PostDate','<=', date('Y-m-d'));
            $query->where('WPPosts.Category','=',$request->Category);
            $query->orderBy('WPPosts.PublishedDate', 'DESC');
            $insight = $query->paginate(9);
        }

        if(isset($request->related_insight) && ($request->related_insight == true))
        {
            $query->where('WPPosts.PostDate','<=', date('Y-m-d'));
            $query->where('WPPosts.Category','=',$request->Category);
            $query->orderBy('WPPosts.PublishedDate', 'DESC');
            $insight = $query->paginate(6);
        }

        if ($insight){
            foreach ($insight as $key => $value) {
                if($value->ConfigurationId == $configurationsId){
                    $value->relatedInsightLink = false;
                }else{
                    $configurationsNonData = Congigurations::where('ConfigurationId',$value->ConfigurationId)->first();
                    $value->relatedInsightLink = $configurationsNonData->SiteUrl;
                }


                $hashids = new Hashids('NEWmark_2022',0,'abcdefghijklmnopqrstuvwxyz1234567890','cfhistu');
                $value->imId        = $hashids->encode($value->Id);
                $value->titleImage  = $hashids->encode($value->Id);
                if(isset($value->getInsightTitleImages->Filename)){
                    $value->SMImage     = $AzImage .'/'. $hashids->encode($value->Id).'/title_image/md/'.$value->getInsightTitleImages->Filename;
                }
            }
            return response()->json(
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => $insight
                ], 200);
        }
        else 
        {
            return response()->json(
                [
                    'status' => 'failed',
                    'message' => 'No insights found',
                    'errors' => [],
                    'data' => []
                ], 200);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function insightCategories()
    {
        $databaseConnection = new DatabaseConnection();
        $configurations     = $databaseConnection->getConfiguration();
        $configurationsId   = $configurations->ConfigurationId;
        
        $query = WPPosts::query();

        $query->leftJoin('InsightsMapping', 'WPPosts.Id', '=', 'InsightsMapping.InsightId');

        $query->where('WPPosts.IsDeleted', 0);
        
        $query->where(function ($q) use ($configurationsId) {
            $q->where(function ($q1) use ($configurationsId) {
                $q1->where('WPPosts.PostStatus', '=', 'Active');
                $q1->where('WPPosts.ConfigurationId', $configurationsId);
            });
            $q->orWhere(function ($q2) use ($configurationsId) {
                $q2->where('InsightsMapping.StageStatusId', 1);
                $q2->where('InsightsMapping.ConfigurationId', $configurationsId);
            });
        });

        $query->select([
            "WPPosts.Category",
        ]);
        $query->where('WPPosts.PostDate','<=', date('Y-m-d'));
        
        $query->whereNotNull('WPPosts.Category');

        $query->distinct('Category');

        $result = $query->get();

        if ($result != null && $result != '')
        {
            return response()->json(
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => $result
                ], 200);
        }
        else 
        {
            return response()->json(
                [
                    'status' => 'failed',
                    'message' => 'No insights category found',
                    'errors' => [],
                    'data' => []
                ], 200);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function insightPage(Request $request)
    {   
        $databaseConnection = new DatabaseConnection();
        $configurations     = $databaseConnection->getConfiguration();
        $configurationsId   = $configurations->ConfigurationId;
        /*Check if parameter is id or URL slug*/ 
        if(!empty(is_numeric($request->insight_id)))
        {  
            $query = WPPosts::query()->with('getInsightElements','getConfigurationName','getInsightImages','getInsightBannerImages','getInsightTitleImages','getInsightDocument','getInsightDocumentPreviewImage');
            
            $query->leftJoin('InsightsMapping', 'WPPosts.Id', '=', 'InsightsMapping.InsightId');

            $query->select([
                "WPPosts.Id",
                DB::raw('CAST(WPPosts.PostTitle AS VARCHAR(MAX)) AS PostTitle'),
                "WPPosts.PostAuthor",
                "WPPosts.PostDate",
                "WPPosts.PostExcerpt",
                "WPPosts.PostStatus",
                "WPPosts.CommentStatus",
                "WPPosts.PostModified",
                DB::raw('CAST(WPPosts.PostContentFiltered AS VARCHAR(MAX)) AS PostContentFiltered'),
                "WPPosts.Guid",
                "WPPosts.CommentCount",
                "WPPosts.Authors",
                "WPPosts.FeaturedInsight",
                "WPPosts.Category",
                "WPPosts.PostTags",
                "WPPosts.DocumentPermission",
                "WPPosts.PublishedDate",
                "WPPosts.KeyPhrase",
                "WPPosts.MetaDescription",
                "WPPosts.DisplayOrder",
                "WPPosts.InsightVideoLink",
                "WPPosts.IsDeleted",
                "WPPosts.DeletedDate",
                "WPPosts.LastModifiedAt",
                "WPPosts.PostDateGMT",
                "WPPosts.PostModifiedGMT",
                "WPPosts.PostVisibility",
                "WPPosts.ConfigurationId",
                "WPPosts.InsightElements",
                "WPPosts.HasImageRights",
                "WPPosts.PrivateListingUrl",
                "WPPosts.BlobStorageUrl",
                "WPPosts.UrlSlug",
                "WPPosts.HashId",
            ]);

            //$query->where('WPPosts.IsDeleted', 0);
            $query->where(function ($q) use ($configurationsId) {
                $q->where(function ($q1) use ($configurationsId) {
                    $q1->where('WPPosts.PostStatus', '=', 'Active');
                    $q1->where('WPPosts.ConfigurationId', $configurationsId);
                });
                $q->orWhere(function ($q2) use ($configurationsId) {
                    $q2->where('InsightsMapping.StageStatusId', 1);
                    $q2->where('InsightsMapping.ConfigurationId', $configurationsId);
                });
            });
            $query = $query->where('WPPosts.HashId', '=', $request->insight_id)->first();
            if($query){
                $insight_id = $query->Id;
            }else{
                return response()->json(
                [
                    'status' => 'failed',
                    'message' => 'Insights details not found',
                    'errors' => [],
                    'data' => []
                ], 200);
            }
        }
        else {
            /*URL slug query to be fired.*/
            $query = WPPosts::query()->with('getInsightElements','getConfigurationName','getInsightImages','getInsightBannerImages','getInsightTitleImages','getInsightDocument','getInsightDocumentPreviewImage')->where('UrlSlug', '=', $request->insight_id)->where('WPPosts.PostStatus', 'Active')->first();
            $id = WPPosts::query()->select('Id')->where('HashId','=',$request->insight_id)->first();
            $insight_id = $id->Id;
        }

        $InsightPageTrackings = new InsightPageTracking();
            $HostedConfigurationId = DB::table('WPPosts')
            ->where('Id', '=', $insight_id)
            ->select('ConfigurationId')
            ->get();
            $databaseConnection = new DatabaseConnection();
            $configurations = $databaseConnection->getConfiguration();
            $configurationsId = $configurations->ConfigurationId;
            
           
        if((isset($request->is_Insightview) && $request->is_Insightview == 'true'))
        {
            $browse_from_mobile = 0;

            if ( isset( $request->device ) && $request->device != '') {
                $browse_from_mobile = $request->device;
            }else{
                $browse_from_mobile = '5';
            }
            $currentUser        = auth()->guard('api')->user();
            if(!empty($currentUser)) {
                $InsightPageTrackings->UserId = $currentUser->Id;  
            }
            $InsightPageTrackings->InsightId = $insight_id;
            $InsightPageTrackings->UserIP = $request->ip;
            $InsightPageTrackings->BrowseFromMobile = $browse_from_mobile;
            $InsightPageTrackings->DateEntered = date('Y-m-d H:i:s');
            $InsightPageTrackings->LastModifiedAt = date('Y-m-d H:i:s');
            $InsightPageTrackings->ConfigurationId = $configurationsId;
            $InsightPageTrackings->HostedConfigurationId =$HostedConfigurationId[0]->ConfigurationId;     
            $InsightPageTrackings->save();
        }
        $AzImage = env('AZURE_STOGARGE_CONTAINER_INSIGHTS_IMAGES');

        $hashids = new Hashids('NEWmark_2022',0,'abcdefghijklmnopqrstuvwxyz1234567890','cfhistu');
        
        if(isset($query->getInsightTitleImages->Filename)){
            $query->SMImage     = $AzImage .'/'. $hashids->encode($insight_id).'/title_image/md/'.$query->getInsightTitleImages->Filename;
        }else{
            $query->SMImage      =  null;
        }
        
        if(isset($query->getInsightBannerImages->Filename)){
            $query->BannerImage     = $AzImage .'/'. $hashids->encode($insight_id).'/banner_image/lg/'.$query->getInsightBannerImages->Filename;
        }else{
            $query->BannerImage      =  null;
        }
            
        if(!empty($query->getInsightDocumentPreviewImage)){
            $query->InsightDocumentPreviewImag      = $AzImage .'/'. $hashids->encode($insight_id).'/market_report_image/'.$query->getInsightDocumentPreviewImage->Filename;
        }else{
            $query->InsightDocumentPreviewImag      =  null;
        }
        
        if ($query != null && $query != '')
        {
            return response()->json(
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => $query
                ], 200);
        }
        else 
        {
            return response()->json(
                [
                    'status' => 'failed',
                    'message' => 'Insights details not found',
                    'errors' => [],
                    'data' => []
                ], 200);
        } 
       
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function insightDocumentTracking(Request $request)
    {
        $query = InsightImages::query()->where('InsightId', '=', $request->insight_id)->where('IsUploaded','=',1)->where('IsDelete','=',0)->where('ImageMode','=',4)->first();

        $databaseConnection = new DatabaseConnection();
        $configurations = $databaseConnection->getConfiguration();
        $configurationsId = $configurations->ConfigurationId;

         $HostedConfigurationId = DB::table('WPPosts')
            ->where('Id', '=', $request->insight_id)
            ->select('ConfigurationId')
            ->get();

        $insightDocumentTracking = new InsightDocumentTracking();

        $currentUser        = auth()->guard('api')->user();
        if(!empty($currentUser)) {
            $insightDocumentTracking->UserId = $currentUser->Id;  
        }
        $insightDocumentTracking->InsightId = $request->insight_id;
        $insightDocumentTracking->UserIP = $request->ip();
        $insightDocumentTracking->DateEntered = date('Y-m-d H:i:s');
        $insightDocumentTracking->LastModifiedAt = date('Y-m-d H:i:s');
        $insightDocumentTracking->ConfigurationId = $configurationsId;
        $insightDocumentTracking->HostedConfigurationId =$HostedConfigurationId[0]->ConfigurationId;

        if ($insightDocumentTracking->save())
        {
            return response()->json(
            [
                'status' => 'success',
                'message' => [],
                'errors' => [],
                'data' => $query
            ], 200);
        }
        else 
        {
            return response()->json(
                [
                    'status' => 'failed',
                    'message' => 'Insights document data count not inserted',
                    'errors' => [],
                    'data' => []
                ], 200);
        } 
        
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function relatedInsights(Request $request)
    {  
        $databaseConnection = new DatabaseConnection();
        $configurations     = $databaseConnection->getConfiguration();
        $configurationsId   = $configurations->ConfigurationId;
        $AzImage = env('AZURE_STOGARGE_CONTAINER_INSIGHTS_IMAGES');

        if(isset($request->related_insight))
        {
            $query = WPPosts::query()->with('getConfigurationName','getInsightTitleImages','getInsightTags','getInsightContent')->where('Category','=',$request->Category)->where('PostStatus','=','Active')->where('IsDeleted','=',0)->whereNotIn('WPPosts.Id',array($request->insightid))->where('WPPosts.ConfigurationId', '=', $configurationsId)->where('WPPosts.PostDate','<=', date('Y-m-d'))->get();
        }
        if ($query != null && $query != '')
        {

            foreach ($query as $key => $value) {

                $hashids = new Hashids('NEWmark_2022',0,'abcdefghijklmnopqrstuvwxyz1234567890','cfhistu');
                $value->SMImage     = $AzImage .'/'. $hashids->encode($value->Id).'/title_image/md/'.$value->getInsightTitleImages->Filename;

                if($value->ConfigurationId == $configurationsId){
                    $value->relatedInsightLink = false;
                }else{
                    $configurationsNonData = Congigurations::where('ConfigurationId',$value->ConfigurationId)->first();
                    $value->relatedInsightLink = $configurationsNonData->SiteUrl;
                }
            }


            return response()->json(
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => $query
                ], 200);
        }
        else 
        {
            return response()->json(
                [
                    'status' => 'failed',
                    'message' => 'Related Insights details not found',
                    'errors' => [],
                    'data' => []
                ], 200);
        } 
    }

    /**
     *
     * @return [json] Insight object
     */
    public function featuredInsights( Request $request ) {

        $AzImage = env('AZURE_STOGARGE_CONTAINER_INSIGHTS_IMAGES');
        $databaseConnection = new DatabaseConnection();
        $configurations     = $databaseConnection->getConfiguration();
        $configurationsId   = $configurations->ConfigurationId;
        //DB::enableQueryLog();
        $query = InsightsMapping::query();
        $query->Join('WPPosts', 'WPPosts.Id', '=', 'InsightsMapping.InsightId');

        $query->with('getConfigurationName','getInsightTitleImages','getInsightTags','getInsightContent');
        
        $query->where('InsightsMapping.IsFeatured','!=','0');

        $query->where('InsightsMapping.DisplayOrder','!=','0');
        $query->where('WPPosts.PostDate','<=', date('Y-m-d'));
        $query->where('WPPosts.IsDeleted','=',0);

        $query->whereNotNull('InsightsMapping.IsFeatured');

        $query->where(function ($q) use ($configurationsId) {
            $q->Where(function ($q2) use ($configurationsId) {
                $q2->where('InsightsMapping.StageStatusId', 1);
                $q2->where('InsightsMapping.ConfigurationId', $configurationsId);
            });
        });

        $query->select([
            "WPPosts.Id",
            DB::raw('CAST(WPPosts.PostTitle AS VARCHAR(MAX)) AS PostTitle'),
            "WPPosts.PostAuthor",
            "WPPosts.PostDate",
            "WPPosts.PostExcerpt",
            "WPPosts.PostStatus",
            "WPPosts.CommentStatus",
            "WPPosts.PostModified",
            DB::raw('CAST(WPPosts.PostContentFiltered AS VARCHAR(MAX)) AS PostContentFiltered'),
            "WPPosts.Guid",
            "WPPosts.CommentCount",
            "WPPosts.Authors",
            "WPPosts.FeaturedInsight",
            "WPPosts.Category",
            "WPPosts.PostTags",
            "WPPosts.DocumentPermission",
            "WPPosts.PublishedDate",
            "WPPosts.KeyPhrase",
            "WPPosts.MetaDescription",
            "WPPosts.DisplayOrder",
            "WPPosts.InsightVideoLink",
            "WPPosts.IsDeleted",
            "WPPosts.DeletedDate",
            "WPPosts.LastModifiedAt",
            "WPPosts.PostDateGMT",
            "WPPosts.PostModifiedGMT",
            "WPPosts.PostVisibility",
            "WPPosts.ConfigurationId",
            "WPPosts.InsightElements",
            "WPPosts.HasImageRights",
            "WPPosts.PrivateListingUrl",
            "WPPosts.BlobStorageUrl",
            "WPPosts.UrlSlug",
            "WPPosts.HashId",
            "InsightsMapping.DisplayOrder AS DisOrder"
        ]);
        
        $query->groupBy(
            "WPPosts.Id",
            DB::raw('CAST(WPPosts.PostTitle AS VARCHAR(MAX))'),
            "WPPosts.PostAuthor",
            "WPPosts.PostDate",
            "WPPosts.PostExcerpt",
            "WPPosts.PostStatus",
            "WPPosts.CommentStatus",
            "WPPosts.PostModified",
            DB::raw('CAST(WPPosts.PostContentFiltered AS VARCHAR(MAX))'),
            "WPPosts.Guid",
            "WPPosts.CommentCount",
            "WPPosts.Authors",
            "WPPosts.FeaturedInsight",
            "WPPosts.Category",
            "WPPosts.PostTags",
            "WPPosts.DocumentPermission",
            "WPPosts.PublishedDate",
            "WPPosts.KeyPhrase",
            "WPPosts.MetaDescription",
            "WPPosts.DisplayOrder",
            "WPPosts.InsightVideoLink",
            "WPPosts.IsDeleted",
            "WPPosts.DeletedDate",
            "WPPosts.LastModifiedAt",
            "WPPosts.PostDateGMT",
            "WPPosts.PostModifiedGMT",
            "WPPosts.PostVisibility",
            "WPPosts.ConfigurationId",
            "WPPosts.InsightElements",
            "WPPosts.HasImageRights",
            "WPPosts.PrivateListingUrl",
            "WPPosts.BlobStorageUrl",
            "WPPosts.UrlSlug",
            "WPPosts.HashId",
            "InsightsMapping.DisplayOrder"
        );

        $query->orderBy('DisOrder', 'ASC');
        $query = $query->paginate(4);
       // dd(DB::getQueryLog()); // Show results of log

        if (!empty($query))
        {
            foreach ($query as $key => $value) {
                $hashids = new Hashids('NEWmark_2022',0,'abcdefghijklmnopqrstuvwxyz1234567890','cfhistu');
                $value->SMImage     = $AzImage .'/'. $hashids->encode($value->Id).'/title_image/md/'.$value->getInsightTitleImages->Filename;
                
                if($value->ConfigurationId == $configurationsId){
                    $value->relatedInsightLink = false;
                }else{
                    $configurationsNonData = Congigurations::where('ConfigurationId',$value->ConfigurationId)->first();
                    $value->relatedInsightLink = $configurationsNonData->SiteUrl;
                }
            }

            return response()->json(
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => $query
                ], 200);
        }
        else 
        {
            return response()->json(
                [
                    'status' => 'failed',
                    'message' => 'Insights details not found',
                    'errors' => [],
                    'data' => []
                ], 200);
        } 
    }
    

    public function previewInsights ( Request $request ) {

        $databaseConnection = new DatabaseConnection();
        $configurations     = $databaseConnection->getConfiguration();
        $configurationsId   = $configurations->ConfigurationId;
        $hashids = new Hashids('NEWmark_2022',8,'1234567890abcdef');
        $id = $this->decodeId($request->hashId);
        $query = WPPosts::query();
        $query->with('getInsightElements','getConfigurationName','getInsightImages','getInsightBannerImages','getInsightTitleImages','getInsightDocument','getInsightDocumentPreviewImage');
        $query->where('WPPosts.ConfigurationId', '=', $configurationsId);
        $query->where('WPPosts.PostStatus', '=', 'Draft');
        $query->where('WPPosts.Id', '=', $id);
        $data = $query->first();

        if ( !empty( $data ) && $data != null ) {
            $AzImage = env('AZURE_STOGARGE_CONTAINER_INSIGHTS_IMAGES');

            $hashids = new Hashids('NEWmark_2022',3,'abcdefghijklmnopqrstuvwxyz1234567890','cfhistu');

            $data->SMImage = $AzImage .'/'. $data->HashId.'/title_image/md/'.$data->getInsightTitleImages->Filename;
            
            $data->BannerImage     = $AzImage .'/'.$data->HashId.'/banner_image/lg/'.$data->getInsightBannerImages->Filename;
                
            if(!empty($data->getInsightDocumentPreviewImage)){
                $data->InsightDocumentPreviewImag      = $AzImage .'/'.$data->HashId.'/market_report_image/lg/'.$data->getInsightDocumentPreviewImage->Filename;
            }else{
                $data->InsightDocumentPreviewImag      =  null;
            }
            
            return response()->json(
            [
                'status' => 'success',
                'message' => [],
                'errors' => [],
                'data' => $data
            ], 200);
        } else {
            return response()->json(
            [
                'status' => 'failed',
                'message' => 'No insights found',
                'errors' => [],
                'data' => []
            ], 200);
        }
    }

    public function debugLogging()
    {
        $log  = "INFO: ".date("F j, Y, g:i:sa").' - This is a log '.PHP_EOL;
        $logFolder = public_path('logs');
       
        if ( !is_dir( $logFolder ) ) {
            mkdir($logFolder, 0777, true) || chmod($logFolder, 0777);
        } 
        chmod($logFolder, 0777);
        //chmod($logFolder.'/'.'log_'.date("j.n.Y").'.log', 0777);
        file_put_contents($logFolder.'/cron_job_log_'.date("j.n.Y").'.log', $log, FILE_APPEND);    
    }

    public function nonhostedInsights( Request $request){
        $databaseConnection = new DatabaseConnection();
        $configurations     = $databaseConnection->getConfiguration();
        $configurationsId   = $configurations->ConfigurationId;
        
        
        $data     = $databaseConnection->getInsightConfiguration($request->HashId);

        $insightsPageTrackHistory = new InsightsPageTrackHistory();
        $insightsPageTrackHistory->InsightId            = $data->Id;
        
        $currentUser        = auth()->guard('api')->user();
        if(!empty($currentUser)) {
            $insightsPageTrackHistory->UserId = $currentUser->Id;
            $insightsPageTrackHistory->CreatedBy = $currentUser->Id;     
        }else{
            $insightsPageTrackHistory->UserId       = null;
            $insightsPageTrackHistory->CreatedBy    = null;
        }

        $random_bytes = substr(md5(microtime()),0,20);
        $insightsPageTrackHistory->UserIP               = $request->ip;
        $insightsPageTrackHistory->Token                = $random_bytes;
        $insightsPageTrackHistory->ConfigurationId      = $configurationsId;
        $insightsPageTrackHistory->HostedConfigurationId= $data->ConfigurationId;
        $insightsPageTrackHistory->Status               = 1;
        $insightsPageTrackHistory->CreatedDate   = date('Y-m-d H:i:s');

        //dd($insightsPageTrackHistory);
        $insightsPageTrackHistory->save();

        
        $url = "https://".$data->SiteUrl."/insights/".$data->UrlSlug."/".$data->HashId."?string=".$random_bytes;
        return response()->json(
            [
                'status'    => 'success',
                'message'   => [],
                'errors'    => [],
                'URL'       => $url
            ], 200);
    }


    public function insightsPageView(Request $request){

        $InsightPageTrackings = new InsightPageTracking();

        //Hosted Page View

        $browse_from_mobile = 0;
        if ( isset( $request->device ) && $request->device != '') {
            $browse_from_mobile = $request->device;
        }else{
            $browse_from_mobile = '5';
        }
        if($request->nonhost){
            if($request->user_id == ''){
                $InsightPageTrackings->UserId = NULL;
            }else{
                $currentUser        = auth()->guard('api')->user();
                if(!empty($currentUser)) {
                    $InsightPageTrackings->UserId = $currentUser->Id;  
                }else{
                    $InsightPageTrackings->UserId = $request->user_id;  
                }  
            }
        }else{
            if($request->user_id == ''){
                $InsightPageTrackings->UserId = NULL;
            }else{            
                $currentUser        = auth()->guard('api')->user();
                if(!empty($currentUser)) {
                    $InsightPageTrackings->UserId = $currentUser->Id;  
                }else{
                    $InsightPageTrackings->UserId = NULL;  
                }
            }
        }

        $InsightPageTrackings->InsightId        = $request->insightId;
        $InsightPageTrackings->UserIP           = $request->ip;
        $InsightPageTrackings->BrowseFromMobile = $browse_from_mobile;
        $InsightPageTrackings->DateEntered      = date('Y-m-d H:i:s');
        $InsightPageTrackings->LastModifiedAt   = date('Y-m-d H:i:s');
        $InsightPageTrackings->ConfigurationId  = $request->configurationsId;
        $InsightPageTrackings->HostedConfigurationId =$request->HostedConfigurationId;
        //dd($InsightPageTrackings);    
        $InsightPageTrackings->save();
        return response()->json(
                [
                    'status'    => 'success',
                    'message'   => [],
                    'errors'    => [],
                    //'userId'    => $currentUser->Id,
                    'type'      => 'same', 
                    //'data'      => $data
                ], 200);
    }

    public function checkInsightPage(Request $request){
        $query = InsightsPageTrackHistory::query();
        $query->where('Token', '=', $request->Token);
        $query->where('status', '=', 1);
        $data = $query->first();

        $data->status = 2;
        $data->save();

        $currentUser        = auth()->guard('api')->user();
        if($data->UserId != ''){
            if(!empty($currentUser) && $currentUser->Id){
                if($data->UserId == $currentUser->Id){
                   return response()->json(
                    [
                        'status'    => 'success',
                        'message'   => [],
                        'errors'    => [],
                        'userId'    => $currentUser->Id,
                        'type'      => 'same', 
                        'data'      => $data
                    ], 200);
                }else{

                    //$user = User::where('Users.Id', $currentUser->Id)->first();
                    //$request->user()->token()->revoke();

                    return response()->json(
                    [
                        'status'    => 'success',
                        'message'   => [],
                        'errors'    => [],
                        'userId'    => $data->Id,
                        'type'      => 'needToUserlogin',
                        'data'      => $data
                    ], 200);
                }
            }else{
               return response()->json(
                [
                    'status'    => 'success',
                    'message'   => [],
                    'errors'    => [],
                    'userId'    => $data->Id,
                    'type'      => 'needToUserlogin',
                    'data'      => $data
                ], 200); 
            }
            
        }else{
            if(!empty($currentUser) && $currentUser->Id){
                return response()->json(
                [
                    'status'    => 'success',
                    'message'   => [],
                    'errors'    => [],
                    'userId'    => $currentUser->Id,
                    'type'      => 'same',
                    'data'      => $data
                ], 200);
            }else{
                return response()->json(
                [
                    'status'    => 'success',
                    'message'   => [],
                    'errors'    => [],
                    'userId'    => '',
                    'type'      => 'NoAnyLogin',
                    'data'      => $data
                ], 200);
            }
        }
    }
}
?>