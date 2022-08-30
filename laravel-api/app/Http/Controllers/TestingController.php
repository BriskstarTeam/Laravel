<?php

namespace App\Http\Controllers;

use App\Congigurations;
use Illuminate\Http\Request;
use App\Properties;
use App\Property\Property;
use App\WpOsdUserPropertiesRelationship;
use App\OeplPropertyTracker;
use App\DocumentVault;
use App\Directory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\Types\Collection;
use App\Traits\Common;
use Twilio\Rest\Client;
use App\Database\DatabaseConnection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;
use MicrosoftAzure\Storage\CreateBlobOptions;
use Image;
use Hashids\Hashids;
use App\Mail\Email;

class TestingController extends Controller
{
    use Common;
    public $propertySlug = "";
    public $documentType = "";

    public function index(Request $request) {
        $email = new Email();
        $to = array(
            array(
                'email' => 'jayesh@1stop.io',
                'name' => 'Jayesh Oad'
            ),
        );
        
        $subject = "Testing Email";

        $content = "<p>This is a testing email body<p>";

        $message = $email->email_content('jayesh Oad', $content);

        $dd = $email->sendEmail( $subject, $to, $message );

        dd($dd);
        $hashids = new Hashids('NEWmark_2022',8,'1234567890abcdef');
        $numbers = $hashids->decode(65934365);
        //dd($this->decodeId(65934365));

        $query = Property::query();

        $property = $query->get();

        $dat = array();
        foreach ($property as $key => $value) {
            $numbers = $hashids->encode($value->Id);
            $name = $value->PrivateURLSlug;
            if($name){

                //$value->PrivateURLSlug = str_replace('/'.$numbers,'',$value->PrivateURLSlug);
                $value->HashId = $numbers;
                $value->save();

            }
            $dat[$value->Id] = $numbers;

        }

        dd($dat);
        $sid = env('ACCOUNT_SID');
        $token = env('ACCOUNT_TOCKEN');
        $client = new Client($sid, $token);
        $country_code = env('COUNTRY_CODE');


        $phone_number = $client->lookups->v1->phoneNumbers($request->mobile_phone)->fetch(["countryCode" => "US"]);
        dd($phone_number);

        $profileContainerName   = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');

        $azurePath              = env('AZURE_STORAGE_URL');  

        
        $image_64  = public_path('linkedin').'/snehal_p_1648534153.png';
        
        $imageName = 'snehal_p2.png';

        $fullUrl = $profileContainerName.'/'.$imageName;


        $height         = Image::make($image_64)->height();
        $width          = Image::make($image_64)->width();

        $resized_image  = Image::make($image_64)->resize($width,$height)->stream('png', 100);


        $res = Storage::disk('azure')->put($fullUrl,$resized_image);

        dd($res);

        /**
         * Sample code
         * 
         */
        //$caSignFolder = env('CA_SIGN_DOCUMENT_NAME', '');
        /*$caSignFolder = 'PropertyCASignedDocuments-Development';
        $absolutePath = public_path('/confidential_agrement/newmarkpcg.com/test-property-12102021/2085');
        $fileUrl = $caSignFolder;
        $fileName = '8db76139-a345-4fb6-9ea1-644de888';
        
        $contents = Storage::disk('azure')->putFileAs($fileUrl, $absolutePath.'/'.$fileName, $fileName);
        //$size = Storage::disk('azure')->lastModified('PropertyCASignedDocuments-Development/8db76139-a345-4fb6-9ea1-644de888');
        $headers = [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename='.$fileName,
        ];*/
        
        /**
         * Sample code
         * End
         */
        $documentPreviewHtml = '';
        if( !empty($property) && $property != null ) {
            $propertyDocBlobName = env('DOCUMENT_VAULT_NAME', '');
            $blobUrl = env('DOCUMENT_VAULT_URL', '').'/';
            $directory = $propertyDocBlobName.'/'.$property->URLSlug.'/'.$request->document_type.'/';
            $docSlug = $propertyDocBlobName.'/'.$property->URLSlug.'/';
            
            $this->propertySlug = $property->URLSlug;
            $this->documentType = $request->document_type;

            $directories = Storage::disk('azure')->allFiles($directory);
            
            if(!empty($directories)) {
                foreach ($directories as $key => $value ) {
                    $directories[$key] = str_replace($docSlug, '', $value);
                }
                $documentVaultTree = self::createTreeArray($directories);
                $documentPreviewHtml = self::createHTML($documentVaultTree);
            } else {
                $documentPreviewHtml = self::docuementNotFoundHtml($request->document_type);
            }
        } else {
            $documentPreviewHtml = self::docuementNotFoundHtml($request->document_type);
        }
        
        echo $documentPreviewHtml;
        die;
        return response()->json(
        [
            'status' => 'success',
            'message' => '',
            'errors' => [],
            'data' => [],
            'counter' => $counter
        ], 200);
    }
}