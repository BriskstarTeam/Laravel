<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {

        $exception = $this->prepareException($exception);
        if ($exception instanceof \Illuminate\Http\Exception\HttpResponseException) {
            $exception = $exception->getResponse();
        }

        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            $exception = $this->unauthenticated($request, $exception);
        }

        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            $exception = $this->convertValidationExceptionToResponse($exception, $request);
        }

        return $this->customApiResponse($exception);
    }

    private function customApiResponse($exception)
    {
        //dd($exception);
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = $exception->getStatusCode();
        } else {
            $statusCode = 500;
        }
        if (method_exists($exception, 'getCode')) {
            $code = $exception->getCode();
        } else {
            $code = 500;
        }
        $response = [];

        switch ($statusCode) {
            case 401:
                $response['message'] = 'Unauthorized';
                $response['status'] = 'failed';
                $response['errors'] = [];
                break;
            case 400:
                $response['message'] = 'Whoops, looks like something went wrong';
                $response['status'] = 'failed';
                $response['errors'] = array('Whoops, looks like something went wrong');
                $response['name']   = 1;
                break;
            case 403:
                $response['message'] = 'Forbidden';
                $response['status'] = 'failed';
                $response['errors'] = [];
                break;
            case 404:
                if($code == '20404'){
                    $response['message'] = 'Not Mobile Number is Not Valid';
                    $response['status'] = 'failed';
                    $response['errors'] = [];
                    return $response;
                }else{
                    $response['message'] = 'Not Found';
                    $response['status'] = 'failed';
                    $response['errors'] = [];
                }
                //}
                break;
            case 405:
                $response['message'] = 'Method Not Allowed';
                $response['status'] = 'failed';
                $response['errors'] = [];
                break;
            case 422:
                $response['message'] = $exception->original['message'];
                $response['errors'] = $exception->original['errors'];
                $response['status'] = 'failed';
                if(isset($response['errors']) && isset($response['errors']['exchange_status']) && isset($response['errors']['exchange_status'][0])) {
                    //$response['errors']['exchange_status'][0] = substr($response['errors']['exchange_status'][0], 0, -1);
                    $response['errors']['exchange_status'][0] = "Please provide a 1031 exchange status";
                }

                if(isset($response['errors']) && isset($response['errors']['email']) && isset($response['errors']['email'][0])) {
                    if(strpos($response['errors']['email'][0], 'email') !== false){
                        $response['errors']['email'][0] = str_replace("email", "email address", $response['errors']['email'][0]);
                    } 
                }

                if(isset($response['errors']) && isset($response['errors']['email']) && isset($response['errors']['email'][0])) {
                    if(strpos($response['errors']['email'][0], 'already') !== false){
                        $response['errors']['email'][0] = 'Email Address already in use. <a href="JavaScript:void(0)" data-popup="forgot" id="datum_forgot_password" class="text-blue datum_model_open">Forgot password?</a>';
                    }
                }
                if(isset($response['errors']) && isset($response['errors']['onetime_password']) && isset($response['errors']['onetime_password'][0])) {
                    $response['errors']['onetime_password'][0] = 'One-Time Password is required.';
                }
                break;
            case 429:
                $response['message'] = 'Too Many Requests';
                $response['status'] = 'failed';
                $response['errors'] = [];
                break;
            case 302:
                $response['message'] = 'Unauthorized';
                $response['status'] = 'failed';
                $response['errors'] = [];
                break;
            default:
                $response['message'] = 'Whoops, looks like something went wrong';
                $response['status'] = 'failed';
                $response['errors'] = array('Whoops, looks like something went wrong');
                break;
        }

        return response()->json($response, $statusCode);
    }
}
