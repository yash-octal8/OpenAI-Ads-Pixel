import { Box, Text, BlockStack } from "@shopify/polaris";
import { useI18n } from "../i18n";

export function Footer() {
  const { t } = useI18n();

  return (
    <Box paddingBlock="600" paddingInline="400">
      <BlockStack gap="100" inlineAlign="center">
        <Text variant="bodySm" tone="subdued" alignment="center">
          {t("footer.difficulties", "Having difficulties?")}
        </Text>
        <a
          href="mailto:octiloapps@gmail.com"
          target="_blank"
          style={{ fontSize: "13px", color: "#2c6ecb", textDecoration: "none" }}
        >
          <span style={{color: "#616161"}}>Contact us at </span>
          octiloapps@gmail.com
        </a>
      </BlockStack>
    </Box>
  );
}