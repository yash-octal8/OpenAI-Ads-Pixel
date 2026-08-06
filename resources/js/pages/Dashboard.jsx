import React, { useState, useEffect } from "react";
import {
  Page,
  Card,
  Box,
  Text,
  BlockStack,
  InlineStack,
  Banner,
  Badge,
  Frame,
  Toast,
  InlineGrid,
  Divider,
  ProgressBar,
} from "@shopify/polaris";
import api from "../api";
import { Loader } from "../components/Loader";

export default function Dashboard() {
  const [loading, setLoading] = useState(true);
  const [pixelId, setPixelId] = useState("");
  const [capiKey, setCapiKey] = useState("");
  const [events, setEvents] = useState([]);
  const [planName, setPlanName] = useState("Free");
  const [monthlyCount, setMonthlyCount] = useState(0);
  const [quotaLimit, setQuotaLimit] = useState(50000);
  const [usagePercentage, setUsagePercentage] = useState(0);
  const [quotaExceeded, setQuotaExceeded] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastError, setToastError] = useState(false);

  useEffect(() => {
    fetchPixelData();
    // Poll every 3 seconds so Page views & counts update live as shoppers browse
    const interval = setInterval(() => {
      fetchPixelDataSilently();
    }, 3000);
    return () => clearInterval(interval);
  }, []);

  const fetchPixelData = async () => {
    try {
      setLoading(true);
      await fetchPixelDataSilently();
    } catch (e) {
      console.error("Failed to load pixel data", e);
    } finally {
      setLoading(false);
    }
  };

  const fetchPixelDataSilently = async () => {
    try {
      const res = await api.get("/pixel");
      if (res.data.success) {
        if (res.data.settings?.pixel_id) setPixelId(res.data.settings.pixel_id);
        if (res.data.settings?.capi_key) setCapiKey(res.data.settings.capi_key);
        if (res.data.events) setEvents(res.data.events);
        if (res.data.plan_name) setPlanName(res.data.plan_name);
        if (res.data.monthly_event_count !== undefined) setMonthlyCount(res.data.monthly_event_count);
        setQuotaLimit(res.data.quota_limit);
        setUsagePercentage(res.data.usage_percentage || 0);
        setQuotaExceeded(Boolean(res.data.quota_exceeded));
      }
    } catch (e) {
      // silent catch for polling
    }
  };

  const handleClearEvents = async () => {
    try {
      await api.post("/pixel/clear");
      setEvents([]);
      setToastMessage("Pixel events cleared");
      setToastError(false);
    } catch (e) {
      console.error(e);
    }
  };

  if (loading) {
    return <Loader />;
  }

  // Count event categories dynamically
  const pageViewsCount = events.filter((e) =>
    ["page_viewed", "page_view"].includes(e.event_name?.toLowerCase())
  ).length;

  const productViewsCount = events.filter((e) =>
    ["product_viewed", "view_content"].includes(e.event_name?.toLowerCase())
  ).length;

  const addToCartCount = events.filter((e) =>
    ["product_added_to_cart", "add_to_cart"].includes(e.event_name?.toLowerCase())
  ).length;

  const checkoutCount = events.filter((e) =>
    ["checkout_started", "initiate_checkout"].includes(e.event_name?.toLowerCase())
  ).length;

  const orderCount = events.filter((e) =>
    ["checkout_completed", "order_completed", "purchase"].includes(
      e.event_name?.toLowerCase()
    )
  ).length;

  const isConnected = Boolean(pixelId && capiKey);

  return (
    <Frame>
      <Box style={{ padding: "2rem 1rem", margin: "0 auto", maxWidth: "1000px" }}>
        <Page
          title="Performance"
          subtitle="Real-time OpenAI Ads & Web Pixel tracking performance"
          secondaryActions={[
            {
              content: "Clear Event Logs",
              onAction: handleClearEvents,
            },
          ]}
          fullWidth
        >
          <BlockStack gap="500">
            {/* Warning Banner if CAPI key missing or rejected */}
            {/* {!isConnected && (
              <Banner title="OpenAI is rejecting your events" status="critical">
                <p>Generate a new Conversions API key in Ads Manager and paste it in Settings.</p>
              </Banner>
            )} */}

            {/* Tracking Status Card (Matching Image 1 Exact Layout) */}
            <Card radius="300">
              <Box padding="500">
                <BlockStack gap="400">
                  <InlineStack align="space-between" blockAlign="center">
                    <InlineStack gap="300" blockAlign="center">
                      <Text variant="headingMd" as="h2">
                        Tracking status
                      </Text>

                      {isConnected ? (
                        <Badge tone="success">Active</Badge>
                      ) : (
                        <Badge tone="critical">Rejected</Badge>
                      )}

                      <InlineStack gap="200" blockAlign="center">
                        <Text variant="bodySm" tone="subdued">
                          ✓ Pixel installed
                        </Text>
                        <Text variant="bodySm" tone="subdued">
                          ✓ Server-side events
                        </Text>
                        <Text variant="bodySm" tone="subdued">
                          ✓ Event taxonomy
                        </Text>
                      </InlineStack>
                    </InlineStack>

                    <Text variant="bodySm" tone="subdued">
                      Last event recently · {events.length} events, last 24h
                    </Text>
                  </InlineStack>

                  <Divider />

                  {/* 5-Column Metrics Grid */}
                  <InlineGrid columns={{ xs: 2, sm: 5 }} gap="400">
                    <Box>
                      <BlockStack gap="100">
                        <Text variant="bodySm" tone="subdued">
                          Page views
                        </Text>
                        <Text variant="headingLg" as="p" tone={pageViewsCount > 0 ? "critical" : "subdued"}>
                          {pageViewsCount}
                        </Text>
                      </BlockStack>
                    </Box>

                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "12px" }}>
                      <BlockStack gap="100">
                        <Text variant="bodySm" tone="subdued">
                          Product views
                        </Text>
                        <Text variant="headingLg" as="p" tone={productViewsCount > 0 ? "critical" : "subdued"}>
                          {productViewsCount}
                        </Text>
                      </BlockStack>
                    </Box>

                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "12px" }}>
                      <BlockStack gap="100">
                        <Text variant="bodySm" tone="subdued">
                          Add to cart
                        </Text>
                        <Text variant="headingLg" as="p" tone={addToCartCount > 0 ? "critical" : "subdued"}>
                          {addToCartCount}
                        </Text>
                      </BlockStack>
                    </Box>

                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "12px" }}>
                      <BlockStack gap="100">
                        <Text variant="bodySm" tone="subdued">
                          Checkout
                        </Text>
                        <Text variant="headingLg" as="p" tone={checkoutCount > 0 ? "critical" : "subdued"}>
                          {checkoutCount}
                        </Text>
                      </BlockStack>
                    </Box>

                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "12px" }}>
                      <BlockStack gap="100">
                        <Text variant="bodySm" tone="subdued">
                          All store orders
                        </Text>
                        <Text variant="headingLg" as="p" tone={orderCount > 0 ? "critical" : "subdued"}>
                          {orderCount}
                        </Text>
                      </BlockStack>
                    </Box>
                  </InlineGrid>
                </BlockStack>
              </Box>
            </Card>

            {/* Monthly Event Usage Progress Card */}
            <Card radius="300">
              <Box padding="500">
                <BlockStack gap="400">
                  <InlineStack align="space-between" blockAlign="center">
                    <InlineStack gap="200" blockAlign="center">
                      <Text variant="headingMd" as="h2">
                        Monthly Event Usage
                      </Text>
                      <Badge tone={planName === "Basic" ? "success" : "attention"}>
                        {planName} Plan
                      </Badge>
                    </InlineStack>

                    <Text variant="bodySm" tone="subdued">
                      {quotaLimit
                        ? `${monthlyCount.toLocaleString()} / ${quotaLimit.toLocaleString()} events used (${usagePercentage}%)`
                        : `${monthlyCount.toLocaleString()} events tracked (Unlimited)`}
                    </Text>
                  </InlineStack>

                  {quotaLimit ? (
                    <ProgressBar
                      progress={usagePercentage}
                      size="small"
                      tone={quotaExceeded ? "critical" : usagePercentage > 80 ? "attention" : "primary"}
                    />
                  ) : (
                    <ProgressBar progress={100} size="small" tone="success" />
                  )}

                  {quotaExceeded && (
                    <Banner title="50,000 Event Limit Reached" tone="critical">
                      <p>
                        You have reached your 50,000 monthly event quota on the Free plan. Event tracking is currently paused until your next monthly billing cycle or until you upgrade to the Basic plan.
                      </p>
                    </Banner>
                  )}
                </BlockStack>
              </Box>
            </Card>

            {/* Setup and Next Milestones Progress Card */}
            <Card radius="300">
              <Box padding="500">
                <BlockStack gap="300">
                  <InlineStack align="space-between" blockAlign="center">
                    <InlineStack gap="200" blockAlign="center">
                      <Text variant="headingMd" as="h2">
                        Setup and next milestones
                      </Text>
                    </InlineStack>
                  </InlineStack>

                  <Text variant="bodyMd" tone="subdued">
                    Your pixel is connected and capturing storefront events. Complete your Conversions API key in Settings to verify server-side deduplication.
                  </Text>
                </BlockStack>
              </Box>
            </Card>
          </BlockStack>
        </Page>
      </Box>

      {toastMessage && (
        <Toast
          content={toastMessage}
          error={toastError}
          onDismiss={() => setToastMessage("")}
        />
      )}
    </Frame>
  );
}
