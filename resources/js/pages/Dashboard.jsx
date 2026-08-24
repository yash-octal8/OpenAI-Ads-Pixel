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
  Button,
} from "@shopify/polaris";
import { useNavigate } from "react-router-dom";
import api from "../api";
import { Loader } from "../components/Loader";

export default function Dashboard() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [pixelId, setPixelId] = useState("");
  const [capiKey, setCapiKey] = useState("");
  const [pixels, setPixels] = useState([]);
  const [events, setEvents] = useState([]);
  const [planName, setPlanName] = useState("Free");
  const [monthlyCount, setMonthlyCount] = useState(0);
  const [quotaLimit, setQuotaLimit] = useState(50000);
  const [usagePercentage, setUsagePercentage] = useState(0);
  const [quotaExceeded, setQuotaExceeded] = useState(false);
  const [metrics, setMetrics] = useState(null);
  const [toastMessage, setToastMessage] = useState("");
  const [toastError, setToastError] = useState(false);

  useEffect(() => {
    fetchPixelData();
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
        if (res.data.pixels) setPixels(res.data.pixels);
        if (res.data.events) setEvents(res.data.events);
        if (res.data.plan_name) setPlanName(res.data.plan_name);
        if (res.data.monthly_event_count !== undefined) setMonthlyCount(res.data.monthly_event_count);
        setQuotaLimit(res.data.quota_limit);
        setUsagePercentage(res.data.usage_percentage || 0);
        setQuotaExceeded(Boolean(res.data.quota_exceeded));
        if (res.data.metrics) setMetrics(res.data.metrics);
      }
    } catch (e) {
      console.log(e);
    }
  };


  if (loading) {
    return <Loader />;
  }

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
  const activePixelsCount = pixels.filter((p) => p.status === "active").length || (isConnected ? 1 : 0);

  return (
    <Frame>
      <Box style={{ padding: "2rem 1.5rem", margin: "0 auto", maxWidth: "1200px" }}>
        <Page
          title="Overview & Performance"
          subtitle="Real-time OpenAI Ads & Web Pixel tracking metrics"
          fullWidth
          // secondaryActions={[
          //   {
          //     content: "Clear Event Logs",
          //     onAction: handleClearEvents,
          //   },
          // ]}
        >
          <BlockStack gap="500">
            {/* 4 Summary KPI Cards */}
            <InlineGrid columns={{ xs: 2, sm: 4 }} gap="400">
              <Card radius="300">
                <Box padding="400">
                  <BlockStack gap="100">
                    <Text variant="bodySm" tone="subdued">Active Pixels</Text>
                    <Text variant="headingXl" as="p">{activePixelsCount}</Text>
                  </BlockStack>
                </Box>
              </Card>

              <Card radius="300">
                <Box padding="400">
                  <BlockStack gap="100">
                    <Text variant="bodySm" tone="subdued">Events Today</Text>
                    <Text variant="headingXl" as="p">{events.length}</Text>
                  </BlockStack>
                </Box>
              </Card>

              <Card radius="300">
                <Box padding="400">
                  <BlockStack gap="100">
                    <Text variant="bodySm" tone="subdued">ChatGPT Purchases</Text>
                    <Text variant="headingXl" as="p" tone="success">{orderCount}</Text>
                  </BlockStack>
                </Box>
              </Card>

              <Card radius="300">
                <Box padding="400">
                  <BlockStack gap="100">
                    <Text variant="bodySm" tone="subdued">Revenue</Text>
                    <Text variant="headingXl" as="p" tone="success">${(metrics?.total_revenue || 0).toLocaleString()}</Text>
                  </BlockStack>
                </Box>
              </Card>
            </InlineGrid>

            {/* Tracking Status Card */}
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
                        <Badge tone="attention">Setup Pending</Badge>
                      )}
                    </InlineStack>

                    <Text variant="bodySm" tone="subdued">
                      {events.length} events logged
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
                        <Text variant="headingLg" as="p" tone={pageViewsCount > 0 ? "primary" : "subdued"}>
                          {pageViewsCount}
                        </Text>
                      </BlockStack>
                    </Box>

                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "12px" }}>
                      <BlockStack gap="100">
                        <Text variant="bodySm" tone="subdued">
                          Product views
                        </Text>
                        <Text variant="headingLg" as="p" tone={productViewsCount > 0 ? "primary" : "subdued"}>
                          {productViewsCount}
                        </Text>
                      </BlockStack>
                    </Box>

                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "12px" }}>
                      <BlockStack gap="100">
                        <Text variant="bodySm" tone="subdued">
                          Add to cart
                        </Text>
                        <Text variant="headingLg" as="p" tone={addToCartCount > 0 ? "primary" : "subdued"}>
                          {addToCartCount}
                        </Text>
                      </BlockStack>
                    </Box>

                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "12px" }}>
                      <BlockStack gap="100">
                        <Text variant="bodySm" tone="subdued">
                          Checkout
                        </Text>
                        <Text variant="headingLg" as="p" tone={checkoutCount > 0 ? "primary" : "subdued"}>
                          {checkoutCount}
                        </Text>
                      </BlockStack>
                    </Box>

                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "12px" }}>
                      <BlockStack gap="100">
                        <Text variant="bodySm" tone="subdued">
                          Purchases
                        </Text>
                        <Text variant="headingLg" as="p" tone={orderCount > 0 ? "success" : "subdued"}>
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
                        You have reached your 50,000 monthly event quota on the Free plan. Upgrade to the Basic plan ($29/mo) for unlimited event tracking.
                      </p>
                    </Banner>
                  )}
                </BlockStack>
              </Box>
            </Card>

            {/* Navigation Quick Actions */}
            <Card radius="300">
              <Box padding="500">
                <BlockStack gap="300">
                  <Text variant="headingMd" as="h2">
                    Quick Actions & Setup Milestones
                  </Text>
                  <Text variant="bodyMd" tone="subdued">
                    Manage pixels, inspect live event logs, or view full revenue attribution analytics.
                  </Text>

                  <InlineStack gap="300">
                    <Button onClick={() => navigate("/pixels")}>Manage Pixels</Button>
                    <Button onClick={() => navigate("/analytics")}>View Performance</Button>
                    <Button onClick={() => navigate("/event-logs")}>Event Logs Debugger</Button>
                    <Button onClick={() => navigate("/setup-guide")}>Setup Guide</Button>
                  </InlineStack>
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
