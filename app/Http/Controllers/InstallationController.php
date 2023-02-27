<?php

namespace App\Http\Controllers;

use App\Models\Store;
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
                        $endpoint = 'https://' . $request->shop . '/admin/oauth/authorize?client_id=' . config('const.shopify_api_key') .
                            '&scope=' . config('const.api_scopes') .
                            '&redirect_uri=' . config('app.ngrok_url') . '/shopify/auth/redirect';

                        return Redirect::to($endpoint);
                    }
                } else {
                    throw new Exception('Shop parameter not present in request');
                }
            } else {
                throw new Exception('Request is not valid');
            }
        } catch (Exception $e) {
            Log::info($e->getMessage() . ' ' . $e->getLine());
            dd($e->getMessage() . ' ' . $e->getLine());
        }
    }

    public function handleRedirect(Request $request)
    {
        try {
            $validRequest = $this->validateRequestFromShopify($request->all());
            if ($validRequest) {
                Log::info(json_encode($request->all()));
                if ($request->has('shop') && $request->has('code')) {
                    $shop = $request->shop;
                    $code = $request->code;
                    $accessToken = $this->requestAccessTokenFromShopifyForThisStore($shop, $code);
                    if ($accessToken !== false && $accessToken !== null) {
                        $shopDetails = $this->getShopDetailFromShopify($shop, $accessToken);
                        $saveDetails = $this->saveStoreDetailsToDatabase($shopDetails, $accessToken);
                        if ($saveDetails) {
                            Redirect::to(config('app.ngrok_url') . 'shop/auth/complete');
                        } else {
                            Log::info('Problem during saving shop details into the DB');
                            Log::info($saveDetails);
                        }
                    } else throw new Exception('Invalid Access Token' . $accessToken);
                } else throw new Exception('Code/ Shop param not present in URL');
            } else throw new Exception('Request is not valid!');
        } catch (Exception $e) {
            Log::info($e->getMessage() . ' ' . $e->getLine());
            dd($e->getMessage() . ' ' . $e->getLine());
        }
    }

    public function saveStoreDetailsToDatabase($shopDetails, $accessToken)
    {
        try {
            $payload = [
                'access_token' => $accessToken,
                'myshopify_domain' => $shopDetails['myshopify_domain'],
                'id' => $shopDetails['id'],
                'name' => $shopDetails['name'],
                'phone' => $shopDetails['phone'],
                'address1' => $shopDetails['address1'],
                'address2' => $shopDetails['address2'],
                'zip' => $shopDetails['zip'],
            ];
            Store::updateOrCreate(['myshopify_domain' => $shopDetails['myshopify_domain']], $payload);

            return true;
        } catch (Exception $e) {
            Log::info($e->getMessage() . '' . $e->getLine());

            return false;
        }
    }

    public function completeInstallation(Request $request)
    {
        print_r('Installation complete');
        exit;
    }
    private function getShopDetailFromShopify($shop, $accessToken)
    {
        try {
            $endpoint = getShopifyURLForStore('shop.json', ['myshopify_domain' => $shop]);
            $headers = getShopifyHeadersForStore(['access_token' => $accessToken]);
            $response = $this->makeAnAPICallToShop('GET', $endpoint, null, $headers, null);
            if ($response['statusCode'] == 200) {
                $body = $response['body'];
                if (!is_array($body)) $body = json_decode($body, true);

                return $body['shop'] ?? null;
            } else {
                Log::info('Response received for shop details');
                Log::info($response);

                return null;
            }
        } catch (Exception $e) {
            Log::info('Problem getting the shop detail from shopify');
            Log::info($e->getMessage() . ' ' . $e->getLine());
        }
    }
    private function requestAccessTokenFromShopifyForThisStore($shop, $code)
    {
        try {
            $endpoint = 'https://' . $shop . '/admin/oauth/access_token';
            $headers = ['Content-Type' => 'application/json'];
            $requestBody = json_encode([
                'client_id' => config('const.shopify_api_key'),
                'client_secret' => config('const.shopify_api_secret'),
                'code' => $code
            ]);
            $response = $this->makeAPOSTCallToShopify($requestBody, $endpoint, $headers);
            Log::info('Response for getting the access token');
            Log::info(json_encode($response));

            if ($response['statusCode'] == 200) {
                $body = $response['body'];
                if (!is_array($body)) $body = json_decode($body, true);
                if (isset($body['access_token']) && $body['access_token'] != null) {
                    return $body['access_token'];
                }
                return false;
            }
        } catch (Exception $e) {
            Log::info($response);
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

                $arr[] = $key . '=' . $value;
            }

            $str = implode('&', $arr);
            $ver_hmac = hash_hmac('sha256', $str, config('const.shopify_api_secret'), false);

            return $ver_hmac == $hmac;
        } catch (Exception $e) {
            Log::info('problem with verify hmac');
            Log::info($e->getMessage() . ' ' . $e->getLine());
        }
    }

    private function checkIfAccessTokenIsValid($storeDetails)
    {
        try {
            if ($storeDetails !== null && isset($storeDetails->access_token) && strlen($storeDetails->access_token) > 0) {
                $token = $storeDetails->access_token;
                $endpoint = getShopifyURLForStore('shop.json', $storeDetails);
                $headers = getShopifyHeadersForStore($storeDetails);
                $response = $this->makeAnAPICallToShop('GET', $endpoint, null, $headers, null);

                Log::info($response);

                return $response['statusCode'] == 200;
            }
        } catch (Exception $e) {
            return false;
        }
    }
}
