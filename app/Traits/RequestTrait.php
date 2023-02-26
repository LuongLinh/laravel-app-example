<?php

namespace App\Traits;

trait RequestTrait
{
    public function makeAnAPICallToShop($method, $url, $url_params, $headers, $responseBody = null)
    {
        try {
            $client = new Client();
            $response = null;
            switch ($method) {
                case 'GET':
                    $response = $client->request($method, $url, ['header' => $headers]);
                    break;
            }

            return [
                'statusCode' => $response->getStatusCode(),
                'body' => $response->getBody(),
            ];
        } catch (Exception $e) {
            return [
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'body' => null,
            ];
        }
    }
}
