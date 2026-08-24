import { useState, useEffect } from "react";
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
  Modal,
} from "@shopify/polaris";
import { ViewIcon, HideIcon } from "@shopify/polaris-icons";
import api from "../api";
import { Loader } from "../components/Loader";

export default function Settings() {
  const [loading, setLoading] = useState(true);
  const [pixelId, setPixelId] = useState("");
  const [capiKey, setCapiKey] = useState("");
  const [saving, setSaving] = useState(false);
  const [testingConnection, setTestingConnection] = useState(false);
  const [testResult, setTestResult] = useState(null);
  const [testModalOpen, setTestModalOpen] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastError, setToastError] = useState(false);
  const [showCapiKey, setShowCapiKey] = useState(false);
  const [showAdvertiserKey, setShowAdvertiserKey] = useState(false);

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

  const handleTestConnection = async () => {
    try {
      setTestingConnection(true);
      const res = await api.post("/pixel/test-connection", {
        pixel_id: pixelId,
        capi_key: capiKey,
      });

      setTestResult(res.data);
      setTestModalOpen(true);
    } catch (e) {
      setTestResult({
        success: false,
        message: "Failed to perform test connection. Please check your credentials.",
        details: { capi: false, pixel: Boolean(pixelId), credentials: false },
      });
      setTestModalOpen(true);
    } finally {
      setTestingConnection(false);
    }
  };

  if (loading) {
    return <Loader />;
  }

  const isConnected = Boolean(pixelId && capiKey);

  return (
    <Frame>
      <Box style={{ padding: "2rem 1.5rem", margin: "0 auto", maxWidth: "1200px" }}>
        <Page title="Settings">
          <BlockStack gap="500">
            {/* Warning Banner when keys missing */}
            {!isConnected && (
              <Banner title="OpenAI Credentials Required" status="warning">
                <p>Enter your OpenAI Pixel ID & Conversions API key below to activate conversion tracking.</p>
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
                        <Badge tone="attention">Action Required</Badge>
                      )}
                    </InlineStack>
                  </InlineStack>

                  <Text variant="bodyMd" tone="subdued">
                    Credentials required by OpenAI Ads Manager. We use these to forward storefront customer events to OpenAI CAPI. Encrypted at rest, deleted if you uninstall.
                  </Text>

                  <BlockStack gap="300">
                    <TextField
                      label="Pixel ID *"
                      value={pixelId}
                      onChange={setPixelId}
                      placeholder="e.g. gpt_987654321098"
                      autoComplete="off"
                      helpText="Create one in OpenAI Ads Manager under Conversions."
                    />

                    <TextField
                      label="Conversions API key (CAPI) *"
                      value={capiKey}
                      onChange={setCapiKey}
                      type="password"
                      placeholder="sk-..."
                      autoComplete="off"
                      helpText="Powers server-side CAPI event delivery. Create it under Ads Manager, Conversions, Manage conversion keys."
                    />
                  </BlockStack>

                  <InlineStack gap="300" align="end">
                    <Button
                      onClick={handleTestConnection}
                      loading={testingConnection}
                    >
                      Test Connection
                    </Button>
                    <Button variant="primary" onClick={handleSave} loading={saving}>
                      Save Settings
                    </Button>
                  </InlineStack>
                </BlockStack>
              </Box>
            </Card>
          </BlockStack>
        </Page>
      </Box>

      {/* Test Connection Result Modal */}
      {testResult && (
        <Modal
          open={testModalOpen}
          onClose={() => setTestModalOpen(false)}
          title="Test Connection Results"
          primaryAction={{
            content: "Close",
            onAction: () => setTestModalOpen(false),
          }}
        >
          <Modal.Section>
            <BlockStack gap="400">
              <Banner
                title={testResult.success ? "Connection Test Passed" : "Connection Test Failed"}
                status={testResult.success ? "success" : "critical"}
              >
                <p>{testResult.message}</p>
              </Banner>

              <BlockStack gap="200">
                <InlineStack align="space-between">
                  <Text variant="bodyMd">Conversions API Endpoint</Text>
                  {testResult.details?.capi ? <Badge tone="success">✓ Connected</Badge> : <Badge tone="critical">✕ Failed</Badge>}
                </InlineStack>
                <InlineStack align="space-between">
                  <Text variant="bodyMd">Pixel ID Registration</Text>
                  {testResult.details?.pixel ? <Badge tone="success">✓ Installed</Badge> : <Badge tone="critical">✕ Missing</Badge>}
                </InlineStack>
                <InlineStack align="space-between">
                  <Text variant="bodyMd">API Key Authorization</Text>
                  {testResult.details?.credentials ? <Badge tone="success">✓ Valid</Badge> : <Badge tone="critical">✕ Invalid</Badge>}
                </InlineStack>
              </BlockStack>
            </BlockStack>
          </Modal.Section>
        </Modal>
      )}

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
