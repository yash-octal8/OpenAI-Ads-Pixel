<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyGraphQLService
{
    private string $shopDomain;
    private ?string $accessToken;

    public function __construct(string $shopDomain, ?string $accessToken = null)
    {
        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;
    }

    private function ensureFreshToken(): void
    {
        $user = User::where('name', $this->shopDomain)->first();
        if ($user) {
            $expiresAt = $user->shopify_offline_access_token_expires_at ?: $user->access_token_expires_at;
            if ($expiresAt && $expiresAt->subMinutes(10)->isPast()) {
                if ($user->refreshAccessToken()) {
                    $user->refresh();
                    $this->accessToken = $user->password;
                }
            }
        }
    }

    public function query(string $query, array $variables = [])
    {
      $this->ensureFreshToken();
      $maxAttempts = 6;
      $attempt = 0;
  
      do {
        $attempt++;
  
        $apiVersion = config('shopify-app.api_version', '2024-07');
        $response = Http::timeout(120)->withHeaders([
          'X-Shopify-Access-Token' => $this->accessToken,
          'Content-Type' => 'application/json',
        ])->post("https://{$this->shopDomain}/admin/api/{$apiVersion}/graphql.json", [
          'query' => $query,
          'variables' => (object) $variables
        ]);
  
        $status = $response->status();
        $retryAfter = (int) ($response->header('Retry-After') ?? 0);
        $data = $response->json();
  
        if (!$response->successful()) {
          if (($status === 429 || ($status >= 500 && $status < 600)) && $attempt < $maxAttempts) {
            $sleep = max($retryAfter, min(60, (int) pow(2, $attempt)));
            Log::warning('Shopify GraphQL retry (HTTP)', ['status' => $status, 'attempt' => $attempt, 'sleep' => $sleep]);
            sleep($sleep);
            continue;
          }
  
          Log::error('Shopify GraphQL Error', [
            'status' => $status,
            'body' => $response->body(),
            'shop' => $this->shopDomain
          ]);
          throw new \Exception('GraphQL request failed: ' . $response->body());
        }
  
        if (isset($data['errors']) && is_array($data['errors'])) {
          $throttled = false;
          foreach ($data['errors'] as $err) {
            if (($err['extensions']['code'] ?? null) === 'THROTTLED') {
              $throttled = true;
              break;
            }
          }
  
          if ($throttled && $attempt < $maxAttempts) {
            $throttleStatus = $data['extensions']['cost']['throttleStatus'] ?? null;
            $sleep = $retryAfter ?: min(60, (int) pow(2, $attempt));
            if ($throttleStatus && isset($throttleStatus['restoreRate'])) {
              $sleep = max($sleep, 1 + (int) ceil(100 / (int) $throttleStatus['restoreRate']));
            }
            Log::warning('Shopify GraphQL retry (THROTTLED)', ['attempt' => $attempt, 'sleep' => $sleep]);
            sleep($sleep);
            continue;
          }
  
          Log::error('Shopify GraphQL Errors', $data['errors']);
          throw new \Exception('GraphQL errors: ' . json_encode($data['errors']));
        }
  
        return $data['data'];
      } while ($attempt < $maxAttempts);
  
      throw new \Exception('GraphQL request failed after max retries');
    }
    
    public function getRestProductsCount(): int
    {
        try {
            $this->ensureFreshToken();
            $apiVersion = config('shopify-app.api_version', '2024-07');
            $response = Http::timeout(30)->withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
            ])->get("https://{$this->shopDomain}/admin/api/{$apiVersion}/products/count.json");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['count'])) {
                    return (int) $data['count'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed REST products/count query: ' . $e->getMessage());
        }

        return 0;
    }

    public function getProductsCount(): int
    {
        try {
            $query = 'query { productsCount { count } }';
            $res = $this->query($query);
            if (isset($res['productsCount']['count'])) {
                $count = (int) $res['productsCount']['count'];
                if ($count >= 10000) {
                    $restCount = $this->getRestProductsCount();
                    if ($restCount > 0) {
                        return $restCount;
                    }
                }
                return $count;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed productsCount query: ' . $e->getMessage());
        }

        $restCount = $this->getRestProductsCount();
        if ($restCount > 0) {
            return $restCount;
        }

        // Fallback: fast count from first page
        try {
            $res = $this->getProducts(250);
            return count($res['products']['edges'] ?? []);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // Add method to get actual counts
    public function getActualCounts(): array
    {
        try {
            $query = '
                query getCounts {
                  productsCount { count }
                  collectionsCount { count }
                  pagesCount { count }
                }
            ';
            $res = $this->query($query);

            $productCount = (int) ($res['productsCount']['count'] ?? 0);
            if ($productCount >= 10000 || !isset($res['productsCount']['count'])) {
                $productCount = $this->getProductsCount();
            }

            return [
                'products' => $productCount,
                'collections' => (int) ($res['collectionsCount']['count'] ?? $this->getTotalCollectionCount()),
                'blogs' => $this->getTotalBlogCount(),
                'pages' => (int) ($res['pagesCount']['count'] ?? $this->getTotalPageCount()),
            ];
        } catch (\Throwable $e) {
            Log::warning('Fast getCounts query failed, using safe fallback', ['error' => $e->getMessage()]);
            return [
                'products' => $this->getProductsCount(),
                'collections' => $this->getTotalCollectionCount(),
                'blogs' => $this->getTotalBlogCount(),
                'pages' => $this->getTotalPageCount(),
            ];
        }
    }

    private function getTotalProductCount(): int
    {
        return $this->getProductsCount();
    }

    private function getTotalCollectionCount(): int
    {
        $count = 0;
        $cursor = null;
        $pages = 0;
        
        do {
            $pages++;
            $response = $this->getCollections(250);
            $newCollections = count($response['collections']['edges'] ?? []);
            $count += $newCollections;
            
            $cursor = $response['collections']['pageInfo']['hasNextPage'] ?? false
                ? $response['collections']['pageInfo']['endCursor'] 
                : null;
        } while ($cursor && $pages < 20); // Cap at max 20 pages (5000 items)
        
        return $count;
    }

    private function getTotalBlogCount(): int
    {
        $response = $this->getBlogs(250);
        $count = 0;
        if (isset($response['blogs']['edges']) && is_array($response['blogs']['edges'])) {
            foreach ($response['blogs']['edges'] as $edge) {
                if (isset($edge['node']['articles']['edges']) && is_array($edge['node']['articles']['edges'])) {
                    $count += count($edge['node']['articles']['edges']);
                }
            }
        }
        return $count;
    }

    private function getTotalPageCount(): int
    {
        $count = 0;
        $cursor = null;
        $pages = 0;
        
        do {
            $pages++;
            $response = $this->getPages(250, $cursor);
            $newPages = count($response['pages']['edges'] ?? []);
            $count += $newPages;
            
            $cursor = $response['pages']['pageInfo']['hasNextPage'] ?? false
                ? $response['pages']['pageInfo']['endCursor'] 
                : null;
        } while ($cursor && $pages < 20);
        
        return $count;
    }

    public function getProducts(int $limit = 100, ?string $cursor = null, ?string $searchQuery = null): array
    {
        $query = '
            query getProducts($first: Int!, $after: String, $query: String) {
  products(first: $first, after: $after, query: $query, sortKey: ID) {
    edges {
      node {
        id
        title
        handle
        description
        vendor
        productType
        tags
        status
        images(first: 1) {
          edges {
            node {
              url
              altText
            }
          }
        }
        options {
          name
          values
        }
        variants(first: 100) {
          edges {
            node {
              id
              title
              price
              compareAtPrice
              availableForSale
              sku
              barcode
              inventoryQuantity
              inventoryItem {
                measurement {
                  weight {
                    value
                    unit
                  }
                }
              }
              selectedOptions {
                name
                value
              }
              image {
                url
                altText
              }
            }
          }
        }
        seoTitle: metafield(namespace: "global", key: "title_tag") { value }
        seoDescription: metafield(namespace: "global", key: "description_tag") { value }
        createdAt
        updatedAt
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
        ';

        return $this->query($query, [
            'first' => $limit,
            'after' => $cursor,
            'query' => $searchQuery
        ]);
    }

    public function createBulkProductOperation(): array
    {
        $mutation = '
            mutation {
              bulkOperationRunQuery(
                query: """
                {
                  products {
                    edges {
                      node {
                        id
                        title
                        handle
                        description
                        vendor
                        productType
                        tags
                        status
                        createdAt
                        updatedAt
                        images(first: 1) {
                          edges {
                            node {
                              url
                              altText
                            }
                          }
                        }
                        options {
                          name
                          values
                        }
                        variants {
                          edges {
                            node {
                              id
                              title
                              price
                              compareAtPrice
                              availableForSale
                              sku
                              barcode
                              inventoryQuantity
                              inventoryItem {
                                measurement {
                                  weight {
                                    value
                                    unit
                                  }
                                }
                              }
                            }
                          }
                        }
                        seoTitle: metafield(namespace: "global", key: "title_tag") { value }
                        seoDescription: metafield(namespace: "global", key: "description_tag") { value }
                      }
                    }
                  }
                }
                """
              ) {
                bulkOperation {
                  id
                  status
                  createdAt
                }
                userErrors {
                  field
                  message
                }
              }
            }
        ';

        return $this->query($mutation);
    }

    public function getBulkOperationStatus(): array
    {
        $query = '
            query {
              currentBulkOperation {
                id
                status
                errorCode
                createdAt
                completedAt
                objectCount
                fileSize
                url
                partialDataUrl
              }
            }
        ';

        return $this->query($query);
    }

    public function downloadBulkOperationResults(string $url): string
    {
        $response = Http::timeout(300)->get($url);
        if (!$response->successful()) {
            throw new \Exception("Failed to download bulk operation results from {$url}: " . $response->status());
        }
        return $response->body();
    }


    public function getCollections(int $limit = 50): array
    {
        $query = '
            query getCollections($first: Int!) {
  collections(first: $first) {
    edges {
      node {
        id
        title
        handle
        description
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
        ';

        return $this->query($query, ['first' => $limit]);
    }

    public function getBlogs(int $limit = 10): array
    {
        $query = '
            query getBlogs($first: Int!) {
  blogs(first: $first) {
    edges {
      node {
        id
        title
        handle
        articles(first: 50) {
          edges {
            node {
              id
              title
              handle
              author {
                name
              }
              publishedAt
              tags
              body
              image {
                url
                altText
              }
            }
          }
        }
      }
    }
  }
}
        ';

        return $this->query($query, ['first' => $limit]);
    }

    public function getPages(int $limit = 50): array
    {
        $query = '
            query getPages($first: Int!) {
                pages(first: $first) {
                    edges {
                        node {
                            id
                            title
                            handle
                            bodySummary
                            body
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        return $this->query($query, ['first' => $limit]);
    }

    public function getPageByHandle(string $handle): array
    {
        $query = '
            query getPageByHandle($handle: String!) {
                pageByHandle(handle: $handle) {
                    id
                    title
                    handle
                    body
                }
            }
        ';

        return $this->query($query, ['handle' => $handle]);
    }

    public function getShop(): array
    {
        $query = '
            query getShop {
  shop {
    id
    name
    primaryDomain {
      url
      host
    }
    description
  }
}
        ';

        return $this->query($query);
    }

    public function createMetaobjectDefinition(array $definition): array
    {
        $query = '
            mutation metaobjectDefinitionCreate($definition: MetaobjectDefinitionCreateInput!) {
  metaobjectDefinitionCreate(definition: $definition) {
    metaobjectDefinition {
      id
      type
      name
      fieldDefinitions {
        key
        name
        type {
          name
        }
      }
    }
    userErrors {
      field
      message
    }
  }
}
        ';

        return $this->query($query, ['definition' => $definition]);
    }
    public function getMetaobjects(string $type, int $limit = 50): array
    {
        $query = '
            query getMetaobjects($type: String!, $first: Int!) {
  metaobjects(type: $type, first: $first) {
    edges {
      node {
        id
        type
        handle
        fields {
          key
          value
        }
      }
    }
  }
}
        ';

        return $this->query($query, [
            'type' => $type,
            'first' => $limit
        ]);
    }

    /**
     * Check if the shop has a specific access scope.
     *
     * @param string $scope
     * @return bool
     */
    public function hasScope(string $scope): bool
    {
        try {
            $cacheKey = "{$this->shopDomain}.currentScopes";
            $scopes = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(6), function () {
                $query = '
                    query {
                      currentAppInstallation {
                        accessScopes {
                          handle
                        }
                      }
                    }
                ';
                $result = $this->query($query);
                $scopesList = $result['currentAppInstallation']['accessScopes'] ?? [];
                return array_column($scopesList, 'handle');
            });

            return in_array($scope, $scopes, true);
        } catch (\Exception $e) {
            Log::error('Failed to check access scope', [
                'shop' => $this->shopDomain,
                'scope' => $scope,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if the app embed is enabled.
     *
     * @param User|null $shop
     * @return bool
     */
    public function isAppEmbedEnabled(?User $shop, bool $forceRefresh = false): bool
    {
        try {
            $cacheKey = "app_embed_status_{$this->shopDomain}";

            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }

            return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($shop) {
                $shopifyService = new ShopifyService($shop);

                // Fetch theme data ONCE via GraphQL
                $response = $shopifyService->getThemes(
                    'first: 1, roles: [MAIN]',
                    'first: 5, filenames: ["config/settings_data.json", "templates/product.json"]',
                );

                $theme = data_get($response, 'body.data.themes.edges.0.node') ?: [];
                $files = collect(data_get($theme, 'files.nodes') ?: []);

                $settingsDataFile = $files->firstWhere('filename', 'config/settings_data.json');

                $settingsBlocks = [];

                if ($settingsDataFile) {
                    $content = data_get($settingsDataFile, 'body.content');
                    $schemaData = prepareThemeSchemaFileJson($content);
                    $settingsBlocks = data_get($schemaData, 'current.blocks') ?: [];
                }


                // 1. Check for App Embed (dynamic login block)
                $embedTarget = trim(config('shopify-app.theme_extension_id'));
                $apiKey = trim(config('shopify-app.api_key'));
                $dynamicLoginAppBlock = null;

                // Pass 1: Match exactly by extension ID (embedTarget)
                if ($embedTarget) {
                    foreach ($settingsBlocks as $appBlock) {
                        $type = data_get($appBlock, 'type') ?: "";
                        if (str_contains($type, $embedTarget)) {
                            Log::info("Pass 1 Match:", ['block' => $appBlock]);
                            $dynamicLoginAppBlock = $appBlock;
                            break;
                        }
                    }
                }

                // Pass 2: Match exactly by App Client API key
                if (is_null($dynamicLoginAppBlock) && $apiKey) {
                    foreach ($settingsBlocks as $appBlock) {
                        $type = data_get($appBlock, 'type') ?: "";
                        if (str_contains($type, $apiKey)) {
                            Log::info("Pass 2 Match:", ['block' => $appBlock]);
                            $dynamicLoginAppBlock = $appBlock;
                            break;
                        }
                    }
                }

                // Pass 3: Fallback to keyword match if exact match not found
                if (is_null($dynamicLoginAppBlock)) {
                    foreach ($settingsBlocks as $appBlock) {
                        $type = data_get($appBlock, 'type') ?: "";
                        if (str_contains($type, 'llms-txt-meta') || str_contains($type, 'llms-txt')) {
                            Log::info("Pass 3 Match:", ['block' => $appBlock]);
                            $dynamicLoginAppBlock = $appBlock;
                            break;
                        }
                    }
                }

                if (is_null($dynamicLoginAppBlock)) {
                    return false;
                }

                $isDisabled = (bool) data_get($dynamicLoginAppBlock, 'disabled', false);

                return !$isDisabled;
            });

        } catch (\Throwable $e) {
            info("DASHBOARD_ERROR: " . $e->getMessage());
            report($e);
            return false;
        }
    }

    /**
     * Get URL redirect by path in Shopify.
     *
     * @param string $path
     * @return array|null
     */
    public function getRedirectByPath(string $path): ?array
    {
        if (!$this->hasScope('write_online_store_navigation')) {
            Log::info("Skipping getRedirectByPath for {$this->shopDomain}: missing write_online_store_navigation scope");
            return null;
        }
        try {
            $query = '
                query getRedirect($query: String!) {
                  urlRedirects(first: 1, query: $query) {
                    edges {
                      node {
                        id
                        path
                        target
                      }
                    }
                  }
                }
            ';

            $result = $this->query($query, [
                'query' => "path:\"{$path}\""
            ]);

            $edges = $result['urlRedirects']['edges'] ?? [];
            if (!empty($edges)) {
                return $edges[0]['node'];
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Shopify Redirect by path', [
                'shop' => $this->shopDomain,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
        }
        return null;
    }

    /**
     * Update an existing URL redirect in Shopify.
     *
     * @param string $id
     * @param string $path
     * @param string $target
     * @return bool
     */
    public function updateRedirect(string $id, string $path, string $target): bool
    {
        if (!$this->hasScope('write_online_store_navigation')) {
            Log::info("Skipping updateRedirect for {$this->shopDomain}: missing write_online_store_navigation scope");
            return false;
        }
        try {
            $query = '
                mutation urlRedirectUpdate($id: ID!, $urlRedirect: UrlRedirectInput!) {
                  urlRedirectUpdate(id: $id, urlRedirect: $urlRedirect) {
                    urlRedirect {
                      id
                      path
                      target
                    }
                    userErrors {
                      field
                      message
                    }
                  }
                }
            ';

            $result = $this->query($query, [
                'id' => $id,
                'urlRedirect' => [
                    'path' => $path,
                    'target' => $target,
                ]
            ]);

            $userErrors = $result['urlRedirectUpdate']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                Log::warning('Shopify Redirect Update UserErrors', [
                    'shop' => $this->shopDomain,
                    'errors' => $userErrors
                ]);
                return false;
            }

            // Log::info('Shopify Redirect Updated successfully', [
            //     'shop' => $this->shopDomain,
            //     'path' => $path,
            //     'target' => $target
            // ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update Shopify Redirect', [
                'shop' => $this->shopDomain,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create or update a URL redirect in Shopify.
     *
     * @param string $path The relative path to redirect from (e.g. /my-key.txt)
     * @param string $target The relative path or URL to redirect to (e.g. /tools/llms-full/my-key.txt)
     * @return bool
     */
    public function createRedirect(string $path, string $target): bool
    {
        if (!$this->hasScope('write_online_store_navigation')) {
            Log::info("Skipping createRedirect for {$this->shopDomain}: missing write_online_store_navigation scope");
            return false;
        }
        try {
            // First check if a redirect already exists for this path
            $existing = $this->getRedirectByPath($path);
            if ($existing) {
                // Normalize slashes for comparison
                $existingPath = '/' . trim($existing['path'], '/');
                $targetPath = '/' . trim($path, '/');
                
                if (strtolower($existingPath) === strtolower($targetPath)) {
                    if ($existing['target'] === $target) {
                        // Log::info('Shopify Redirect already exists and is correct', [
                        //     'shop' => $this->shopDomain,
                        //     'path' => $path,
                        //     'target' => $target
                        // ]);
                        return true;
                    } else {
                        Log::info('Shopify Redirect target incorrect. Updating...', [
                            'shop' => $this->shopDomain,
                            'path' => $path,
                            'old_target' => $existing['target'],
                            'new_target' => $target
                        ]);
                        return $this->updateRedirect($existing['id'], $path, $target);
                    }
                }
            }

            $query = '
                mutation urlRedirectCreate($urlRedirect: UrlRedirectInput!) {
                  urlRedirectCreate(urlRedirect: $urlRedirect) {
                    urlRedirect {
                      id
                      path
                      target
                    }
                    userErrors {
                      field
                      message
                    }
                  }
                }
            ';

            $result = $this->query($query, [
                'urlRedirect' => [
                    'path' => $path,
                    'target' => $target,
                ]
            ]);

            $userErrors = $result['urlRedirectCreate']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                foreach ($userErrors as $error) {
                    $msg = strtolower($error['message'] ?? '');
                    // Fallback in case of a racing condition or search missed it
                    if (str_contains($msg, 'taken') || str_contains($msg, 'already')) {
                        $existingFallback = $this->getRedirectByPath($path);
                        if ($existingFallback) {
                            $existingPath = '/' . trim($existingFallback['path'], '/');
                            $targetPath = '/' . trim($path, '/');
                            if (strtolower($existingPath) === strtolower($targetPath) && $existingFallback['target'] !== $target) {
                                return $this->updateRedirect($existingFallback['id'], $path, $target);
                            }
                        }
                        return true;
                    }
                    if (str_contains($msg, 'maximum number of redirects') || str_contains($msg, '100000')) {
                        Log::info("Shopify max 100k redirect limit reached for shop {$this->shopDomain}, skipping redirect creation.");
                        return false;
                    }
                }
                Log::warning('Shopify Redirect Creation UserErrors', [
                    'shop' => $this->shopDomain,
                    'errors' => $userErrors
                ]);
                return false;
            }

            // Log::info('Shopify Redirect Created successfully', [
            //     'shop' => $this->shopDomain,
            //     'path' => $path,
            //     'target' => $target
            // ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create Shopify Redirect', [
                'shop' => $this->shopDomain,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
