import { Page, Box } from "@shopify/polaris";
import { useEffect, useState } from "react";
import "../../css/PlanPricing.css";
import { useI18n } from "../i18n";
import { useApiFetcher } from "../hooks/useApiFetcher";
import { useAppBridge } from "@shopify/app-bridge-react";
import api from "../api";
import { Loader } from "../components/Loader";

export default function PlanPage() {
  const [data, setData] = useState(null);

  const fetchPlans = () => {
    api
      .get("/plan")
      .then((res) => setData(res.data))
      .catch((e) => console.error(e));
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
  const { t } = useI18n();
  const [loading, setLoading] = useState(false);
  const [currentIndex, setCurrentIndex] = useState(null);

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
    let host = urlParams.get("host") || shopify?.config?.host;
    if (!host) {
      const appBridgeMeta = document.querySelector(
        'meta[name="shopify-setup"]'
      );
      if (appBridgeMeta) {
        try {
          const data = JSON.parse(appBridgeMeta.getAttribute("content"));
          host = data.host;
        } catch (e) {}
      }
    }

    const shopName =
      urlParams.get("shop") ||
      shopify?.config?.shop ||
      (typeof apiShop === "string" ? apiShop : apiShop?.name) ||
      "";

    if (plan.name === "Free" || parseFloat(plan.price) === 0) {
      shopify.toast.show(
        t("plans.subscribingFree", "Subscribing to Free plan...")
      );
      try {
        const result = await api.post("/plans/choose-plan/free");
        if (result.data?.success) {
          shopify.toast.show(
            result.data.message || "Successfully subscribed to Free plan"
          );
          onRefresh();
        } else {
          shopify.toast.show(result.data?.message || "Failed to subscribe", {
            isError: true,
          });
        }
      } catch (error) {
        shopify.toast.show("Something went wrong", { isError: true });
      } finally {
        setLoading(false);
      }
      return;
    }

    try {
      const response = await api.get(
        `/billing/${plan.id}?shop=${shopName}&host=${host}`
      );
      if (response.data && response.data.url) {
        window.open(response.data.url, "_top");
      } else {
        if (response.data.errors && response.data.errors.length > 0) {
          console.error(
            "Shopify Error: " +
              response.data.errors.map((e) => e.message).join(", ")
          );
        }
        setLoading(false);
      }
    } catch (e) {
      setLoading(false);
    }
  };

  const plansToUse = dbPlans.length > 0 ? dbPlans : [];

  const filteredPlans = plansToUse.filter(
    (plan) => plan.name === "Free" || plan.name === "Basic"
  );

  const plansOrder = ["Free", "Basic"];
  const sortedPlans = [...filteredPlans].sort(
    (a, b) => plansOrder.indexOf(a.name) - plansOrder.indexOf(b.name)
  );

  const plans = sortedPlans.map((plan) => {
    const isCurrent = currentPlan ? currentPlan === plan.name : false;
    const isFree = plan.name === "Free";

    let buttonText = t("plans.subscribeNow", "Subscribe Now");
    let buttonDisabled = false;
    let badge = "";

    if (isCurrent) {
      buttonText = t("plans.currentPlan", "Current Plan");
      buttonDisabled = true;
      badge = t("plans.badgeCurrent", "CURRENT PLAN");
    } else if (isFree && currentPlan && currentPlan !== "Free") {
      buttonText = t("plans.downgradeFree", "Downgrade to Free");
      buttonDisabled = false;
    }

    if (plan.name === "Basic" && !isCurrent) {
      badge = t("plans.badgeBestValue", "BEST VALUE");
    }

    return {
      id: plan.id,
      name: plan.name,
      price: plan.price,
      interval:
        plan.interval === "annual"
          ? t("plans.perYear", "/ year")
          : t("plans.perMonth", "/ month"),
      description:
        plan.name === "Free"
          ? t(
              "plans.freeDescription",
              "Perfect for getting started with up to 50,000 events"
            )
          : t(
              "plans.basicDescription",
              "Unlimited event tracking for high-volume stores"
            ),
      badge,
      isCurrent,
      primary: plan.name === "Basic",
      features: plan.features,
      buttonText,
      buttonDisabled,
      isFree,
    };
  });

  return (
    <>
      {loading && <Loader message="Please wait while we prepare your plan." />}
      <Box
        paddingBlock="600"
        paddingInline="400"
        style={{ margin: "0 auto", maxWidth: "950px" }}
      >
        <Page fullWidth>
          <div className="plan-page-wrapper">
            <div className="plan-title-container">
              <h1 className="plan-title">
                {t("plans.pageTitle", "Unlock your catalog's potential")}
              </h1>
              <p className="plan-subheading">
                {t(
                  "plans.pageSubtitle",
                  "Choose the plan that's right for your store's growth."
                )}
              </p>
            </div>

            <div className="plan-cards-container">
              {plans.map((plan, index) => {
                const displayPrice = `$${plan.price}`;

                return (
                  <div
                    className={`plan-card ${plan.primary ? "plan-card-popular" : ""}`}
                    key={index}
                  >
                    {plan.badge && (
                      <div
                        className={
                          plan.isCurrent ? "current-badge" : "recommended-badge"
                        }
                      >
                        {plan.badge}
                      </div>
                    )}

                    <div className="plan-card-header">
                      <div className="plan-card-title">{plan.name}</div>
                      <div className="plan-card-description">
                        {plan.description}
                      </div>
                    </div>

                    <div className="plan-card-price-container">
                      <span className="plan-card-price">{displayPrice}</span>
                      <span className="plan-card-period">{plan.interval}</span>
                    </div>

                    <div className="plan-card-divider"></div>

                    <div className="plan-features-list">
                      {plan.features.map((feature, fIndex) => (
                        <div className="plan-feature-item" key={fIndex}>
                          <div className="plan-feature-icon">
                            <svg
                              width="12"
                              height="10"
                              viewBox="0 0 12 10"
                              fill="none"
                              xmlns="http://www.w3.org/2000/svg"
                            >
                              <path
                                d="M10.6666 1.5L4.24992 7.91667L1.33325 5"
                                stroke="#008060"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                              />
                            </svg>
                          </div>
                          {feature}
                        </div>
                      ))}
                    </div>

                    <button
                      className={`plan-button ${
                        plan.buttonDisabled
                          ? "plan-button-current"
                          : plan.primary
                          ? "plan-button-primary"
                          : "plan-button-secondary"
                      }`}
                      disabled={plan.buttonDisabled || loading}
                      onClick={() => handleSubscribePlan(plan, index)}
                    >
                      {loading && currentIndex === index
                        ? "Processing..."
                        : plan.buttonText}
                    </button>
                  </div>
                );
              })}
            </div>
          </div>
        </Page>
      </Box>
    </>
  );
}
