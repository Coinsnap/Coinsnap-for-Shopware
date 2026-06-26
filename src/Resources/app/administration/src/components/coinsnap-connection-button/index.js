/**
 * Copyright (c) 2023 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

const { Component, Mixin, ApiService } = Shopware;
import template from "./coinsnap-connection-button.html.twig";
import "./coinsnap-connection-button.scss";

const CONFIG_DOMAIN = "CoinsnapShopware.config";

// Read-only fields the server fills in after a successful connection.
const STATUS_KEYS = [
  "coinsnapIntegrationStatus",
  "coinsnapWebhookId",
  "coinsnapWebhookSecret",
];

Component.register("coinsnap-button", {
  template: template,
  inject: ["coinsnapApiService"],
  mixins: [Mixin.getByName("notification")],
  data() {
    return {
      isLoading: false,
      // Gates the Test button until credentials are saved.
      credentialsReady: false,
    };
  },
  mounted() {
    this.refreshCredentialsReady();
    // Saving config doesn't remount, so poll to keep the button state in sync.
    this.credentialsPoll = setInterval(() => this.refreshCredentialsReady(), 2000);
  },
  beforeUnmount() {
    clearInterval(this.credentialsPoll);
  },
  methods: {
    // Walk up to the parent sw-system-config that owns the config form model.
    findSystemConfigParent() {
      let parent = this.$parent;
      while (parent) {
        if (parent.actualConfigData && typeof parent.actualConfigData === "object") {
          return parent;
        }
        parent = parent.$parent;
      }
      return null;
    },
    // Push the server-side status values into the config form so the read-only
    // switches reflect the connection result without a full page reload.
    syncStatusToParent() {
      const parent = this.findSystemConfigParent();
      if (!parent) {
        // Fall back to a reload if the form model can't be reached.
        window.location.reload();
        return Promise.resolve();
      }
      const salesChannelId = parent.currentSalesChannelId ?? null;
      const systemConfig = ApiService.getByName("systemConfigApiService");
      return systemConfig
        .getValues(CONFIG_DOMAIN, salesChannelId)
        .then((values) => {
          const bucket = parent.actualConfigData?.[salesChannelId];
          if (!bucket) {
            return;
          }
          const updated = { ...bucket };
          STATUS_KEYS.forEach((key) => {
            const fullKey = `${CONFIG_DOMAIN}.${key}`;
            if (fullKey in values) {
              updated[fullKey] = values[fullKey];
            }
          });
          parent.actualConfigData[salesChannelId] = updated;
        })
        .catch(() => {});
    },
    // Toggle the Test button from saved credentials; returns the values.
    refreshCredentialsReady() {
      const systemConfig = ApiService.getByName("systemConfigApiService");
      return systemConfig
        .getValues(CONFIG_DOMAIN)
        .then((values) => {
          this.credentialsReady = Boolean(
            values[`${CONFIG_DOMAIN}.coinsnapStoreId`] &&
              values[`${CONFIG_DOMAIN}.coinsnapApiKey`],
          );
          return values;
        })
        .catch(() => ({}));
    },
    // Verifies the SAVED credentials (server reads them from system config).
    testConnection() {
      this.isLoading = true;
      const systemConfig = ApiService.getByName("systemConfigApiService");

      systemConfig
        .getValues(CONFIG_DOMAIN)
        .then((values) => {
          const storeId = values[`${CONFIG_DOMAIN}.coinsnapStoreId`];
          const apiKey = values[`${CONFIG_DOMAIN}.coinsnapApiKey`];

          if (!storeId || !apiKey) {
            this.isLoading = false;
            systemConfig.saveValues({
              [`${CONFIG_DOMAIN}.coinsnapIntegrationStatus`]: false,
            });
            return this.createNotificationWarning({
              title: "Coinsnap",
              message: this.$t(
                "coinsnap-coinsnap-test-connection.missing_credentials",
              ),
            });
          }

          return this.coinsnapApiService
            .verifyApiKey()
            .then((ApiResponse) => {
              this.isLoading = false;
              if (ApiResponse.success === false) {
                return this.createNotificationWarning({
                  title: "Coinsnap",
                  message: ApiResponse.message,
                });
              }
              this.createNotificationSuccess({
                title: "Coinsnap",
                message: this.$t("coinsnap-coinsnap-test-connection.success"),
              });
              // Refresh gating state and the read-only status switches without a
              // hard reload (avoids losing unsaved input).
              this.refreshCredentialsReady();
              this.syncStatusToParent();
            })
            .catch(() => {
              this.isLoading = false;
              this.createNotificationError({
                title: "Coinsnap",
                message: this.$t("coinsnap-coinsnap-test-connection.error"),
              });
            });
        })
        .catch(() => {
          this.isLoading = false;
          this.createNotificationError({
            title: "Coinsnap",
            message: this.$t("coinsnap-coinsnap-test-connection.error"),
          });
        });
    },
  },
});
