<?php

return [
    'shopify_api_key' => env('SHOPIFY_API_KEY'),
    'shopify_api_secret' => env('SHOPIFY_API_SECRET'),
    'shopify_api_version' => '2022-07',
    'api_scopes' => 'write_orders,write_fulfillments,write_customers,read_locations,write_products',
];
