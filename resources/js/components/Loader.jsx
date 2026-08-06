import { BlockStack, Box, Spinner, Text } from "@shopify/polaris";

export function Loader({ message }) {
  return (
    <div
      style={{
        position: "fixed",
        inset: 0,
        background: "#ffffff",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        zIndex: 9999,
      }}
    >
      <style>{`
        @keyframes pulse-logo {
          0% { transform: scale(1); opacity: 0.8; }
          50% { transform: scale(1.08); opacity: 1; }
          100% { transform: scale(1); opacity: 0.8; }
        }
      `}</style>

      <Box style={{ textAlign: "center" }}>
        <BlockStack align="center" gap="400">
          <BlockStack gap="100">
            <Text variant="headingMd" as="h2">
              <Spinner></Spinner>
            </Text>

            <Text variant="bodySm" tone="subdued">
              Loading...
            </Text>
          </BlockStack>
        </BlockStack>
      </Box>
    </div>
  );
}
