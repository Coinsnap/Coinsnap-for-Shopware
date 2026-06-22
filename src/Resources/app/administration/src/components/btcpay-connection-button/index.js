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

Component.register("coinsnap-btcpay-buttons", {
  template: template,
  inject: ["coinsnapBTCPayApiService"],
  mixins: [Mixin.getByName("notification")],
  data() {
    return {
      isLoading: false,
      // Gates the "Test connection" button: it only makes sense once the
      // BTCPay URL, API key and store id are saved.
      credentialsReady: false,
    };
  },
  mounted() {
    this.loadConnectionState();
    // Saving config in Shopware doesn't remount this component, so poll the
    // saved values to keep the button's enabled state in sync after the
    // merchant saves credentials.
    this.credentialsPoll = setInterval(() => this.refreshCredentialsReady(), 2000);
  },
  beforeUnmount() {
    clearInterval(this.credentialsPoll);
  },
  methods: {
    removeTrailingSlash(serverUrl) {
      return serverUrl.replace(/\/$/, "");
    },
    // Reads the saved credentials and toggles the Test button accordingly.
    // Returns the values so callers can reuse them without a second request.
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
    // When the merchant has just returned from BTCPay's authorization
    // (credentials present but not yet verified), finishes the connection
    // automatically so they aren't left with stored keys but no registered
    // webhook. Guarded with a session flag so a persistently failing
    // connection isn't retried on every visit.
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
    // Opens BTCPay's API-key authorization page. The server URL must be saved
    // first: Shopware 6.7 no longer exposes the config key as a DOM element id,
    // so we read the persisted value via the system config API instead.
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
        // One-time nonce echoed back by BTCPay so the unauthenticated callback
        // can reject forged credential overwrites.
        const state =
          window.crypto && window.crypto.randomUUID
            ? window.crypto.randomUUID()
            : String(Date.now()) + Math.random().toString(36).slice(2);
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
              this.createNotificationSuccess({
                title: "BTCPay Server",
                message: this.$t("coinsnap-btcpay-test-connection.success"),
              });
              window.location.reload();
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
