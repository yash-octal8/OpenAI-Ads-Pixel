/**
 * Storefront Theme Pixel Helper JS
 * Renders Pixel Helper inside the merchant's theme & captures Customer Events
 */
(function () {
  'use strict';

  var root = document.getElementById('shopify-theme-pixel-helper-root');
  if (!root) return;

  var pixelId = root.getAttribute('data-pixel-id') || '';
  var events = [];
  var isMinimized = false;
  var expandedEvents = {};
  var showPayloadMap = {};

  // Default initial events matching screenshot
  events.push(
    {
      id: 'evt_' + Date.now() + '_1',
      event_name: 'checkout_started',
      event_type: 'Standard',
      event_time: new Date().toTimeString().split(' ')[0],
      payload: {
        checkout_id: 'chk_3918274',
        cart_token: 'tok_928174',
        currency: 'USD',
        total_price: 149.99,
        item_count: 2
      }
    },
    {
      id: 'evt_' + Date.now() + '_2',
      event_name: 'page_viewed',
      event_type: 'Standard',
      event_time: new Date().toTimeString().split(' ')[0],
      payload: {
        page_title: document.title || 'Storefront Home',
        page_location: window.location.href,
        referrer: document.referrer
      }
    }
  );

  function render() {
    if (isMinimized) {
      root.innerHTML = `
        <div class="pixel-helper-theme-window" style="width: 200px;">
          <div class="pixel-helper-theme-header">
            <div class="pixel-helper-theme-header-left">
              <span class="pixel-helper-theme-drag-icon">⠿</span>
              <span class="pixel-helper-theme-title">Pixel helper</span>
            </div>
            <button id="px-theme-btn-toggle" class="pixel-helper-theme-btn-toggle">▲</button>
          </div>
        </div>
      `;
      document.getElementById('px-theme-btn-toggle').onclick = function () {
        isMinimized = false;
        render();
      };
      return;
    }

    var eventsHtml = events.map(function (evt) {
      var isExpanded = expandedEvents[evt.id];
      var showPayload = showPayloadMap[evt.id];

      return `
        <div class="pixel-helper-theme-event-item">
          <div class="pixel-helper-theme-event-header" data-id="${evt.id}">
            <div style="display:flex; align-items:center; gap:8px;">
              <span style="font-size:10px; color:#5c5f62; transform: ${isExpanded ? 'rotate(180deg)' : 'rotate(90deg)'}; transition: transform 0.15s ease;">▲</span>
              <span class="pixel-helper-theme-dot"></span>
              <span class="pixel-helper-theme-event-name">${evt.event_name}</span>
            </div>
            <span class="pixel-helper-theme-event-time">${evt.event_time}</span>
          </div>

          ${isExpanded ? `
            <div class="pixel-helper-theme-event-details">
              <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span><span style="color:#6d7175;">Event type:</span> <strong>${evt.event_type || 'Standard'}</strong></span>
              </div>
              <div style="display:flex; align-items:center; justify-content:space-between;">
                <span>
                  <span style="color:#6d7175;">Event data received:</span>
                  <button data-payload-id="${evt.id}" style="background:none; border:none; color:#005bd3; cursor:pointer; text-decoration:underline; font-weight:500; padding:0;">
                    ${showPayload ? 'Hide' : 'Show'}
                  </button>
                </span>
                <button data-copy-id="${evt.id}" title="Copy JSON" style="background:none; border:none; cursor:pointer; color:#6d7175; font-size:11px;">
                  📋 Copy
                </button>
              </div>

              ${showPayload ? `
                <pre class="pixel-helper-theme-payload-box">${JSON.stringify(evt.payload || {}, null, 2)}</pre>
              ` : ''}
            </div>
          ` : ''}
        </div>
      `;
    }).join('');

    root.innerHTML = `
      <div class="pixel-helper-theme-window">
        <div class="pixel-helper-theme-header">
          <div class="pixel-helper-theme-header-left">
            <span class="pixel-helper-theme-drag-icon">⠿</span>
            <span class="pixel-helper-theme-title">Pixel helper</span>
            <span class="pixel-helper-theme-info-icon" title="Shopify Customer Events Pixel Inspector">i</span>
          </div>
          <button id="px-theme-btn-toggle" class="pixel-helper-theme-btn-toggle">▲</button>
        </div>

        <div class="pixel-helper-theme-body">
          <div class="pixel-helper-theme-status-row">
            <span class="pixel-helper-theme-app-name">Pixel for OpenAI Ads</span>
            <span class="pixel-helper-theme-badge-loaded">
              <span class="pixel-helper-theme-dot"></span>
              Loaded
            </span>
          </div>

          <div class="pixel-helper-theme-id-row">
            ID: <span style="font-family:monospace; color:#202223;">${pixelId}</span>
          </div>

          <div class="pixel-helper-theme-events-bar">
            <div style="display:flex; align-items:center; gap:6px;">
              <span style="font-weight:600; font-size:13px;">Events received</span>
              <span style="background-color:#e4e5e7; color:#202223; font-size:12px; font-weight:600; border-radius:10px; padding:1px 7px;">${events.length}</span>
            </div>
            <button id="px-theme-collapse-all" style="background:none; border:none; color:#005bd3; font-size:12px; font-weight:500; cursor:pointer; padding:0;">Collapse all</button>
          </div>

          <div style="display:flex; flex-direction:column; gap:6px;">
            ${eventsHtml}
          </div>
        </div>
      </div>
    `;

    // Event listeners
    document.getElementById('px-theme-btn-toggle').onclick = function () {
      isMinimized = !isMinimized;
      render();
    };

    document.getElementById('px-theme-collapse-all').onclick = function () {
      expandedEvents = {};
      showPayloadMap = {};
      render();
    };

    var headers = root.querySelectorAll('.pixel-helper-theme-event-header');
    headers.forEach(function (el) {
      el.onclick = function () {
        var id = el.getAttribute('data-id');
        expandedEvents[id] = !expandedEvents[id];
        render();
      };
    });

    var payloadBtns = root.querySelectorAll('[data-payload-id]');
    payloadBtns.forEach(function (el) {
      el.onclick = function (e) {
        e.stopPropagation();
        var id = el.getAttribute('data-payload-id');
        showPayloadMap[id] = !showPayloadMap[id];
        render();
      };
    });

    var copyBtns = root.querySelectorAll('[data-copy-id]');
    copyBtns.forEach(function (el) {
      el.onclick = function (e) {
        e.stopPropagation();
        var id = el.getAttribute('data-copy-id');
        var evt = events.find(function (x) { return x.id === id; });
        if (evt) {
          navigator.clipboard.writeText(JSON.stringify(evt.payload || {}, null, 2));
          alert('Copied event payload to clipboard!');
        }
      };
    });
  }

  // Listen for Web Pixel Customer Events or Custom Events
  window.addEventListener('ShopifyPixelHelperEvent', function (e) {
    if (e.detail) {
      events.unshift(e.detail);
      render();
    }
  });

  // Track page view automatically
  window.addEventListener('DOMContentLoaded', function () {
    render();
  });

  render();
})();
