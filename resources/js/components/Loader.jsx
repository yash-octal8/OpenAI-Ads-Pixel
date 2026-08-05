import { BlockStack, Box, Text } from "@shopify/polaris";

export function Loader({message}) {
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
          <img
            src="/Images/Octilo-logo.png"
            alt="Octilo"
            style={{
              width: "120px",
              height: "auto",
              margin: "0 auto",
              animation: "pulse-logo 1.5s infinite ease-in-out",
            }}
          />
          <BlockStack gap="100">
            <Text variant="headingMd" as="h2">
              Loading...
            </Text>

            <Text variant="bodySm" tone="subdued">
              {message ? message : "Please wait while we prepare your data."}
            </Text>
          </BlockStack>
        </BlockStack>
      </Box>
    </div>
  );
}