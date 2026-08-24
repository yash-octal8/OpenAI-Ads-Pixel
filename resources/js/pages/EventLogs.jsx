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
  Divider,
  InlineGrid,
} from "@shopify/polaris";
import api from "../api";
import { Loader } from "../components/Loader";

export default function EventLogs() {
  const [loading, setLoading] = useState(true);
  const [events, setEvents] = useState([]);
  const [search, setSearch] = useState("");
  const [eventTypeFilter, setEventTypeFilter] = useState("all");
  const [sourceFilter, setSourceFilter] = useState("all");
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [toastMessage, setToastMessage] = useState("");

  useEffect(() => {
    fetchEvents();
    const interval = setInterval(() => {
      fetchEventsSilently();
    }, 4000);
    return () => clearInterval(interval);
  }, [eventTypeFilter, sourceFilter]);

  const filteredEvents = events.filter((e) => {
    if (!search) return true;
    const query = search.toLowerCase();
    return (
      (e.event_id && e.event_id.toLowerCase().includes(query)) ||
      (e.event_name && e.event_name.toLowerCase().includes(query)) ||
      (e.pixel_id && e.pixel_id.toLowerCase().includes(query)) ||
      (e.oppref && e.oppref.toLowerCase().includes(query)) ||
      (e.order_id && e.order_id.toLowerCase().includes(query))
    );
  });

  const resourceName = { singular: "event log", plural: "event logs" };
  const { selectedResources, allResourcesSelected, handleSelectionChange } =
    useIndexResourceState(filteredEvents);

  const fetchEvents = async () => {
    try {
      setLoading(true);
      await fetchEventsSilently();
    } catch (e) {
      console.error("Failed to fetch event logs", e);
    } finally {
      setLoading(false);
    }
  };

  const fetchEventsSilently = async () => {
    try {
      const res = await api.get("/event-logs", {
        params: {
          search,
          event_type: eventTypeFilter,
          source: sourceFilter,
        },
      });
      if (res.data.success) {
        setEvents(res.data.events || []);
      }
    } catch (e) {
      // silent
    }
  };

  const handleClearLogs = async () => {
    try {
      await api.post("/pixel/clear");
      setEvents([]);
      setToastMessage("Event logs cleared successfully");
    } catch (e) {
      console.error(e);
    }
  };

  if (loading) {
    return <Loader />;
  }

  const rowMarkup = filteredEvents.map((event, index) => {
    const isSuccess = event.status === "Delivered" || event.status === "Loaded" || event.response_code === 200;

    return (
      <IndexTable.Row
        id={event.id ? event.id.toString() : index.toString()}
        key={event.id || index}
        selected={selectedResources.includes(event.id ? event.id.toString() : index.toString())}
        position={index}
      >
        <IndexTable.Cell>
          <Text variant="bodySm" fontWeight="bold">
            {event.event_id || `evt_${event.id}`}
          </Text>
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Text variant="bodyMd" fontWeight="semibold">
            {event.event_name}
          </Text>
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Badge tone={event.source === "Server" ? "attention" : "info"}>
            {event.source || "Browser"}
          </Badge>
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Text variant="bodySm" tone="subdued">
            {event.pixel_id || "N/A"}
          </Text>
        </IndexTable.Cell>
        <IndexTable.Cell>
          {isSuccess ? (
            <Badge tone="success">Delivered (200)</Badge>
          ) : (
            <Badge tone="critical">Failed ({event.response_code || 500})</Badge>
          )}
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Text variant="bodySm" tone="subdued">
            {event.event_time}
          </Text>
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Button size="slim" onClick={() => setSelectedEvent(event)}>
            View Details
          </Button>
        </IndexTable.Cell>
      </IndexTable.Row>
    );
  });

  return (
    <Frame>
      <Box style={{ padding: "2rem 1.5rem", margin: "0 auto", maxWidth: "1200px" }}>
        <Page fullWidth>
          <BlockStack gap="500">
            {/* Custom Header Bar */}
            <InlineStack align="space-between" blockAlign="center">
              <BlockStack gap="100">
                <Text variant="headingLg" as="h1">
                  Event Logs & Debugger
                </Text>
                <Text variant="bodyMd" tone="subdued">
                  Real-time log of every storefront browser & server CAPI event sent to OpenAI.
                </Text>
              </BlockStack>

              <Button onClick={handleClearLogs}>
                Clear Event Logs
              </Button>
            </InlineStack>
            {/* Filters Bar */}
            <Card radius="300">
              <Box padding="400">
                <InlineStack gap="400" align="space-between" blockAlign="center">
                  <InlineStack gap="300">
                    <Box style={{ width: "220px" }}>
                      <Select
                        label="Event Name"
                        options={[
                          { label: "All Events", value: "all" },
                          { label: "page_viewed", value: "page_viewed" },
                          { label: "product_viewed", value: "product_viewed" },
                          { label: "add_to_cart", value: "add_to_cart" },
                          { label: "checkout_started", value: "checkout_started" },
                          { label: "order_completed", value: "order_completed" },
                        ]}
                        value={eventTypeFilter}
                        onChange={setEventTypeFilter}
                      />
                    </Box>

                    <Box style={{ width: "200px" }}>
                      <Select
                        label="Source"
                        options={[
                          { label: "All Sources", value: "all" },
                          { label: "Browser Pixel", value: "Browser" },
                          { label: "Server CAPI", value: "Server" },
                        ]}
                        value={sourceFilter}
                        onChange={setSourceFilter}
                      />
                    </Box>
                  </InlineStack>

                  <Box style={{ width: "320px" }}>
                    <TextField
                      label="Search Logs"
                      placeholder="Search event ID, oppref, order ID..."
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

            {/* Event Table */}
            <Card radius="300">
              {filteredEvents.length > 0 ? (
                <IndexTable
                  resourceName={resourceName}
                  itemCount={filteredEvents.length}
                  selectedItemsCount={
                    allResourcesSelected ? "All" : selectedResources.length
                  }
                  onSelectionChange={handleSelectionChange}
                  headings={[
                    { title: "Event ID" },
                    { title: "Event Name" },
                    { title: "Source" },
                    { title: "Pixel ID" },
                    { title: "Status" },
                    { title: "Time" },
                    { title: "Details" },
                  ]}
                >
                  {rowMarkup}
                </IndexTable>
              ) : (
                <Box padding="600">
                  <BlockStack gap="200" inlineAlign="center">
                    <Text variant="headingMd" as="h3">No event logs recorded</Text>
                    <Text variant="bodyMd" tone="subdued">
                      Events fired on your storefront will stream live in this tab.
                    </Text>
                  </BlockStack>
                </Box>
              )}
            </Card>
          </BlockStack>
        </Page>
      </Box>

      {/* Event Details Drawer Modal */}
      {selectedEvent && (
        <Modal
          open={Boolean(selectedEvent)}
          onClose={() => setSelectedEvent(null)}
          title={`Event Details: ${selectedEvent.event_name}`}
          primaryAction={{
            content: "Close",
            onAction: () => setSelectedEvent(null),
          }}
        >
          <Modal.Section>
            <BlockStack gap="400">
              <InlineGrid columns={2} gap="300">
                <Box>
                  <Text variant="bodySm" tone="subdued">Event ID</Text>
                  <Text variant="bodyMd" fontWeight="bold">{selectedEvent.event_id || "N/A"}</Text>
                </Box>
                <Box>
                  <Text variant="bodySm" tone="subdued">Pixel ID</Text>
                  <Text variant="bodyMd" fontWeight="bold">{selectedEvent.pixel_id || "N/A"}</Text>
                </Box>
                <Box>
                  <Text variant="bodySm" tone="subdued">Source</Text>
                  <Text variant="bodyMd">{selectedEvent.source || "Browser"}</Text>
                </Box>
                <Box>
                  <Text variant="bodySm" tone="subdued">oppref (ChatGPT Click Ref)</Text>
                  <Text variant="bodyMd">{selectedEvent.oppref || "None"}</Text>
                </Box>
              </InlineGrid>

              <Divider />

              <Text variant="headingSm" as="h3">Event Payload JSON</Text>
              <Box
                style={{
                  backgroundColor: "#1e1e1e",
                  color: "#d4d4d4",
                  padding: "12px",
                  borderRadius: "6px",
                  fontFamily: "monospace",
                  fontSize: "12px",
                  overflowX: "auto",
                  maxHeight: "220px",
                }}
              >
                <pre>{JSON.stringify(selectedEvent.payload || {}, null, 2)}</pre>
              </Box>

              <Text variant="headingSm" as="h3">OpenAI API CAPI Response</Text>
              <Box
                style={{
                  backgroundColor: "#f4f6f8",
                  padding: "12px",
                  borderRadius: "6px",
                  fontFamily: "monospace",
                  fontSize: "12px",
                }}
              >
                <Text variant="bodySm">HTTP Status: {selectedEvent.response_code || 200}</Text>
                <pre>{selectedEvent.response_body || '{"status": "OK", "events_received": 1, "deduplicated": true}'}</pre>
              </Box>
            </BlockStack>
          </Modal.Section>
        </Modal>
      )}

      {toastMessage && (
        <Toast
          content={toastMessage}
          onDismiss={() => setToastMessage("")}
        />
      )}
    </Frame>
  );
}
