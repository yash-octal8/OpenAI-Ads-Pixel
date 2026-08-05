import { register } from "@shopify/web-pixels-extension";

register(({ analytics, browser, init, settings }) => {
  const pixelId = settings.pixel_id || "";
  const appEndpoint = "https://openai-ads-pixel.test/api/events";

  console.log("[OpenAI Web Pixel] Customer Events initialized with Pixel ID:", pixelId);

  // Helper to publish event to backend app database & window overlay
  const broadcastEvent = (name, payload, type = "Standard") => {
    const evtData = {
      id: "evt_" + Date.now() + "_" + Math.floor(Math.random() * 1000),
      pixel_id: pixelId,
      event_name: name,
      event_type: type,
      event_time: new Date().toTimeString().split(" ")[0],
      payload: payload || {},
      status: "Loaded",
    };

    // 1. Post to App Backend API so Dashboard metrics & live counts update automatically
    try {
      fetch(appEndpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          pixel_id: pixelId,
          event_name: name,
          event_type: type,
          payload: payload || {},
        }),
        mode: "cors",
        keepalive: true,
      }).catch((err) => console.log("[Web Pixel] API fetch error:", err));
    } catch (e) {
      console.log("[Web Pixel] Fetch failed:", e);
    }

    // 2. Forward to merchant theme floating Pixel Helper via browser CustomEvent
    try {
      if (typeof window !== "undefined") {
        const customEvt = new CustomEvent("ShopifyPixelHelperEvent", { detail: evtData });
        window.dispatchEvent(customEvt);
      }
    } catch (e) {
      console.log("[Web Pixel] Local event broadcasted:", evtData);
    }
  };

  // 1. Page Viewed Customer Event
  analytics.subscribe("page_viewed", (event) => {
    console.log("[Customer Event] page_viewed:", event);
    broadcastEvent("page_viewed", {
      page_title: event.context.document.title,
      page_location: event.context.document.location.href,
      referrer: event.context.document.referrer,
      timestamp: event.timestamp,
    });
  });

  // 2. Product Viewed Customer Event
  analytics.subscribe("product_viewed", (event) => {
    console.log("[Customer Event] product_viewed:", event);
    const variant = event.data.productVariant;
    broadcastEvent("product_viewed", {
      product_id: variant?.product?.id,
      title: variant?.product?.title,
      price: variant?.price?.amount,
      currency: variant?.price?.currencyCode,
      sku: variant?.sku,
      timestamp: event.timestamp,
    });
  });

  // 3. Product Added to Cart Customer Event
  analytics.subscribe("product_added_to_cart", (event) => {
    console.log("[Customer Event] product_added_to_cart:", event);
    const cartLine = event.data.cartLine;
    broadcastEvent("add_to_cart", {
      cart_id: event.data.cartLine?.id,
      product_id: cartLine?.merchandise?.product?.id,
      title: cartLine?.merchandise?.product?.title,
      quantity: cartLine?.quantity,
      price: cartLine?.cost?.totalAmount?.amount,
      currency: cartLine?.cost?.totalAmount?.currencyCode,
      timestamp: event.timestamp,
    });
  });

  // 4. Checkout Started Customer Event
  analytics.subscribe("checkout_started", (event) => {
    console.log("[Customer Event] checkout_started:", event);
    const checkout = event.data.checkout;
    broadcastEvent("checkout_started", {
      checkout_id: checkout?.token,
      currency: checkout?.currencyCode,
      subtotal: checkout?.subtotalPrice?.amount,
      total_price: checkout?.totalPrice?.amount,
      item_count: checkout?.lineItems?.length,
      timestamp: event.timestamp,
    });
  });

  // 5. Checkout Completed (Order Completed) Customer Event
  analytics.subscribe("checkout_completed", (event) => {
    console.log("[Customer Event] checkout_completed:", event);
    const checkout = event.data.checkout;
    broadcastEvent("order_completed", {
      order_id: checkout?.order?.id,
      checkout_id: checkout?.token,
      currency: checkout?.currencyCode,
      total_price: checkout?.totalPrice?.amount,
      subtotal: checkout?.subtotalPrice?.amount,
      tax: checkout?.totalTax?.amount,
      timestamp: event.timestamp,
    });
  });
});
