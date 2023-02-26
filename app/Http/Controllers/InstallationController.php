<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class InstallationController extends Controller
{
    public function startInstallation(Request $request)
    {
        try {
            $validRequest = $this->validateRequestFromShopify($request->all());
            if ($validRequest) {
                $shop = $request->has('shop');
                if ($shop) {
                    $storeDetails = $this->getStoreByDomain($request->shop);
                    if ($storeDetails !== null && $storeDetails !== false) {
                        $validateAccessToken = $this->checkIfAccessTokenIsValid($storeDetails);
                        if ($validateAccessToken) {
                            //token is valid => redirect to login page
                            print_r('Here is the valid token part');
                            exit;
                        } else {
                            //token is not valid => re-installation
                            print_r('Here is not the valid token part');
                            exit;
                        }
                    } else {
                        Log::info('Installation for shop');
                        $endpoint = 'https://'.$request->shop.'/admin/oauth/authorize?client_id='.config('const.shopify_api_key').
                        '&scope='.config('const.api_scopes').
                        '&redirect_uri='.config('app.ngrok_url').'/shopify/auth/redirect';

                        return Redirect::to($endpoint);
                    }
                } else {
                    throw new Exception('Shop parameter not present in request');
                }
            } else {
                throw new Exception('Request is not valid');
            }
        } catch (Exception $e) {
            Log::info($e->getMessage().' '.$e->getLine());
            dd($e->getMessage().' '.$e->getLine());
        }
    }

    public function handleRedirect(Request $request)
    {
        try {
            $validRequest = $this->validateRequestFromShopify($request->all());
            if($validRequest) {
                if($request->has('shop') && $request->has('code')) {
                    $shop = $request->shop;
                    $code = $request->code;
                    $accessToken = $this->requestAccessTokenFromShopifyForThisStore($shop, $code);
                    Log::info('Access Token '.$accessToken);
                    if($accessToken !== false && $accessToken !== null) {
                        $shopDetails = $this->getShopDetailsFromShopify($shop, $accessToken);
                        $saveDetails = $this->saveStoreDetailsToDatabase($shopDetails, $accessToken);
                        if($saveDetails) {  
                            //At this point the installation process is complete.
                            return Redirect::route('login');
                        } else {
                            Log::info('Problem during saving shop details into the db');
                            Log::info($saveDetails);
                            dd('Problem during installation. please check logs.');
                        }
                    } else throw new Exception('Invalid Access Token '.$accessToken);
                } else throw new Exception('Code / Shop param not present in the URL');
            } else throw new Exception('Request is not valid!');
        } catch(Exception $e) {
            Log::info($e->getMessage().' '.$e->getLine());
            dd($e->getMessage().' '.$e->getLine());
        }
    }

    private function validateRequestFromShopify($request)
    {
        try {
            $arr = [];
            $hmac = $request['hmac'];
            unset($request['hmac']);

            foreach ($request as $key => $value) {
                $key = str_replace('%', '%25', $key);
                $key = str_replace('&', '%26', $key);
                $key = str_replace('=', '%3D', $key);
                $value = str_replace('%', '%25', $value);
                $value = str_replace('&', '%26', $value);

                $arr[] = $key.'='.$value;
            }

            $str = implode('&', $arr);
            $ver_hmac = hash_hmac('sha256', $str, config('const.shopify_api_secret'), false);

            return $ver_hmac == $hmac;
        } catch (Exception $e) {
            Log::info('problem with verify hmac');
            Log::info($e->getMessage().' '.$e->getLine());
        }
    }

    private function checkIfAccessTokenIsValid($storeDetails)
    {
        try {
            if ($storeDetails !== null && isset($storeDetails->access_token) && strlen($storeDetails->access_token) > 0) {
                $token = $storeDetails->access_token;
                $endpoint = getShopifyURLForStore('shop.json', $storeDetails);
                $headers = getShopifyHeadersForStore($storeDetails);
                $response = $this->makeAnAPICallToShop('GET', $endpoint, null, $headers);

                Log::info($response);

                return $response['statusCode'] == 200;
            }
        } catch (Exception $e) {
            return false;
        }
    }
}
