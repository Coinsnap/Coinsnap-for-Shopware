/**
 * Copyright (c) 2024 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

const { Component, Mixin, ApiService } = Shopware;
import template from "./btcpay-connection-button.html.twig";
import "./btcpay-connection-button.scss";

const CONFIG_DOMAIN = "CoinsnapShopware.config";
// sessionStorage flag: show the success toast once after the post-connect reload.
const CONNECT_SUCCESS_FLAG = "coinsnapBtcpayConnectSuccess";

Component.register("coinsnap-btcpay-buttons", {
  template: template,
  inject: ["coinsnapBTCPayApiService"],
  mixins: [Mixin.getByName("notification")],
  data() {
    return {
      isLoading: false,
      // Gates the Test button until credentials are saved.
      credentialsReady: false,
    };
  },
  mounted() {
    this.showSuccessAfterReload();
    this.loadConnectionState();
    // Saving config doesn't remount, so poll to keep the button state in sync.
    this.credentialsPoll = setInterval(() => this.refreshCredentialsReady(), 750);
    // Refresh on return to the tab/window (e.g. back from BTCPay authorization).
    this.refreshOnFocus = () => this.refreshCredentialsReady();
    window.addEventListener("focus", this.refreshOnFocus);
    document.addEventListener("visibilitychange", this.refreshOnFocus);
  },
  beforeUnmount() {
    clearInterval(this.credentialsPoll);
    window.removeEventListener("focus", this.refreshOnFocus);
    document.removeEventListener("visibilitychange", this.refreshOnFocus);
  },
  methods: {
    removeTrailingSlash(serverUrl) {
      return serverUrl.replace(/\/$/, "");
    },
    // Reload after a successful connection so the read-only "Connected" checkbox
    // reflects the saved server state; the in-place form model isn't reliably
    // reactive for disabled fields. Credentials are already saved at this point,
    // so nothing editable is lost.
    finishWithReload() {
      try {
        sessionStorage.setItem(CONNECT_SUCCESS_FLAG, "1");
      } catch (e) {
        /* sessionStorage unavailable, reload anyway */
      }
      window.location.reload();
    },
    // Show the success notification once, after the post-connect reload.
    showSuccessAfterReload() {
      let flagged = false;
      try {
        flagged = sessionStorage.getItem(CONNECT_SUCCESS_FLAG) === "1";
      } catch (e) {
        flagged = false;
      }
      if (!flagged) {
        return;
      }
      try {
        sessionStorage.removeItem(CONNECT_SUCCESS_FLAG);
      } catch (e) {
        /* ignore */
      }
      this.createNotificationSuccess({
        title: "BTCPay Server",
        message: this.$t("coinsnap-btcpay-test-connection.success"),
      });
    },
    // Toggle the Test button from saved credentials; returns the values.
    refreshCredentialsReady() {
      const systemConfig = ApiService.getByName("systemConfigApiService");
      return systemConfig
        .getValues(CONFIG_DOMAIN)
        .then((values) => {
          this.credentialsReady = Boolean(
            values[`${CONFIG_DOMAIN}.btcpayServerUrl`] &&
              values[`${CONFIG_DOMAIN}.btcpayApiKey`] &&
              values[`${CONFIG_DOMAIN}.btcpayServerStoreId`],
          );
          return values;
        })
        .catch(() => ({}));
    },
    // Auto-finish the connection after returning from BTCPay; once per session.
    loadConnectionState() {
      this.refreshCredentialsReady().then((values) => {
        const apiKey = values[`${CONFIG_DOMAIN}.btcpayApiKey`];
        const storeId = values[`${CONFIG_DOMAIN}.btcpayServerStoreId`];
        const connected = values[`${CONFIG_DOMAIN}.btcpayIntegrationStatus`];

        let alreadyTried = false;
        try {
          alreadyTried = sessionStorage.getItem("coinsnapBtcpayAutoFinish") === "1";
        } catch (e) {
          alreadyTried = false;
        }

        if (apiKey && storeId && !connected && !alreadyTried) {
          try {
            sessionStorage.setItem("coinsnapBtcpayAutoFinish", "1");
          } catch (e) {
            /* sessionStorage unavailable, proceed anyway */
          }
          this.createNotificationInfo({
            title: "BTCPay Server",
            message: this.$t("coinsnap-btcpay-test-connection.finishing"),
          });
          this.testConnection();
        }
      });
    },
    // Opens BTCPay's API-key authorization page (server URL must be saved first).
    generateAPIKey() {
      const systemConfig = ApiService.getByName("systemConfigApiService");

      systemConfig.getValues(CONFIG_DOMAIN).then((values) => {
        const serverUrl = values[`${CONFIG_DOMAIN}.btcpayServerUrl`];
        if (!serverUrl) {
          return this.createNotificationWarning({
            title: "BTCPay Server",
            message: this.$t("coinsnap-btcpay-generate-credentials.missing_server"),
          });
        }

        const filteredUrl = this.removeTrailingSlash(serverUrl);
        // One-time nonce guarding the unauthenticated callback; require a CSPRNG.
        if (!window.crypto || !window.crypto.randomUUID) {
          return this.createNotificationError({
            title: "BTCPay Server",
            message: this.$t("coinsnap-btcpay-generate-credentials.insecure_context"),
          });
        }
        const state = window.crypto.randomUUID();
        const clearedPathname = window.location.pathname.replace("/admin", "/");
        const redirectUrl =
          window.location.origin +
          clearedPathname +
          "api/_action/coinsnap/btcpay_credentials?state=" +
          encodeURIComponent(state);

        return systemConfig
          .saveValues({
            [`${CONFIG_DOMAIN}.btcpayServerUrl`]: filteredUrl,
            [`${CONFIG_DOMAIN}.btcpayAuthState`]: state,
            [`${CONFIG_DOMAIN}.btcpayApiKey`]: "",
            [`${CONFIG_DOMAIN}.btcpayServerStoreId`]: "",
            [`${CONFIG_DOMAIN}.btcpayWebhookId`]: "",
            [`${CONFIG_DOMAIN}.btcpayWebhookSecret`]: "",
            [`${CONFIG_DOMAIN}.btcpayIntegrationStatus`]: false,
            [`${CONFIG_DOMAIN}.btcpayStorePaymentMethodBTC`]: false,
            [`${CONFIG_DOMAIN}.btcpayStorePaymentMethodLightning`]: false,
          })
          .then(() => {
            window.open(
              filteredUrl +
                "/api-keys/authorize/?applicationName=CoinsnapShopwarePlugin&permissions=btcpay.store.cancreateinvoice&permissions=btcpay.store.canviewinvoices&permissions=btcpay.store.webhooks.canmodifywebhooks&permissions=btcpay.store.canviewstoresettings&selectiveStores=true&redirect=" +
                redirectUrl,
              "_blank",
              "noopener",
            );
          });
      })
      .catch(() => {
        this.createNotificationError({
          title: "BTCPay Server",
          message: this.$t("coinsnap-btcpay-test-connection.error"),
        });
      });
    },
    testConnection() {
      this.isLoading = true;
      const systemConfig = ApiService.getByName("systemConfigApiService");

      systemConfig
        .getValues(CONFIG_DOMAIN)
        .then((values) => {
          const serverUrl = values[`${CONFIG_DOMAIN}.btcpayServerUrl`];
          const apiKey = values[`${CONFIG_DOMAIN}.btcpayApiKey`];
          const storeId = values[`${CONFIG_DOMAIN}.btcpayServerStoreId`];

          if (!serverUrl || !apiKey || !storeId) {
            this.isLoading = false;
            systemConfig.saveValues({
              [`${CONFIG_DOMAIN}.btcpayIntegrationStatus`]: false,
            });
            return this.createNotificationWarning({
              title: "BTCPay Server",
              message: this.$t(
                "coinsnap-btcpay-test-connection.missing_credentials",
              ),
            });
          }

          return this.coinsnapBTCPayApiService
            .verifyApiKey()
            .then((ApiResponse) => {
              this.isLoading = false;
              if (ApiResponse.success === false) {
                return this.createNotificationWarning({
                  title: "BTCPay Server",
                  message: ApiResponse.message,
                });
              }
              // Reload so the read-only "Connected" checkbox reflects the saved
              // server state; the success toast is shown after the reload.
              this.finishWithReload();
            })
            .catch(() => {
              this.isLoading = false;
              this.createNotificationError({
                title: "BTCPay Server",
                message: this.$t("coinsnap-btcpay-test-connection.error"),
              });
            });
        })
        .catch(() => {
          this.isLoading = false;
          this.createNotificationError({
            title: "BTCPay Server",
            message: this.$t("coinsnap-btcpay-test-connection.error"),
          });
        });
    },
  },
});
