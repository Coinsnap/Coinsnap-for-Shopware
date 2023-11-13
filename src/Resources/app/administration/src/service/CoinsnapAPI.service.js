/**
 * Copyright (c) 2023 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

const ApiService = Shopware.Classes.ApiService;

export default class CoinsnapApiService extends ApiService {
  constructor(httpClient, loginService, apiEndpoint = "coinsnap") {
    super(httpClient, loginService, apiEndpoint);
  }
  verifyApiKey() {
    const apiRoute = `/_action/${this.getApiBasePath()}/coinsnap_verify`;
    const headers = this.getBasicHeaders();

    return this.httpClient
      .get(apiRoute, { headers })
      .then((response) => {
        return ApiService.handleResponse(response);
      })
      .catch((error) => {
        throw error.message;
      });
  }
}
