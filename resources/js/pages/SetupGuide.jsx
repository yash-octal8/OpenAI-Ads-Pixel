import React from "react";
import {
  Card,
  Box,
  Text,
  BlockStack,
  Link as PolarisLink,
  Page,
  Frame,
} from "@shopify/polaris";
import { Link as RouterLink } from "react-router-dom";

export default function SetupGuide() {
  const steps = [
    {
      id: 1,
      title: "1. Get your Pixel ID and Conversions API key",
      content: (
        <span>
          Sign in at{" "}
          <PolarisLink url="https://ads.openai.com" external>
            ads.openai.com
          </PolarisLink>
          , open <strong>Conversions</strong>, and create a pixel if you don't
          have one. Copy the <strong>Pixel ID</strong>, then create a key under{" "}
          <strong>Manage conversion keys</strong> and copy it too.
        </span>
      ),
    },
    {
      id: 2,
      title: "2. Save them in Settings",
      content: (
        <span>
          Paste <strong>both</strong> into{" "}
          <RouterLink
            to="/settings"
            style={{
              color: "#303030",
              fontWeight: "bold",
              textDecoration: "underline",
            }}
          >
            Settings
          </RouterLink>{" "}
          and save. Reach installs the corrected tracking snippet on your
          storefront automatically, with no theme editing. Your keys are
          encrypted at rest and deleted if you uninstall.
        </span>
      ),
    },
    {
      id: 3,
      title: "3. Watch your first events arrive",
      content: (
        <span>
          Within a few minutes of traffic, the{" "}
          <RouterLink
            to="/"
            style={{
              color: "#303030",
              fontWeight: "bold",
              textDecoration: "underline",
            }}
          >
            Home
          </RouterLink>{" "}
          page shows events forwarding to OpenAI Ads: page views, product views,{" "}
          <strong>add to cart</strong>, checkout, and orders.{" "}
          <strong>That's your confirmation everything is live.</strong>
        </span>
      ),
    },
    {
      id: 4,
      title: "4. What Free includes, and what Basic adds",
      content: (
        <span>
          Free shows your pixel health and live ad engagement: visitors from
          your ChatGPT ads, what they view, and what they add to cart. Basic
          adds the deep layer: which products your ad shoppers love, where they
          drop off, and revenue attribution tied to real Shopify orders.
        </span>
      ),
    },
  ];

  return (
    <Frame>
      <Box style={{ padding: "2rem 1.5rem", margin: "0 auto", maxWidth: "1200px" }}>
        <Page title="Setup guide">
          <BlockStack gap="400">
            {steps.map((step) => (
              <Card key={step.id} radius="300">
                <Box padding="500">
                  <BlockStack gap="200">
                    <Text variant="headingMd" as="h2">
                      {step.title}
                    </Text>
                    <Text variant="bodyMd" tone="subdued">
                      {step.content}
                    </Text>
                  </BlockStack>
                </Box>
              </Card>
            ))}
          </BlockStack>
        </Page>
      </Box>
    </Frame>
  );
}
