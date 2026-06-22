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

Component.register("coinsnap-button", {
  template: template,
  inject: ["coinsnapApiService"],
  mixins: [Mixin.getByName("notification")],
  data() {
    return {
      isLoading: false,
    };
  },
  methods: {
    // Test connection validates the SAVED credentials: the server-side
    // verify endpoint reads them from the system config, so the values must
    // be persisted first (via Shopware's Save button). Reading the saved
    // config here avoids depending on the admin form's DOM, which changed
    // in Shopware 6.7 and no longer exposes the config key as an element id.
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
              window.location.reload();
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
