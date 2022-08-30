<?php

namespace App\Http\Controllers;

use App\Congigurations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

/**
 * Class CongigurationController
 * @package App\Http\Controllers
 */
class CongigurationController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        $input = $request->all();
        $validator = Validator::make($request->all(), [
            'site_url'=>'required|url',
            'email'=>'required|unique:configuration,email',
            'key'=>'required|unique:configuration,key',
            'active_at'=>'required',
            'expired_at'=>'required',
        ]);

        if ($validator->fails())
        {
            $message = $validator->errors()->first();
            return response()->json([
                'statusCode'=>200,
                'success'=>false,
                'message'=>$message
            ], 200);
        }
        $configuration = Congigurations::create($input);
        return response()->json(['statusCode'=>200,'success'=>true,'message'=>[], 'data'=>$configuration], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeConf( Request $request) {
        $input = $request->all();
        $validator = Validator::make($request->all(), [
            'site_url'=>'required|url',
            'email'=>'required',
            'key'=>'required'
        ]);

        if ($validator->fails())
        {
            $message = $validator->errors()->first();
            return response()->json(['statusCode'=>200,'success'=>false,'message'=>$message], 200);
        }

        $configuration = Congigurations::where('key', $input['key'])->where('email', $input['email'])->get();
        return response()->json(['statusCode'=>200,'success'=>true,'message'=>[], 'data'=>$configuration], 200);
    }
}
