import React, { useState, useEffect } from "react";
import {
  Page,
  Card,
  Box,
  Text,
  BlockStack,
  InlineStack,
  Button,
  Badge,
  Frame,
  Toast,
  TextField,
  Select,
  Modal,
  IndexTable,
  useIndexResourceState,
} from "@shopify/polaris";
import api from "../api";
import { Loader } from "../components/Loader";

export default function Pixels() {
  const [loading, setLoading] = useState(true);
  const [pixels, setPixels] = useState([]);
  const [search, setSearch] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [editingPixel, setEditingPixel] = useState(null);

  // Form State
  const [name, setName] = useState("");
  const [pixelId, setPixelId] = useState("");
  const [capiKey, setCapiKey] = useState("");
  const [status, setStatus] = useState("active");
  const [testMode, setTestMode] = useState(false);
  const [coverageType, setCoverageType] = useState("entire_store");

  const [saving, setSaving] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastError, setToastError] = useState(false);

  useEffect(() => {
    fetchPixels();
  }, []);

  const filteredPixels = pixels.filter((p) =>
    p.name.toLowerCase().includes(search.toLowerCase()) ||
    p.pixel_id.toLowerCase().includes(search.toLowerCase())
  );

  const resourceName = { singular: "pixel", plural: "pixels" };
  const { selectedResources, allResourcesSelected, handleSelectionChange } =
    useIndexResourceState(filteredPixels);

  const fetchPixels = async () => {
    try {
      setLoading(true);
      const res = await api.get("/pixels");
      if (res.data.success) {
        setPixels(res.data.pixels || []);
      }
    } catch (e) {
      console.error("Failed to fetch pixels", e);
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (pixel = null) => {
    if (pixel) {
      setEditingPixel(pixel);
      setName(pixel.name || "");
      setPixelId(pixel.pixel_id || "");
      setCapiKey(pixel.capi_key || "");
      setStatus(pixel.status || "active");
      setTestMode(Boolean(pixel.test_mode));
      setCoverageType(pixel.coverage_type || "entire_store");
    } else {
      setEditingPixel(null);
      setName("GPT Pixel — Campaign");
      setPixelId("");
      setCapiKey("");
      setStatus("active");
      setTestMode(false);
      setCoverageType("entire_store");
    }
    setModalOpen(true);
  };

  const handleSavePixel = async () => {
    if (!pixelId || !name) {
      setToastMessage("Pixel Name and Pixel ID are required.");
      setToastError(true);
      return;
    }

    try {
      setSaving(true);
      const payload = {
        name,
        pixel_id: pixelId,
        capi_key: capiKey,
        status,
        test_mode: testMode,
        coverage_type: coverageType,
      };

      let res;
      if (editingPixel) {
        res = await api.put(`/pixels/${editingPixel.id}`, payload);
      } else {
        res = await api.post("/pixels", payload);
      }

      if (res.data.success) {
        setToastMessage(editingPixel ? "Pixel updated successfully" : "Pixel created successfully");
        setToastError(false);
        setModalOpen(false);
        fetchPixels();
      } else {
        setToastMessage(res.data.message || "Failed to save pixel");
        setToastError(true);
      }
    } catch (e) {
      setToastMessage("Error saving pixel");
      setToastError(true);
    } finally {
      setSaving(false);
    }
  };

  const handleDeletePixel = async (id) => {
    try {
      const res = await api.delete(`/pixels/${id}`);
      if (res.data.success) {
        setToastMessage("Pixel deleted successfully");
        setToastError(false);
        fetchPixels();
      }
    } catch (e) {
      setToastMessage("Failed to delete pixel");
      setToastError(true);
    }
  };

  if (loading) {
    return <Loader />;
  }

  const activeCount = pixels.filter((p) => p.status === "active").length;
  const pausedCount = pixels.filter((p) => p.status === "paused").length;
  const testingCount = pixels.filter((p) => p.status === "testing" || p.test_mode).length;
  const totalCount = pixels.length;

  const rowMarkup = filteredPixels.map((pixel, index) => (
    <IndexTable.Row
      id={pixel.id.toString()}
      key={pixel.id}
      selected={selectedResources.includes(pixel.id.toString())}
      position={index}
    >
      <IndexTable.Cell>
        <Text variant="bodyMd" fontWeight="bold">
          {pixel.name}
        </Text>
      </IndexTable.Cell>
      <IndexTable.Cell>
        <Text variant="bodySm" tone="subdued">
          {pixel.pixel_id}
        </Text>
      </IndexTable.Cell>
      <IndexTable.Cell>
        {pixel.status === "active" ? (
          <Badge tone="success">Active</Badge>
        ) : pixel.status === "testing" || pixel.test_mode ? (
          <Badge tone="attention">Testing</Badge>
        ) : (
          <Badge tone="subdued">Paused</Badge>
        )}
      </IndexTable.Cell>
      <IndexTable.Cell>
        <Text variant="bodySm">
          {pixel.coverage_type === "entire_store" ? "Entire store" : "Specific selection"}
        </Text>
      </IndexTable.Cell>
      <IndexTable.Cell>
        <InlineStack gap="200">
          <Button size="slim" onClick={() => handleOpenModal(pixel)}>
            Edit
          </Button>
          <Button size="slim" tone="critical" onClick={() => handleDeletePixel(pixel.id)}>
            Delete
          </Button>
        </InlineStack>
      </IndexTable.Cell>
    </IndexTable.Row>
  ));

  return (
    <Frame>
      <Box style={{ padding: "2rem 1.5rem", margin: "0 auto", maxWidth: "1200px" }}>
        <Page
          title="Pixels"
          subtitle="Create and manage multiple OpenAI tracking pixels for different campaigns and markets."
          primaryAction={{
            content: "Add new pixel",
            onAction: () => handleOpenModal(),
          }}
          fullWidth
        >
          <BlockStack gap="500">
            {/* Status Summary Banner */}
            <Card radius="300">
              <Box padding="500">
                <InlineStack align="space-between" blockAlign="center">
                  <InlineStack gap="500" blockAlign="center">
                    <Box>
                      <Text variant="bodySm" tone="subdued">All Pixels</Text>
                      <Text variant="headingXl" as="p">{totalCount}</Text>
                    </Box>
                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "24px" }}>
                      <Text variant="bodySm" tone="subdued">Active</Text>
                      <Text variant="headingXl" as="p" tone="success">{activeCount}</Text>
                    </Box>
                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "24px" }}>
                      <Text variant="bodySm" tone="subdued">Testing</Text>
                      <Text variant="headingXl" as="p" tone="attention">{testingCount}</Text>
                    </Box>
                    <Box style={{ borderLeft: "1px solid #e1e3e5", paddingLeft: "24px" }}>
                      <Text variant="bodySm" tone="subdued">Paused</Text>
                      <Text variant="headingXl" as="p" tone="subdued">{pausedCount}</Text>
                    </Box>
                  </InlineStack>

                  <Box style={{ width: "320px" }}>
                    <TextField
                      placeholder="Search by name or pixel ID..."
                      value={search}
                      onChange={setSearch}
                      clearButton
                      onClearButtonClick={() => setSearch("")}
                      autoComplete="off"
                    />
                  </Box>
                </InlineStack>
              </Box>
            </Card>

            {/* Pixels List Table */}
            <Card radius="300">
              {filteredPixels.length > 0 ? (
                <IndexTable
                  resourceName={resourceName}
                  itemCount={filteredPixels.length}
                  selectedItemsCount={
                    allResourcesSelected ? "All" : selectedResources.length
                  }
                  onSelectionChange={handleSelectionChange}
                  headings={[
                    { title: "Pixel Name" },
                    { title: "GPT Pixel ID" },
                    { title: "Status" },
                    { title: "Coverage" },
                    { title: "Actions" },
                  ]}
                >
                  {rowMarkup}
                </IndexTable>
              ) : (
                <Box padding="600">
                  <BlockStack gap="300" inlineAlign="center">
                    <Text variant="headingMd" as="h3">No pixels configured yet</Text>
                    <Text variant="bodyMd" tone="subdued">
                      Click "Add new pixel" above to configure your first OpenAI Ads Pixel.
                    </Text>
                    <Button variant="primary" onClick={() => handleOpenModal()}>
                      Add new pixel
                    </Button>
                  </BlockStack>
                </Box>
              )}
            </Card>
          </BlockStack>
        </Page>
      </Box>

      {/* Modal for Creating / Editing Pixels */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingPixel ? "Edit Pixel" : "Add new pixel"}
        primaryAction={{
          content: saving ? "Saving..." : "Save Pixel",
          onAction: handleSavePixel,
          loading: saving,
        }}
        secondaryActions={[
          {
            content: "Cancel",
            onAction: () => setModalOpen(false),
          },
        ]}
      >
        <Modal.Section>
          <BlockStack gap="400">
            <TextField
              label="Pixel Name *"
              value={name}
              onChange={setName}
              placeholder="e.g. GPT Pixel — Main Store"
              helpText="Internal name to identify this pixel configuration."
              autoComplete="off"
            />

            <TextField
              label="GPT Pixel ID *"
              value={pixelId}
              onChange={setPixelId}
              placeholder="e.g. gpt_987654321098"
              helpText="Generated in OpenAI Ads Manager under Conversions."
              autoComplete="off"
            />

            <TextField
              label="Conversions API Key (CAPI)"
              value={capiKey}
              onChange={setCapiKey}
              type="password"
              placeholder="sk-..."
              helpText="Used for server-side event delivery & deduplication."
              autoComplete="off"
            />

            <Select
              label="Status"
              options={[
                { label: "Active — start firing immediately", value: "active" },
                { label: "Testing — fire with test code only", value: "testing" },
                { label: "Paused — save configuration but do not fire", value: "paused" },
              ]}
              value={status}
              onChange={setStatus}
            />

            <Select
              label="Store Coverage"
              options={[
                { label: "Entire store (Every storefront page)", value: "entire_store" },
                { label: "Specific selection (Selected collections / products)", value: "specific" },
              ]}
              value={coverageType}
              onChange={setCoverageType}
            />
          </BlockStack>
        </Modal.Section>
      </Modal>

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
