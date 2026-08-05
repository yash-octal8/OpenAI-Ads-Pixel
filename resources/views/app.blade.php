<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="shopify-api-key" content="{{ \Osiset\ShopifyApp\Util::getShopifyConfig('api_key') }}" />
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <title>Smart Upload</title>
    @viteReactRefresh
    @vite('resources/js/app.jsx')
</head>
<body>
    <div id="app"></div>
    <script>
        window.shopifyConfig = {
            shopOrigin: '{{ Auth::user()->name ?? "" }}',
            host: '{{ request()->get('host') }}',
            apiKey: '{{ \Osiset\ShopifyApp\Util::getShopifyConfig('api_key') }}',
        };
    </script>
</body>
</html>
