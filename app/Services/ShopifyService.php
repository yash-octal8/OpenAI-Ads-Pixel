<?php

namespace App\Services;

use App\Helpers\ShopifyHelper;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\Internal\SettingRepository;

class ShopifyService
{
  protected User $shop;

  public function __construct(?User $shop)
  {
    $this->shop = $shop;
  }

  public function getShopDetails(): array
  {
    $query = <<<'GQL'
        {
            shop {
                id
                name
                email
                contactEmail
                ianaTimezone
                primaryDomain {
                   url
                }
                plan {
                   partnerDevelopment
                   shopifyPlus
                   publicDisplayName
                }
            }
        }
        GQL;

    return $this->execute($query);
  }

  protected static array $syncedPixels = [];

  public function getWebPixels(): array
  {
    $query = <<<'GQL'
        query getWebPixels {
          webPixels(first: 10) {
            nodes {
              id
              settings
            }
            edges {
              node {
                id
                settings
              }
            }
          }
        }
    GQL;

    return $this->execute($query);
  }

  public function getWebPixelId(): ?string
  {
    $res = $this->getWebPixels();
    $arrayData = json_decode(json_encode($res), true);

    $nodes = $arrayData['body']['data']['webPixels']['nodes']
      ?? ($arrayData['data']['webPixels']['nodes'] ?? []);
    if (!empty($nodes) && isset($nodes[0]['id'])) {
      return $nodes[0]['id'];
    }

    $edges = $arrayData['body']['data']['webPixels']['edges']
      ?? ($arrayData['data']['webPixels']['edges'] ?? []);
    if (!empty($edges) && isset($edges[0]['node']['id'])) {
      return $edges[0]['node']['id'];
    }

    return null;
  }

  public function clearPixelCache(): void
  {
    if ($this->shop) {
      \Illuminate\Support\Facades\Cache::forget("web_pixel_synced_{$this->shop->id}");
      \Illuminate\Support\Facades\Cache::forget("scope_denied_write_pixels_{$this->shop->id}");
    }
  }

  public function syncWebPixel(string $pixelId, bool $force = false): array
  {
    if (!$this->shop) {
      return ['errors' => true, 'message' => 'Shop reference missing'];
    }

    if (empty($pixelId)) {
      return ['errors' => false, 'message' => 'Pixel ID empty'];
    }

    $shopCacheKey = "web_pixel_synced_{$this->shop->id}";
    if (!$force && \Illuminate\Support\Facades\Cache::has($shopCacheKey)) {
      return ['success' => true, 'message' => 'Web pixel already active on shop'];
    }

    $guardKey = $this->shop->id . '_' . $pixelId;
    if (isset(self::$syncedPixels[$guardKey])) {
      return ['errors' => false, 'message' => 'Already synced in current request'];
    }
    self::$syncedPixels[$guardKey] = true;

    \Illuminate\Support\Facades\Cache::put($shopCacheKey, true, now()->addHours(1));

    $existingId = $this->getWebPixelId();
    if ($existingId) {
      return $this->updateWebPixel($existingId, $pixelId);
    }

    return $this->createWebPixel($pixelId);
  }

  public function updateWebPixel(string $webPixelGid, string $pixelId): array
  {
    $mutation = <<<'GQL'
        mutation webPixelUpdate($id: ID!, $webPixel: WebPixelInput!) {
          webPixelUpdate(id: $id, webPixel: $webPixel) {
            userErrors {
              field
              message
            }
            webPixel {
              id
              settings
            }
          }
        }
    GQL;

    $input = [
      'id' => $webPixelGid,
      'webPixel' => [
        'settings' => json_encode(['pixel_id' => $pixelId]),
      ],
    ];

    $res = $this->execute($mutation, $input);
    
    if ($this->shop) {
      \Illuminate\Support\Facades\Cache::put("web_pixel_created_{$this->shop->id}_{$pixelId}", true, now()->addDays(30));
    }
    return $res;
  }

  public function createWebPixel(string $pixelId): array
  {
    $mutation = <<<'GQL'
        mutation webPixelCreate($webPixel: WebPixelInput!) {
          webPixelCreate(webPixel: $webPixel) {
            userErrors {
              field
              message
            }
            webPixel {
              id
              settings
            }
          }
        }
    GQL;

    $input = [
      'webPixel' => [
        'settings' => json_encode(['pixel_id' => $pixelId]),
      ],
    ];

    $res = $this->execute($mutation, $input);

    // Check for access denied errors
    $graphQLErrors = $res['body']['errors'] ?? ($res['errors'] ?? []);
    if (!empty($graphQLErrors)) {
      foreach ($graphQLErrors as $err) {
        $msg = strtolower($err['message'] ?? '');
        if (str_contains($msg, 'access denied') || str_contains($msg, 'write_pixels') || str_contains($msg, 'read_customer_events')) {
          if ($this->shop) {
            \Illuminate\Support\Facades\Cache::put("scope_denied_write_pixels_{$this->shop->id}", true, now()->addMinutes(10));
          }
          \Log::warning("Web Pixel registration blocked: Shopify Access Denied for write_pixels / read_customer_events scope. Store re-authentication required.");
        }
      }
    }

    // If pixel already exists, cache that it is active and exit cleanly
    $userErrors = $res['body']['data']['webPixelCreate']['userErrors'] ?? ($res['data']['webPixelCreate']['userErrors'] ?? []);
    if (!empty($userErrors)) {
      foreach ($userErrors as $err) {
        $msg = strtolower($err['message'] ?? '');
        if (str_contains($msg, 'already exists') || str_contains($msg, 'already been set') || str_contains($msg, 'update mutation') || str_contains($msg, 'taken')) {
          if ($this->shop) {
            \Illuminate\Support\Facades\Cache::put("web_pixel_created_{$this->shop->id}_{$pixelId}", true, now()->addDays(30));
          }
          return ['success' => true, 'message' => 'Web pixel already active on shop'];
        }
      }
    }

    $webPixel = $res['body']['data']['webPixelCreate']['webPixel'] ?? ($res['data']['webPixelCreate']['webPixel'] ?? null);
    if ($webPixel && $this->shop) {
      \Illuminate\Support\Facades\Cache::put("web_pixel_created_{$this->shop->id}_{$pixelId}", true, now()->addDays(30));
    }

    return $res;
  }

  public function getThemes($themeQueries, $fileQueries = "")
  {
    $themeFields = '
            id
            name
            role';

    if ($fileQueries) {
      $themeFields .= '
            files(' . $fileQueries . ') {
              nodes {
                filename
                body {
                  ... on OnlineStoreThemeFileBodyText {
                    content
                  }
                }
              }
            }
            ';
    }
    $query = <<<QUERY
        query {
          themes($themeQueries) {
            edges {
              node {
                $themeFields
              }
            }
          }
        }
        QUERY;

    return $this->execute($query);
  }

  public function execute($query, $input = [])
  {
    $response = [];
    $retry = 3;
    do {
      try {
        $response = empty($input) ?
          $this->shop->api()->graph($query) :
          $this->shop->api()->graph($query, $input);

        $response = json_decode(json_encode($response), true);
        $retry = 0;
      } catch (\Throwable $e) {
        $retry--;
        \Log::error("Shopify GraphQL execute exception: " . $e->getMessage() . " File: " . $e->getFile() . ":" . $e->getLine());

        if ($retry <= 0) {
          $response = [
            'errors' => true,
            'body' => $e->getMessage(),
          ];
        }
        sleep(1);
      }
    } while ($retry > 0);

    return $response;
  }
}
