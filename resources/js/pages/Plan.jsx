import { Badge, BlockStack, Button, Card, Icon, InlineStack, Page, Spinner, Text, Box, Collapsible, Divider } from "@shopify/polaris";
import { useEffect, useState } from "react";
import { ChevronDownIcon } from "@shopify/polaris-icons";
import "../../css/PlanPricing.css";
import { useI18n } from "../i18n";
import { useApiFetcher } from "../hooks/useApiFetcher";
import { useAppBridge } from "@shopify/app-bridge-react";
import api from "../api";
import { Loader } from "../components/Loader";

export default function PlanPage() {
  const [data, setData] = useState(null);
  
  const fetchPlans = () => {
    api.get('/plan')
      .then(res => setData(res.data))
      .catch(e => console.error(e));
  };

  useEffect(() => {
    fetchPlans();
  }, []);

  if (!data) return <Loader />;

  return <PlanPageInner data={data} onRefresh={fetchPlans} />;
}

function PlanPageInner({ data, onRefresh }) {
  const { currentPlan, plans: dbPlans = [], shop: apiShop } = data || {};
  const fetcher = useApiFetcher();
  const shopify = useAppBridge();
  const [billingInterval, setBillingInterval] = useState("monthly");
  const { t } = useI18n();
  const [openIndex, setOpenIndex] = useState(null);
  const [loading, setLoading] = useState(false);
  const [currentIndex, setCurrentIndex] = useState(null);

  const toggleOpen = (index) => {
      setOpenIndex(openIndex === index ? null : index);
  };

  useEffect(() => {
    if (fetcher.data?.redirectUrl) {
      shopify.toast.show("Redirecting to Shopify for approval...");
      window.open(fetcher.data.redirectUrl, "_top");
    }
  }, [fetcher.data, shopify]);

  const handleSubscribePlan = async (plan, index) => {
    setLoading(true);
    setCurrentIndex(index);

    const urlParams = new URLSearchParams(window.location.search);
    let host = urlParams.get('host') || shopify?.config?.host;
    if (!host) {
        const appBridgeMeta = document.querySelector('meta[name="shopify-setup"]');
        if (appBridgeMeta) {
            try {
                const data = JSON.parse(appBridgeMeta.getAttribute('content'));
                host = data.host;
            } catch(e) {}
        }
    }
    
    // apiShop might be a string (shop domain) or an object
    const shopName = urlParams.get('shop') || shopify?.config?.shop || (typeof apiShop === 'string' ? apiShop : apiShop?.name) || '';
    const shopId = (typeof apiShop === 'object' ? apiShop?.id : 'shop') || 'shop';

    if (plan.name === 'Free') {
        shopify.toast.show(t("plans.downgrading", "Downgrading to Free plan..."));
        try {
            const result = await api.post('/plans/choose-plan/free');
            if (result.data?.success) {
                shopify.toast.show(result.data.message || "Successfully downgraded to Free plan");
                onRefresh();
                setLoading(false);
            } else {
                shopify.toast.show(result.data?.message || "Failed to downgrade", { isError: true });
                setLoading(false);
            }
        } catch (error) {
            shopify.toast.show("Something went wrong", { isError: true });
            setLoading(false);
        }
        return;
    }

    try {
        const response = await api.get(`/billing/${plan.id}?shop=${shopName}&host=${host}`);
        if (response.data && response.data.url) {
            window.open(response.data.url, "_top");
        } else {
            if (response.data.errors && response.data.errors.length > 0) {
                alert('Shopify Error: ' + response.data.errors.map(e => e.message).join(', '));
            }
            setLoading(false);
        }
    } catch (e) {
        setLoading(false);
    }
  };

  const defaultPlans = [
    {
      id: 0,
      name: "Free",
      price: "0.00",
      interval: "monthly",
      features: [
        "Smart Upload: Up to 25 images/mo",
        "Bulk Delete: Up to 100 items/mo",
        "Bulk Export: Up to 100 images/mo",
      ]
    },
    {
      id: 1,
      name: "Premium",
      price: "49.00",
      interval: "monthly",
      features: [
        "Smart Upload: Unlimited uploads",
        "Bulk Delete: Unlimited deletions",
        "Bulk Export: Unlimited exports",
      ]
    },
  ];

  const plansToUse = dbPlans.length > 0 ? dbPlans : defaultPlans;

  // Only Free and Premium plans — no interval filtering needed
  const filteredPlans = plansToUse.filter((plan) =>
    plan.name === "Free" || plan.name === "Premium"
  );

  const plansOrder = ["Free", "Premium"];
  const sortedPlans = [...filteredPlans].sort((a, b) => plansOrder.indexOf(a.name) - plansOrder.indexOf(b.name));

  const plans = sortedPlans.map((plan) => {
    const isCurrent = currentPlan === plan.name || (currentPlan === "Premium" && plan.name === "Annual Subscription");
    const isFree = plan.name === "Free";

    let buttonText = t("plans.subscribeNow", "Subscribe Now");
    let buttonDisabled = false;
    let badge = "";

    if (isCurrent) {
      buttonText = t("plans.currentPlan", "Current Plan");
      buttonDisabled = true;
      badge = t("plans.badgeCurrent", "CURRENT PLAN");
    } else if (isFree) {
      buttonText = t("plans.downgradeFree", "Downgrade to Free");
      buttonDisabled = false;
    }

    if (plan.name === "Premium" || plan.name === "Annual Subscription") {
      badge = isCurrent ? t("plans.badgeCurrent", "CURRENT PLAN") : t("plans.badgeBestValue", "BEST VALUE");
    }

    return {
      id: plan.id,
      name: plan.name,
      price: plan.price,
      interval: plan.interval === "annual" ? t("plans.perYear", "/ year") : t("plans.perMonth", "/ month"),
      description: plan.name === "Free" ? t("plans.freeDescription", "Perfect for getting started") : t("plans.premiumDescription", "Everything you need to scale"),
      badge,
      primary: plan.name !== "Free",
      features: plan.features,
      buttonText,
      buttonDisabled,
      isFree
    };
  });
  

  return (
    <>
    {loading && <Loader message="Please wait while we prepare your plan." />}
    <Box paddingBlock="800" paddingInline="400" style={{ margin: "0 auto", maxWidth: "1000px" }}>
        <Page fullWidth>
            <div className="plan-page-wrapper">
                <div className="plan-title-container">
                    <h2 className="plan-title">{t("plans.pageTitle", "Unlock your catalog's potential")}</h2>
                    <p className="plan-subheading">
                        {t("plans.pageSubtitle", "Choose the plan that's right for your store's growth.")}
                    </p>
                </div>

                <div className="plan-cards-container">
                    {plans.map((plan, index) => {
                        const displayPrice = `$${plan.price}`;
                        const originalPrice = !plan.isFree ? `$${(parseFloat(plan.price) * 1.1).toFixed(1)}` : null;

                        return (
                            <div className="plan-card " key={index} style={plan.primary ? { border: '2px solid var(--p-color-bg-fill-success-strong)' } : {}}>
                
                             {plan.name === "Premium" && (
                                <div className="recommended-badge">
                                <Badge tone="success">{t("plans.recommended", "Recommended")}</Badge>
                                </div>
                            )}

                                <div className="plan-card-header">
                                    <div className="plan-card-title">
                                        {plan.name}
                                    </div>
                                    <div className="plan-card-description">
                                        {plan.description}
                                    </div>
                                </div>

                                <div className="plan-card-price-container">
                                    {originalPrice && (
                                        <span className="plan-card-price-original">
                                            {originalPrice}
                                        </span>
                                    )}
                                    <span className="plan-card-price">
                                        {displayPrice}
                                    </span>
                                    <span className="plan-card-period">
                                        {plan.interval}
                                    </span>
                                </div>

                                <div className="plan-card-divider"></div>

                                <div className="plan-features-list">
                                    {plan.features.map((feature, fIndex) => (
                                        <div className="plan-feature-item" key={fIndex}>
                                            <svg
                                                width="20"
                                                height="20"
                                                viewBox="0 0 20 20"
                                                fill="none"
                                                xmlns="http://www.w3.org/2000/svg"
                                                style={{ marginRight: "8px", flexShrink: 0, border: "1px solid #aee9b8", borderRadius: "100%" }}
                                            >
                                                <circle cx="10" cy="10" r="10" fill="#d1f7d6" />
                                                <path
                                                    d="M14.6666 6.66669L8.24992 13.0834L5.33325 10.1667"
                                                    stroke="#0c5132"
                                                    strokeWidth="1.5"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                />
                                            </svg>
                                            {feature}
                                        </div>
                                    ))}
                                </div>

                                <button
                                    className={`plan-button ${plan.buttonDisabled ? "plan-button-current" : "plan-button-change"}`}
                                    disabled={plan.buttonDisabled || loading}
                                    onClick={() => handleSubscribePlan(plan, index)}
                                >
                                    {loading && currentIndex === index ? "Loading..." : plan.buttonText}
                                </button>
                            </div>
                        );
                    })}
                </div>

                <div style={{ width: '100%', maxWidth: '900px', marginTop: '32px' }}>
                    <Card padding="0">
                        <Box padding="600">
                            <Text variant="headingLg" as="h2">{t("planFaq.title", "Frequently Asked Questions")}</Text>

                            <Box paddingBlockStart="600">
                                {[
                                  {
                                    category: 'general',
                                    title: "General",
                                    questions: [
                                      { q: "Can I cancel my subscription anytime?", a: "Yes, you can easily downgrade to the free plan or uninstall at any time without penalty." },
                                      { q: "What happens if I exceed my quota?", a: "You will be prompted to upgrade to a higher tier plan before you can run more sync jobs." }
                                    ]
                                  },
                                  {
                                    category: 'upgrading',
                                    title: "Upgrading",
                                    questions: [
                                      { q: "When will I be billed?", a: "You will be billed immediately upon upgrading, prorated for the remaining time in your billing cycle." },
                                      { q: "Is there a free trial?", a: "Yes, most of our premium plans come with a 7-day free trial so you can test all features risk-free." }
                                    ]
                                  },
                                  {
                                    category: 'aboutApp',
                                    title: "About App",
                                    questions: [
                                      { q: "How does the matching work?", a: "Our algorithm automatically matches your images based on SKU, barcode, or title based on your configuration." }
                                    ]
                                  }
                                ].map((cat, sIdx) => (
                                    <Box key={sIdx} paddingBlockEnd={sIdx < 2 ? "600" : "0"}>
                                        <Box paddingBlockEnd="300">
                                            <Text variant="headingSm" as="h3">{t(`planFaq.${cat.category}.title`, cat.title)}</Text>
                                        </Box>
                                        <Card padding="0">
                                            {cat.questions.map((item, iIdx) => {
                                                const globalIndex = `${sIdx}-${iIdx}`;
                                                const isOpen = openIndex === globalIndex;
                                                return (
                                                    <Box key={iIdx} background={isOpen ? 'bg-surface-secondary' : 'bg-surface'}>
                                                        <button
                                                            onClick={() => toggleOpen(globalIndex)}
                                                            style={{
                                                                width: '100%',
                                                                padding: '16px 20px',
                                                                display: 'flex',
                                                                justifyContent: 'space-between',
                                                                alignItems: 'center',
                                                                background: 'transparent',
                                                                border: 'none',
                                                                cursor: 'pointer',
                                                                textAlign: 'left',
                                                            }}
                                                        >
                                                            <Text variant="bodyMd" tone="subdued">
                                                                {t(`planFaq.${cat.category}.q${iIdx + 1}`, item.q)}
                                                            </Text>
                                                            <div style={{
                                                                transform: isOpen ? 'rotate(180deg)' : 'rotate(0deg)',
                                                                transition: 'transform 0.2s ease',
                                                                flexShrink: 0
                                                            }}>
                                                                <Icon source={ChevronDownIcon} tone="subdued" />
                                                            </div>
                                                        </button>
                                                        <Collapsible open={isOpen} id={`plan-faq-${globalIndex}`}>
                                                            <Box paddingInlineStart="500" paddingInlineEnd="500" paddingBlockEnd="400">
                                                                <Text variant="bodyMd" as="p" tone="subdued">
                                                                    {t(`planFaq.${cat.category}.a${iIdx + 1}`, item.a)}
                                                                </Text>
                                                            </Box>
                                                        </Collapsible>
                                                        {(iIdx < cat.questions.length - 1) && <Divider />}
                                                    </Box>
                                                );
                                            })}
                                        </Card>
                                    </Box>
                                ))}
                            </Box>
                        </Box>
                    </Card>
                </div>
            </div>
        </Page>
    </Box>
    </>
  );
}
