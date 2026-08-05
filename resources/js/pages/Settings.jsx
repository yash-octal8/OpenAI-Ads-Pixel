import React, { useState, useEffect } from "react";
import {
  Page,
  Card,
  Box,
  Text,
  BlockStack,
  InlineStack,
  Button,
  TextField,
  Banner,
  Badge,
  Frame,
  Toast,
  InlineGrid,
  Divider,
} from "@shopify/polaris";
import api from "../api";
import { Loader } from "../components/Loader";

export default function Settings() {
  const [loading, setLoading] = useState(true);
  const [pixelId, setPixelId] = useState("");
  const [capiKey, setCapiKey] = useState("");
  const [advertiserKey, setAdvertiserKey] = useState("");
  const [saving, setSaving] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastError, setToastError] = useState(false);

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      setLoading(true);
      const res = await api.get("/pixel");
      if (res.data.success && res.data.settings) {
        setPixelId(res.data.settings.pixel_id || "");
        setCapiKey(res.data.settings.capi_key || "");
        setAdvertiserKey(res.data.settings.advertiser_key || "");
      }
    } catch (e) {
      console.error("Failed to load pixel settings", e);
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      const res = await api.post("/pixel/settings", {
        pixel_id: pixelId,
        capi_key: capiKey,
        advertiser_key: advertiserKey,
      });

      if (res.data.success) {
        setToastMessage("Settings saved successfully!");
        setToastError(false);
      } else {
        setToastMessage(res.data.message || "Failed to save settings");
        setToastError(true);
      }
    } catch (e) {
      setToastMessage("Failed to save settings");
      setToastError(true);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <Loader />;
  }

  const isConnected = Boolean(pixelId && capiKey);

  return (
    <Frame>
      <Box style={{ padding: "2rem 1rem", margin: "0 auto", maxWidth: "900px" }}>
        <Page title="Settings">
          <BlockStack gap="500">
            {/* Warning Banner when keys missing/rejected */}
            {!isConnected && (
              <Banner title="OpenAI is rejecting your events" status="critical">
                <p>Generate a new Conversions API key in Ads Manager and paste it below.</p>
              </Banner>
            )}

            {/* OpenAI Connection Card */}
            <Card radius="300">
              <Box padding="500">
                <BlockStack gap="400">
                  <InlineStack align="space-between" blockAlign="center">
                    <InlineStack gap="200" blockAlign="center">
                      <Text variant="headingMd" as="h2">
                        OpenAI connection
                      </Text>
                      {isConnected ? (
                        <Badge tone="success">Connected</Badge>
                      ) : (
                        <Badge tone="critical">Key rejected</Badge>
                      )}
                    </InlineStack>
                  </InlineStack>

                  <Text variant="bodyMd" tone="subdued">
                    Everything Reach needs from OpenAI Ads Manager, in one place. We use these only to forward your store events and read your ads data. Encrypted at rest, deleted if you uninstall.
                  </Text>

                  <BlockStack gap="300">
                    <TextField
                      label="Pixel ID *"
                      value={pixelId}
                      onChange={setPixelId}
                      placeholder="e.g. QgedQGwnYIJIVHMvHwSvh2"
                      autoComplete="off"
                      helpText="Create one in Ads Manager, Conversions, Create new pixel."
                    />

                    <TextField
                      label="Conversions API key *"
                      value={capiKey}
                      onChange={setCapiKey}
                      type="password"
                      placeholder="Paste your key..."
                      autoComplete="off"
                      helpText="Lets Reach send your store events to OpenAI. Create it under Ads Manager, Conversions, Manage conversion keys."
                    />

                    <TextField
                      label="Advertiser API key (optional)"
                      value={advertiserKey}
                      onChange={setAdvertiserKey}
                      type="password"
                      placeholder="Paste your key"
                      autoComplete="off"
                      helpText="Unlocks spend, ROAS, and CPA on your Performance page. A separate key from Ads Manager. Paste it whenever you're ready."
                    />
                  </BlockStack>

                  <InlineStack gap="200" align="space-between" blockAlign="center">
                    <InlineStack gap="200">
                      {pixelId ? (
                        <Badge tone="success">Pixel ID set</Badge>
                      ) : (
                        <Badge tone="attention">Pixel ID missing</Badge>
                      )}
                      {capiKey ? (
                        <Badge tone="success">Conversions key set</Badge>
                      ) : (
                        <Badge tone="attention">Conversions key missing</Badge>
                      )}
                      <Badge tone="subdued">Advertiser key optional</Badge>
                    </InlineStack>

                    <Button variant="primary" onClick={handleSave} loading={saving}>
                      Save Settings
                    </Button>
                  </InlineStack>
                </BlockStack>
              </Box>
            </Card>

            {/* Connection status Card */}
            <Card radius="300">
              <Box padding="500">
                <BlockStack gap="400">
                  <Text variant="headingMd" as="h2">
                    Connection status
                  </Text>

                  <BlockStack gap="300">
                    {/* Row 1: Conversions API */}
                    <InlineStack align="space-between" blockAlign="center">
                      <BlockStack gap="050">
                        <Text variant="bodyMd" fontWeight="semibold">
                          Conversions API
                        </Text>
                        <Text variant="bodySm" tone="subdued">
                          Forwarding events to OpenAI
                        </Text>
                      </BlockStack>
                      {capiKey ? (
                        <Badge tone="success">Connected</Badge>
                      ) : (
                        <Badge tone="critical">Disconnected</Badge>
                      )}
                    </InlineStack>

                    <Divider />

                    {/* Row 2: Advertiser API */}
                    <InlineStack align="space-between" blockAlign="center">
                      <BlockStack gap="050">
                        <Text variant="bodyMd" fontWeight="semibold">
                          Advertiser API
                        </Text>
                        <Text variant="bodySm" tone="subdued">
                          Unlocks spend, ROAS, and CPA
                        </Text>
                      </BlockStack>
                      {advertiserKey ? (
                        <Badge tone="success">Connected</Badge>
                      ) : (
                        <Badge tone="subdued">Not connected - Optional</Badge>
                      )}
                    </InlineStack>

                    <Divider />

                    {/* Row 3: Web pixel on storefront */}
                    <InlineStack align="space-between" blockAlign="center">
                      <BlockStack gap="050">
                        <Text variant="bodyMd" fontWeight="semibold">
                          Web pixel on storefront
                        </Text>
                        <Text variant="bodySm" tone="subdued">
                          Corrected snippet installed
                        </Text>
                      </BlockStack>
                      <Badge tone="success">Live</Badge>
                    </InlineStack>
                  </BlockStack>
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
