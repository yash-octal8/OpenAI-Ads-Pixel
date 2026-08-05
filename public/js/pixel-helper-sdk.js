/**
 * Pixel Helper SDK for Shopify Storefront & OpenAI Ads Pixel Tracking
 * Automatically tracks page views, product views, add-to-cart, checkout, and order confirmations.
 */
(function (window, document) {
  'use strict';

  var PixelHelperSDK = {
    pixelId: '',
    appName: 'Pixel for OpenAI Ads',
    events: [],

    init: function (config) {
      if (config && config.pixelId) {
        this.pixelId = config.pixelId;
      }
      this.bindStorefrontEvents();
      this.track('page_viewed', {
        page_title: document.title,
        page_location: window.location.href,
        referrer: document.referrer
      });
      console.log('[Pixel Helper SDK] Initialized with Pixel ID:', this.pixelId);
    },

    track: function (eventName, payload, eventType) {
      var event = {
        id: 'evt_' + Date.now() + '_' + Math.floor(Math.random() * 1000),
        pixel_id: this.pixelId,
        event_name: eventName,
        event_type: eventType || 'Standard',
        event_time: new Date().toTimeString().split(' ')[0],
        payload: payload || {},
        status: 'Loaded'
      };

      this.events.unshift(event);

      // Post message for Pixel Helper overlay window
      window.postMessage({
        type: 'PIXEL_HELPER_EVENT',
        event: event
      }, '*');

      // Send telemetry event to app API endpoint (POST /api/events)
      try {
        fetch('/api/events', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(event)
        }).catch(function () {
          // Fallback silence if offline
        });
      } catch (e) {}
    },

    bindStorefrontEvents: function () {
      var self = this;

      // Add to cart tracking
      document.addEventListener('click', function (e) {
        var target = e.target;
        if (!target) return;

        var isAddToCart = target.matches('[name="add"], .add-to-cart, [data-add-to-cart], button[type="submit"]') ||
                          (target.innerText && target.innerText.toLowerCase().indexOf('add to cart') !== -1);

        if (isAddToCart) {
          self.track('add_to_cart', {
            url: window.location.href,
            title: document.title,
            timestamp: new Date().toISOString()
          });
        }

        var isCheckout = target.matches('a[href*="/checkout"], [name="checkout"], .checkout-button') ||
                         (target.innerText && target.innerText.toLowerCase().indexOf('checkout') !== -1);

        if (isCheckout) {
          self.track('checkout_started', {
            url: window.location.href,
            timestamp: new Date().toISOString()
          });
        }
      });
    }
  };

  window.PixelHelperSDK = PixelHelperSDK;

  // Auto-init on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      PixelHelperSDK.init();
    });
  } else {
    PixelHelperSDK.init();
  }

})(window, document);
