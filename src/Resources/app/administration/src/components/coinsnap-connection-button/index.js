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
// sessionStorage flag: show the success toast once after the post-connect reload.
const CONNECT_SUCCESS_FLAG = "coinsnapConnectSuccess";

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
    this.showSuccessAfterReload();
    this.refreshCredentialsReady();
    // Saving config doesn't remount, so poll to keep the button state in sync.
    this.credentialsPoll = setInterval(() => this.refreshCredentialsReady(), 2000);
  },
  beforeUnmount() {
    clearInterval(this.credentialsPoll);
  },
  methods: {
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
        title: "Coinsnap",
        message: this.$t("coinsnap-coinsnap-test-connection.success"),
      });
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
              // Reload so the read-only "Connected" checkbox reflects the saved
              // server state; the success toast is shown after the reload.
              this.finishWithReload();
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
