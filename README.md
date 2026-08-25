# OpenAI Ads Pixel — Shopify Application

A high-performance embedded Shopify application designed to integrate OpenAI Ads & Conversions API (CAPI) with Shopify storefronts.

Automatically tracks storefront buyer events (PageView, ViewContent, AddToCart, InitiateCheckout, Purchase, Search) via Shopify's native Customer Events Web Pixel API and securely forwards conversion data to OpenAI Ads Manager.

---

## 🚀 Features

- **Customer Events Web Pixel**: Uses Shopify's native `web_pixel_extension` for privacy-compliant storefront tracking.
- **OpenAI Conversions API (CAPI)**: Server-side event forwarding for improved tracking accuracy and conversion optimization.
- **Embedded SPA UI**: Modern React frontend styled with `@shopify/polaris` design tokens.
- **Access Scopes Diagnostic CLI**: Built-in Artisan commands to inspect granted Shopify API scopes and trigger OAuth re-authentication.
- **Automated Billing & Plans**: Integrated freemium & subscription plan management.
- **Clean Uninstallation**: Listens to `APP_UNINSTALLED` webhook to automatically prune store data upon app uninstallation.

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.2+, Laravel 11, `kyon147/laravel-shopify`
- **Frontend**: React 18, Shopify Polaris, Vite
- **Extension**: Shopify CLI Web Pixel Extension (`web_pixel_extension`)
- **Database**: MySQL 8.0+

---

## ⚙️ Prerequisites & Configuration

### Required Access Scopes
Ensure the following scopes are configured in both `shopify.app.open-ai-ads-pixel.toml` and your **Shopify Partner Dashboard** under **Apps > Open Ai Ads Pixel > API Access**:

```toml
[access_scopes]
scopes = "read_customer_events,read_products,write_products,read_themes,write_themes,read_pixels,write_pixels"
```

## 💻 Installation & Setup

1. **Clone the Repository**:
   ```bash
   git clone <repository-url>
   cd openAI-ads-pixel
   ```

2. **Install PHP & Node Dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Run Database Migrations**:
   ```bash
   php artisan migrate
   ```

4. **Build Production Assets**:
   ```bash
   npm run build
   ```

---
