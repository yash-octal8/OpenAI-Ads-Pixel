import React, { useState, useEffect } from "react";
import {
  Page,
  Card,
  Box,
  Text,
  BlockStack,
  InlineStack,
  InlineGrid,
  ProgressBar,
  Frame,
} from "@shopify/polaris";
import api from "../api";
import { Loader } from "../components/Loader";

export default function Analytics() {
  const [loading, setLoading] = useState(true);
  const [metrics, setMetrics] = useState(null);

  useEffect(() => {
    fetchAnalytics();
  }, []);

  const fetchAnalytics = async () => {
    try {
      setLoading(true);
      const res = await api.get("/analytics");
      if (res.data.success) {
        setMetrics(res.data.metrics);
      }
    } catch (e) {
      console.error("Failed to load analytics", e);
    } finally {
      setLoading(false);
    }
  };

  if (loading || !metrics) {
    return <Loader />;
  }

  const {
    total_revenue,
    total_orders,
    average_order_value,
    conversion_rate,
    funnel,
    event_mix,
  } = metrics;

  return (
    <Frame>
      <Box style={{ padding: "2rem 1.5rem", margin: "0 auto", maxWidth: "1200px" }}>
        <Page
          title="Performance & Revenue Analytics"
          subtitle="Revenue, conversion funnel, and event mix attributed directly to OpenAI & ChatGPT Ads."
          fullWidth
        >
          <BlockStack gap="500">
            {/* 4 Core KPI Metric Cards */}
            <InlineGrid columns={{ xs: 2, sm: 4 }} gap="400">
              <Card radius="300">
                <Box padding="500">
                  <BlockStack gap="150">
                    <Text variant="bodySm" tone="subdued">OpenAI Ads Revenue</Text>
                    <Text variant="headingXl" as="p" tone="success">${total_revenue.toLocaleString()}</Text>
                  </BlockStack>
                </Box>
              </Card>

              <Card radius="300">
                <Box padding="500">
                  <BlockStack gap="150">
                    <Text variant="bodySm" tone="subdued">Attributed Orders</Text>
                    <Text variant="headingXl" as="p">{total_orders}</Text>
                  </BlockStack>
                </Box>
              </Card>

              <Card radius="300">
                <Box padding="500">
                  <BlockStack gap="150">
                    <Text variant="bodySm" tone="subdued">Conversion Rate</Text>
                    <Text variant="headingXl" as="p">{conversion_rate}%</Text>
                  </BlockStack>
                </Box>
              </Card>

              <Card radius="300">
                <Box padding="500">
                  <BlockStack gap="150">
                    <Text variant="bodySm" tone="subdued">Avg Order Value</Text>
                    <Text variant="headingXl" as="p">${average_order_value}</Text>
                  </BlockStack>
                </Box>
              </Card>
            </InlineGrid>

            {/* Conversion Funnel Section */}
            <Card radius="300">
              <Box padding="500">
                <BlockStack gap="400">
                  <Text variant="headingMd" as="h2">Conversion Funnel</Text>
                  <Text variant="bodySm" tone="subdued">
                    Step-by-step visitor progression from ChatGPT Ad clicks to completed purchases.
                  </Text>

                  <BlockStack gap="300">
                    <Box>
                      <InlineStack align="space-between">
                        <Text variant="bodyMd">1. Page Views (Store Visitors)</Text>
                        <Text variant="bodyMd" fontWeight="bold">{funnel.page_views.toLocaleString()}</Text>
                      </InlineStack>
                      <ProgressBar
                        progress={funnel.page_views > 0 ? 100 : 0}
                        size="small"
                        tone="primary"
                      />
                    </Box>

                    <Box>
                      <InlineStack align="space-between">
                        <Text variant="bodyMd">2. Product Views</Text>
                        <Text variant="bodyMd" fontWeight="bold">{funnel.product_views.toLocaleString()}</Text>
                      </InlineStack>
                      <ProgressBar
                        progress={funnel.page_views ? (funnel.product_views / funnel.page_views) * 100 : 0}
                        size="small"
                        tone="primary"
                      />
                    </Box>

                    <Box>
                      <InlineStack align="space-between">
                        <Text variant="bodyMd">3. Add to Cart</Text>
                        <Text variant="bodyMd" fontWeight="bold">{funnel.add_to_carts.toLocaleString()}</Text>
                      </InlineStack>
                      <ProgressBar
                        progress={funnel.product_views ? (funnel.add_to_carts / funnel.product_views) * 100 : 0}
                        size="small"
                        tone="primary"
                      />
                    </Box>

                    <Box>
                      <InlineStack align="space-between">
                        <Text variant="bodyMd">4. Checkout Started</Text>
                        <Text variant="bodyMd" fontWeight="bold">{funnel.checkouts.toLocaleString()}</Text>
                      </InlineStack>
                      <ProgressBar
                        progress={funnel.add_to_carts ? (funnel.checkouts / funnel.add_to_carts) * 100 : 0}
                        size="small"
                        tone="primary"
                      />
                    </Box>

                    <Box>
                      <InlineStack align="space-between">
                        <Text variant="bodyMd">5. Purchase Completed</Text>
                        <Text variant="bodyMd" fontWeight="bold" tone="success">{funnel.purchases.toLocaleString()}</Text>
                      </InlineStack>
                      <ProgressBar
                        progress={funnel.checkouts ? (funnel.purchases / funnel.checkouts) * 100 : 0}
                        size="small"
                        tone="success"
                      />
                    </Box>
                  </BlockStack>
                </BlockStack>
              </Box>
            </Card>

            {/* Event Mix Breakdown */}
            <Card radius="300">
              <Box padding="500">
                <BlockStack gap="400">
                  <Text variant="headingMd" as="h2">Event Mix</Text>
                  <Text variant="bodySm" tone="subdued">Percentage breakdown of storefront Customer Events</Text>

                  <InlineGrid columns={{ xs: 2, sm: 5 }} gap="300">
                    <Box style={{ backgroundColor: "#f1f2f4", padding: "16px", borderRadius: "8px" }}>
                      <Text variant="bodySm" tone="subdued">PageView</Text>
                      <Text variant="headingLg" as="p">{event_mix.page_view}%</Text>
                    </Box>
                    <Box style={{ backgroundColor: "#f1f2f4", padding: "16px", borderRadius: "8px" }}>
                      <Text variant="bodySm" tone="subdued">ProductView</Text>
                      <Text variant="headingLg" as="p">{event_mix.product_view}%</Text>
                    </Box>
                    <Box style={{ backgroundColor: "#f1f2f4", padding: "16px", borderRadius: "8px" }}>
                      <Text variant="bodySm" tone="subdued">AddToCart</Text>
                      <Text variant="headingLg" as="p">{event_mix.add_to_cart}%</Text>
                    </Box>
                    <Box style={{ backgroundColor: "#f1f2f4", padding: "16px", borderRadius: "8px" }}>
                      <Text variant="bodySm" tone="subdued">Checkout</Text>
                      <Text variant="headingLg" as="p">{event_mix.checkout}%</Text>
                    </Box>
                    <Box style={{ backgroundColor: "#eafaf1", padding: "16px", borderRadius: "8px" }}>
                      <Text variant="bodySm" tone="subdued">Purchase</Text>
                      <Text variant="headingLg" as="p" tone="success">{event_mix.purchase}%</Text>
                    </Box>
                  </InlineGrid>
                </BlockStack>
              </Box>
            </Card>
          </BlockStack>
        </Page>
      </Box>
    </Frame>
  );
}
