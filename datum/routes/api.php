<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' =>'v1', 'middleware' => ['APIToken']], function () {
    Route::post('property', 'PropertyController@index');
    Route::get('property/{id}/{name}', 'PropertyController@show');
    Route::get('configurations', 'ConfigurationsController@index');
    Route::post('configurations', 'ConfigurationsController@store');
    Route::post('favorite', 'PropertyController@favorite');
    Route::get('agent/{username}', 'AgentController@show');
    Route::post('request-inquire', 'RequestInquireController@create');
    Route::post('share-property', 'PropertyController@sharePropertyOnEmail');
    Route::post('contact-property', 'PropertyController@contactProperty');
    Route::post('get-property-by-guid', 'PropertyController@getPropertyByGUID');
    Route::get('get-agent', 'AgentController@getAgent');
    Route::post('insights-sharing-email', 'CommonController@insightsSharingEmail');
    //Route::get('page-view/{id}', 'PropertyController@pageView');
    Route::post('page-view', 'PropertyController@pageView');
    Route::post('get-agent-childsite', 'AgentController@getAgentChildSite');
    Route::post('ca-download-dashboard', 'DocumentVaultPreviewController@caDownloadDashboard');
    Route::post('zip-file-removed', 'DocumentVaultPreviewController@removedZipFileFromAzure');

    Route::post('insight-listing', 'InsightListingController@insightListing');
    Route::post('insight-categories', 'InsightListingController@insightCategories');
    Route::post('insight-page', 'InsightListingController@insightPage');
    //Route::post('insight-page-view', 'InsightListingController@addPageView');
    Route::post('insight-doc-tracking', 'InsightListingController@insightDocumentTracking');
    Route::post('related_insights', 'InsightListingController@relatedInsights');
    Route::post('featured-insights', 'InsightListingController@featuredInsights');
    
    Route::post('user-page-access', 'UserAccessController@checkUserPageAccess');
    Route::post('insight-page-access', 'InsightAccessController@checkUserPageAccess');
    
    Route::post('check-mobile-valid', 'AuthController@checkMobileNumber');
    Route::post('preview-insights', 'InsightListingController@previewInsights');
});

/**
 *
 * Authentication
 */
Route::group(['prefix' =>'v1', 'middleware' => ['Authentication', 'APIPluginKeyCheck']], function () {
    Route::post('signup', 'AuthController@signup');
    Route::post('login', 'AuthController@login')->name('login');
    Route::post('new-user-register', 'AuthController@newUserRegister');
    Route::post('user-register', 'AuthController@userRegister');
    Route::post('forgot-password', 'AuthController@forgotPassword');
    Route::get('find/{token}', 'AuthController@find');
    Route::post('resend-email-otp', 'AuthController@reSendEmailOtp');
    Route::get('get-configuration', 'ConfigurationsController@getConfiguration');
    Route::post('change-email', 'AuthController@changeEmailAddress');
    Route::post('resend-mobile-otp', 'AuthController@resendMobileOTP');
    Route::post('verify-mobile-otp', 'AuthController@verifyMobileOTP');
    Route::get('acquisitioncriteria', 'CommonController@index');
    Route::post('find-verification', 'AuthController@verificationEmailToken');
    Route::post('resend-email-verification', 'AuthController@ResendEmailVerificationLink');
    Route::post('verify-onetime-password', 'AuthController@verifyOnetimePassword');
    Route::post('reset-password', 'AuthController@passwordReset');
    Route::post('resend-onetime-password', 'AuthController@resendOnetimePassword');
    Route::post('check-email-token', 'AuthController@checkEmailToken');
});

/**
 * Auth
 *
 */
Route::group(['prefix' =>'v1', 'middleware' => ['auth:api', 'APIPluginKeyCheck']], function () {
    Route::post('logout', 'AuthController@logout');
    Route::get('user', 'AuthController@user');
    Route::post('profile', 'AuthController@profile');
    Route::post('confidential-agreement-sign', 'ConfidentialAgreementController@confidentialAgreementSign');
    Route::post('my-property-list', 'PropertyController@getMyPropertyList');
    Route::post('check-ca-sign', 'ConfidentialAgreementController@checkCaSignUser');
    Route::post('send-dd-request', 'ConfidentialAgreementController@sendDDRequest');
    Route::post('send-om-request', 'DocumentVaultPreviewController@sendOMRequest');
    Route::post('check-dd-request', 'ConfidentialAgreementController@checkDDRequest');
    Route::post('change-password', 'AuthController@changePassword');
    Route::post('get-favorite-property', 'PropertyController@getFavoriteProperty');
    Route::post('ca-property-tracking', 'ConfidentialAgreementController@caPropertyTracking');
    Route::post('document-tracking', 'DocumentVaultController@documentTracking');
    Route::post('unexecuted', 'DocumentVaultController@unexecutedDownload');
    Route::post('add-pressrelease', 'PropertyController@addPressReleaseHistory');
    Route::post('check-om-access', 'PropertyController@addOMAccessToUser');

    Route::post('property-docoument-list', 'DocumentVaultPreviewController@documentList');
    
    Route::post('property-vault-list', 'DocumentVaultPreviewController@documentVaultStructure');
    
    Route::post('property-down-vault-list', 'DocumentVaultPreviewController@downloadVaultStructure');
    Route::post('download-existing-ca', 'DocumentVaultPreviewController@downloadExistingCA');
    Route::post('check-sequrity-role-access', 'DocumentVaultPreviewController@checkSequrityRoleAccess');
});

/**
 * Plugin activatio
 */
Route::group(['prefix' =>'v1'], function () {
    Route::post('active_plugin', 'ActivationController@store');
    Route::post('add_page', 'ActivationController@addPage');
    Route::get('check-plugin-key', 'ActivationController@checkPluginActivationKey');
    Route::post('testing', 'TestingController@index');
    Route::post('cron-job-testing', 'TestingController@cronTest');
    Route::get('remove-temp-folder', 'CronController@index');
    Route::get('check-ca-sign-blobstorage', 'BlobStorageController@index');
    Route::get('create-ca-sign-blobstorage', 'BlobStorageController@createAgrement');
    Route::post('check-email-valid', 'CronController@checkEmailReal');
    //Route::post('check-mobile-valid', 'CronController@checkMobileReal');
});

Route::get('/clear-cache', function() {
    $exitCode = Artisan::call('cache:clear');
    // return what you want
});