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

Component.register("coinsnap-button", {
  template: template,
  inject: [["coinsnapApiService"]],
  mixins: [Mixin.getByName("notification")],
  data() {
    return {
      isLoading: false,
      coinsnapStoreId: "",
      coinsnapApiKey: "",
    };
  },
  mounted() {
    const systemConfig = ApiService.getByName("systemConfigApiService");
    systemConfig
      .getValues("CoinsnapShopware.config")
      .then((r) => {
        this.coinsnapApiKey = r["CoinsnapShopware.config.coinsnapApiKey"];
        this.coinsnapStoreId = r["CoinsnapShopware.config.coinsnapStoreId"];
      })
      .catch(() => {});
  },
  computed: {
    isDisabled() {
      return !this.coinsnapStoreId || !this.coinsnapApiKey;
    },
  },
  methods: {
    testConnection() {
      this.isLoading = true;
      if (!this.credentialsExist()) {
        this.isLoading = false;
        return this.createNotificationWarning({
          title: "Coinsnap",
          message: this.$t(
            "coinsnap-coinsnap-test-connection.missing_credentials",
          ),
        });
      }
      this.coinsnapApiService
        .verifyApiKey()
        .then((ApiResponse) => {
          if (ApiResponse.success === false) {
            this.createNotificationWarning({
              title: "Coinsnap",
              message: ApiResponse.message,
            });
            this.isLoading = false;
            return;
          }
          this.createNotificationSuccess({
            title: "Coinsnap",
            message: this.$t("coinsnap-coinsnap-test-connection.success"),
          });

          this.isLoading = false;
          window.location.reload();
        })
        .catch((e) => {
          this.isLoading = false;
          return this.createNotificationError({
            title: "Coinsnap",
            message: this.$t("coinsnap-coinsnap-test-connection.error"),
          });
        });
    },
    saveCredentials() {
      const systemConfig = ApiService.getByName("systemConfigApiService");
      const coinsnapStoreId = document.getElementById(
        "CoinsnapShopware.config.coinsnapStoreId",
      ).value;
      const coinsnapApiKey = document.getElementById(
        "CoinsnapShopware.config.coinsnapApiKey",
      ).value;
      systemConfig.saveValues({
        "CoinsnapShopware.config.coinsnapStoreId": coinsnapStoreId,
        "CoinsnapShopware.config.coinsnapApiKey": coinsnapApiKey,
      });
      window.location.reload();
    },
    credentialsExist() {
      const systemConfig = ApiService.getByName("systemConfigApiService");
      if (
        document.getElementById("CoinsnapShopware.config.coinsnapStoreId")
          .value == "" ||
        document.getElementById("CoinsnapShopware.config.coinsnapApiKey")
          .value == ""
      ) {
        systemConfig.saveValues({
          "CoinsnapShopware.config.coinsnapIntegrationStatus": false,
        });
        return false;
      }
      return true;
    },
  },
});
