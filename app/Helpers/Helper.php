<?php

if (! function_exists('getShopifyURLForStore')) {
    function getShopifyURLForStore($endpoint, $store)
    {dd($store);
        return 'https://'.$store['myshopify_domain'].'/admin/api/'.config('const.shopify_api_version').'/'.$endpoint;
    }
}

if (! function_exists('getShopifyHeadersForStore')) {
    function getShopifyHeadersForStore($storeDetails)
    {
        return [
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => $storeDetails['access_token']
        ];
    }
}
