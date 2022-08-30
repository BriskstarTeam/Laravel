<?php

namespace App\Http\Controllers;

use App\Mail\Email;
use App\Property\Property;
use App\Property\PropertyAgents;
use App\Traits\AgentApi;
use Illuminate\Http\Request;
use App\Database\DatabaseConnection;
use App\User;

/**
 * Class RequestInquireController
 * @package App\Http\Controllers
 */
class RequestInquireController extends Controller
{
    use AgentApi;

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \SendGrid\Mail\TypeException
     */
    public function create(Request $request)
    {
        $request->validate([
            'property_id' => 'required|numeric',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email',
            'work_phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $databaseConnection = new DatabaseConnection();
        $configurations = $databaseConnection->getConfiguration();

        $property = Property::where('Id', $request->property_id)->first();

        if( !empty( $property ) ) {

            $propertyAgents = PropertyAgents::where('PropertyId', $property->Id)->where('IsNotificationEnabled', 1)->where('EntityStatusId', 1)->select('AgentId')->get();

            $agent_to = array();
            $i = 0;

            if(!empty($propertyAgents)) {
                foreach ( $propertyAgents as $key => $value ) {
                    $agent = User::where('Id', $value->AgentId)->first();
                    $agent_to[$i]['email'] = $agent->Email;
                    $agent_to[$i]['name']  = $agent->FirstName.' '.$agent->LastName;
                    $i++;

                }
                $content  = "<p><b>First Name:</b> ".$request->first_name."<br>";
                $content .= "<b>Last Name:</b> ".$request->last_name."<br>";
                $content .= "<b>Phone:</b> ".$request->work_phone."<br>";
                $content .= "<b>Email:</b> ".$request->email."<br>";
                $content .= "<b>Subject:</b> Closed Listing Inquiry | ".$property->Name."<br>";
                $content .= "<b>Message:</b> ".stripslashes($request->message)."</p>";

                $email   = new Email();
                $subject = $configurations->SiteName." Closed Listing Inquiry | ".$property->Name;
                $message = $email->email_content('Agent', $content);
                
                if( !empty($agent_to)) {
                    $email->sendEmail( $subject, $agent_to, $message );
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => '<h3>Thank you for your inquiry. <br/>A member of our team will be in touch shortly.</h3>',
                'errors' => [],
                'data' => []
            ], 200);

        } else {

            return response()->json([
                'status' => 'success',
                'message' => 'Property ID is invalid. Please try again',
                'errors' => [],
                'data' => []
            ], 200);
            
        }

    }
}