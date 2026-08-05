import React, { useState } from "react";
import {
  Box,
  Text,
  Badge,
  Button,
  InlineStack,
  BlockStack,
  Icon,
  Tooltip,
} from "@shopify/polaris";

export default function PixelHelper({
  pixelId = "",
  appName = "Pixel for OpenAI Ads",
  events = [],
  onSimulateEvent = null,
  onClearEvents = null,
  isOpen = true,
  onClose = null,
}) {
  const [isMinimized, setIsMinimized] = useState(false);
  const [expandedEvents, setExpandedEvents] = useState({});
  const [showPayloadMap, setShowPayloadMap] = useState({});
  const [copiedId, setCopiedId] = useState(null);

  // Default events fallback if empty
  const displayEvents = events && events.length > 0 ? events : [
    {
      id: "evt_1",
      event_name: "checkout_started",
      event_type: "Standard",
      event_time: "12:35:17",
      payload: {
        checkout_id: "chk_902184",
        cart_token: "tok_392817",
        currency: "USD",
        value: 149.99,
        items_count: 2
      }
    },
    {
      id: "evt_2",
      event_name: "page_viewed",
      event_type: "Standard",
      event_time: "12:35:17",
      payload: {
        page_title: "Shopify Storefront",
        page_location: "https://openai-store.myshopify.com/",
        referrer: "https://ads.openai.com"
      }
    }
  ];

  const toggleEventExpand = (id) => {
    setExpandedEvents((prev) => ({
      ...prev,
      [id]: !prev[id],
    }));
  };

  const toggleShowPayload = (id, e) => {
    e.stopPropagation();
    setShowPayloadMap((prev) => ({
      ...prev,
      [id]: !prev[id],
    }));
  };

  const handleCopyPayload = (evt, e) => {
    e.stopPropagation();
    const jsonStr = JSON.stringify(evt.payload || {}, null, 2);
    navigator.clipboard.writeText(jsonStr);
    setCopiedId(evt.id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const collapseAll = () => {
    setExpandedEvents({});
    setShowPayloadMap({});
  };

  const expandAll = () => {
    const all = {};
    const payloadAll = {};
    displayEvents.forEach((evt) => {
      all[evt.id] = true;
      payloadAll[evt.id] = true;
    });
    setExpandedEvents(all);
    setShowPayloadMap(payloadAll);
  };

  const allExpanded =
    displayEvents.length > 0 &&
    displayEvents.every((evt) => expandedEvents[evt.id]);

  if (!isOpen) return null;

  return (
    <div
      style={{
        width: "360px",
        backgroundColor: "#ffffff",
        borderRadius: "14px",
        boxShadow: "0 12px 32px rgba(0, 0, 0, 0.18), 0 2px 6px rgba(0, 0, 0, 0.08)",
        border: "1px solid #e1e3e5",
        fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
        color: "#202223",
        zIndex: 9999,
        overflow: "hidden",
        transition: "all 0.25s ease",
      }}
    >
      {/* Header bar */}
      <div
        style={{
          padding: "10px 14px",
          borderBottom: isMinimized ? "none" : "1px solid #e1e3e5",
          backgroundColor: "#f6f6f7",
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          userSelect: "none",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
          {/* Drag Handle Icon */}
          <span style={{ color: "#8c9196", cursor: "grab", fontSize: "14px", fontWeight: "bold" }}>
            ⠿
          </span>
          <span style={{ fontWeight: 600, fontSize: "14px", color: "#202223" }}>
            Pixel helper
          </span>
          <Tooltip content="Live Facebook / OpenAI Ads Pixel Inspector">
            <span
              style={{
                cursor: "pointer",
                color: "#6d7175",
                fontSize: "13px",
                width: "16px",
                height: "16px",
                borderRadius: "50%",
                border: "1px solid #8c9196",
                display: "inline-flex",
                alignItems: "center",
                justifyContent: "center",
                lineHeight: 1,
              }}
            >
              i
            </span>
          </Tooltip>
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
          <button
            onClick={() => setIsMinimized(!isMinimized)}
            title={isMinimized ? "Expand Pixel Helper" : "Minimize Pixel Helper"}
            style={{
              background: "none",
              border: "none",
              cursor: "pointer",
              color: "#5c5f62",
              padding: "2px",
              display: "flex",
              alignItems: "center",
            }}
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              style={{
                transform: isMinimized ? "rotate(180deg)" : "rotate(0deg)",
                transition: "transform 0.2s ease",
              }}
            >
              <path d="M4 13L10 7L16 13" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </button>
          {onClose && (
            <button
              onClick={onClose}
              title="Close Pixel Helper"
              style={{
                background: "none",
                border: "none",
                cursor: "pointer",
                color: "#8c9196",
                padding: "2px",
              }}
            >
              ✕
            </button>
          )}
        </div>
      </div>

      {/* Main Body */}
      {!isMinimized && (
        <div style={{ padding: "14px", maxHeight: "520px", overflowY: "auto" }}>
          {/* Pixel Loaded Card */}
          <div
            style={{
              marginBottom: "12px",
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
            }}
          >
            <span style={{ fontWeight: 600, fontSize: "14px", color: "#1a1a1a" }}>
              {appName}
            </span>
            <span
              style={{
                backgroundColor: "#e3f8e0",
                color: "#008060",
                fontSize: "12px",
                fontWeight: 600,
                padding: "2px 8px",
                borderRadius: "12px",
                display: "inline-flex",
                alignItems: "center",
                gap: "5px",
              }}
            >
              <span
                style={{
                  width: "7px",
                  height: "7px",
                  borderRadius: "50%",
                  backgroundColor: "#008060",
                  display: "inline-block",
                }}
              />
              Loaded
            </span>
          </div>

          <div style={{ fontSize: "13px", color: "#6d7175", marginBottom: "14px" }}>
            ID: <span style={{ fontFamily: "monospace", color: "#202223" }}>{pixelId}</span>
          </div>

          {/* Events Received Bar */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              marginBottom: "12px",
              paddingBottom: "8px",
              borderBottom: "1px solid #f1f2f3",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
              <span style={{ fontWeight: 600, fontSize: "13px", color: "#202223" }}>
                Events received
              </span>
              <span
                style={{
                  backgroundColor: "#e4e5e7",
                  color: "#202223",
                  fontSize: "12px",
                  fontWeight: 600,
                  borderRadius: "10px",
                  padding: "1px 7px",
                }}
              >
                {displayEvents.length}
              </span>
            </div>

            <button
              onClick={allExpanded ? collapseAll : expandAll}
              style={{
                background: "none",
                border: "none",
                color: "#005bd3",
                fontSize: "12px",
                fontWeight: 500,
                cursor: "pointer",
                padding: 0,
              }}
            >
              {allExpanded ? "Collapse all" : "Collapse all"}
            </button>
          </div>

          {/* Event Items List */}
          <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
            {displayEvents.map((evt) => {
              const isExpanded = Boolean(expandedEvents[evt.id]);
              const showPayload = Boolean(showPayloadMap[evt.id]);

              return (
                <div
                  key={evt.id}
                  style={{
                    borderRadius: "8px",
                    border: "1px solid #e1e3e5",
                    backgroundColor: "#ffffff",
                    overflow: "hidden",
                    transition: "border-color 0.15s ease",
                  }}
                >
                  {/* Event Title Row */}
                  <div
                    onClick={() => toggleEventExpand(evt.id)}
                    style={{
                      padding: "8px 12px",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      cursor: "pointer",
                      backgroundColor: isExpanded ? "#fafafa" : "#ffffff",
                    }}
                  >
                    <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                      <span
                        style={{
                          transform: isExpanded ? "rotate(180deg)" : "rotate(90deg)",
                          transition: "transform 0.15s ease",
                          fontSize: "10px",
                          color: "#5c5f62",
                        }}
                      >
                        ▲
                      </span>
                      <span
                        style={{
                          width: "7px",
                          height: "7px",
                          borderRadius: "50%",
                          backgroundColor: "#008060",
                          display: "inline-block",
                        }}
                      />
                      <span
                        style={{
                          fontWeight: 500,
                          fontSize: "13px",
                          color: "#202223",
                          fontFamily: "monospace",
                        }}
                      >
                        {evt.event_name}
                      </span>
                    </div>

                    <span style={{ fontSize: "12px", color: "#6d7175" }}>
                      {evt.event_time}
                    </span>
                  </div>

                  {/* Expanded Content Details */}
                  {isExpanded && (
                    <div
                      style={{
                        padding: "10px 12px 10px 32px",
                        borderTop: "1px solid #f1f2f3",
                        backgroundColor: "#ffffff",
                        fontSize: "12px",
                      }}
                    >
                      <div
                        style={{
                          display: "flex",
                          justifyContent: "space-between",
                          alignItems: "center",
                          marginBottom: "6px",
                        }}
                      >
                        <div>
                          <span style={{ color: "#6d7175" }}>Event type: </span>
                          <span style={{ fontWeight: 600, color: "#202223" }}>
                            {evt.event_type || "Standard"}
                          </span>
                        </div>
                      </div>

                      <div
                        style={{
                          display: "flex",
                          alignItems: "center",
                          justifyContent: "space-between",
                        }}
                      >
                        <div>
                          <span style={{ color: "#6d7175" }}>Event data received: </span>
                          <button
                            onClick={(e) => toggleShowPayload(evt.id, e)}
                            style={{
                              background: "none",
                              border: "none",
                              color: "#005bd3",
                              cursor: "pointer",
                              textDecoration: "underline",
                              fontWeight: 500,
                              padding: 0,
                            }}
                          >
                            {showPayload ? "Hide" : "Show"}
                          </button>
                        </div>

                        {/* Copy Payload Button */}
                        <button
                          onClick={(e) => handleCopyPayload(evt, e)}
                          title="Copy payload JSON"
                          style={{
                            background: "none",
                            border: "none",
                            cursor: "pointer",
                            color: copiedId === evt.id ? "#008060" : "#6d7175",
                            padding: "2px 4px",
                            borderRadius: "4px",
                            display: "inline-flex",
                            alignItems: "center",
                            gap: "4px",
                            fontSize: "11px",
                          }}
                        >
                          <svg
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                          >
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                          </svg>
                          {copiedId === evt.id && "Copied!"}
                        </button>
                      </div>

                      {/* Payload JSON Inspector Box */}
                      {showPayload && (
                        <pre
                          style={{
                            marginTop: "8px",
                            padding: "8px",
                            backgroundColor: "#202223",
                            color: "#e1e3e5",
                            borderRadius: "6px",
                            fontSize: "11px",
                            lineHeight: "1.4",
                            overflowX: "auto",
                            fontFamily: "Menlo, Monaco, Consolas, 'Courier New', monospace",
                          }}
                        >
                          {JSON.stringify(evt.payload || {}, null, 2)}
                        </pre>
                      )}
                    </div>
                  )}
                </div>
              );
            })}
          </div>

          {/* Quick Trigger Simulation Actions Footer inside Helper */}
          {onSimulateEvent && (
            <div
              style={{
                marginTop: "14px",
                paddingTop: "10px",
                borderTop: "1px dashed #e1e3e5",
              }}
            >
              <div
                style={{
                  fontSize: "11px",
                  fontWeight: 600,
                  color: "#6d7175",
                  marginBottom: "6px",
                  textTransform: "uppercase",
                  letterSpacing: "0.5px",
                }}
              >
                Test Fire Events
              </div>
              <div style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}>
                {[
                  { name: "page_viewed", label: "Page View" },
                  { name: "product_viewed", label: "Product View" },
                  { name: "add_to_cart", label: "Add Cart" },
                  { name: "checkout_started", label: "Checkout" },
                  { name: "order_completed", label: "Order" },
                ].map((item) => (
                  <button
                    key={item.name}
                    onClick={() => onSimulateEvent(item.name)}
                    style={{
                      padding: "4px 8px",
                      fontSize: "11px",
                      borderRadius: "6px",
                      border: "1px solid #c9cccf",
                      backgroundColor: "#f6f6f7",
                      color: "#202223",
                      cursor: "pointer",
                      fontWeight: 500,
                      transition: "all 0.15s ease",
                    }}
                    onMouseOver={(e) => {
                      e.target.style.backgroundColor = "#e4e5e7";
                    }}
                    onMouseOut={(e) => {
                      e.target.style.backgroundColor = "#f6f6f7";
                    }}
                  >
                    + {item.label}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
