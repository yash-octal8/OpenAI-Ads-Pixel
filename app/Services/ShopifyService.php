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

  public function getWebPixels(): array
  {
    $query = <<<'GQL'
        query getWebPixels {
          webPixels(first: 10) {
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

  public function syncWebPixel(string $pixelId): array
  {
    if (!$this->shop) {
      return ['errors' => true, 'message' => 'Shop reference missing'];
    }

    $existingRes = $this->getWebPixels();
    $existingEdges = $existingRes['body']['data']['webPixels']['edges'] ?? ($existingRes['data']['webPixels']['edges'] ?? []);

    $settingsJson = json_encode(['pixel_id' => $pixelId]);

    if (!empty($existingEdges)) {
      $existingId = $existingEdges[0]['node']['id'];
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
        'id' => $existingId,
        'webPixel' => [
          'settings' => $settingsJson,
        ],
      ];

      $res = $this->execute($mutation, $input);
      \Log::info("webPixelUpdate GraphQL response for {$pixelId}: " . json_encode($res));
      return $res;
    } else {
      return $this->createWebPixel($pixelId);
    }
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
    \Log::info("createWebPixel GraphQL response for {$pixelId}: " . json_encode($res));

    // Check for access denied errors
    $graphQLErrors = $res['body']['errors'] ?? ($res['errors'] ?? []);
    if (!empty($graphQLErrors)) {
      foreach ($graphQLErrors as $err) {
        $msg = strtolower($err['message'] ?? '');
        if (str_contains($msg, 'access denied') || str_contains($msg, 'write_pixels') || str_contains($msg, 'read_customer_events')) {
          \Log::warning("Web Pixel registration blocked: Shopify Access Denied for write_pixels / read_customer_events scope. Store re-authentication required.");
        }
      }
    }

    // If pixel already exists, fall back to sync/update
    $userErrors = $res['body']['data']['webPixelCreate']['userErrors'] ?? ($res['data']['webPixelCreate']['userErrors'] ?? []);
    if (!empty($userErrors)) {
      foreach ($userErrors as $err) {
        $msg = strtolower($err['message'] ?? '');
        if (str_contains($msg, 'already exists') || str_contains($msg, 'already been set') || str_contains($msg, 'update mutation') || str_contains($msg, 'taken')) {
          \Log::info("Web pixel already exists on shop, falling back to update...");
          return $this->syncWebPixel($pixelId);
        }
      }
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
