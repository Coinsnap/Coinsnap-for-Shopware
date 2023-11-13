/**
 * Copyright (c) 2023 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

import "./components/btcpay-connection-button";
import "./components/coisnap-connection-button";
import "./main.scss";
import CoinchargeBtcpayApiService from "./service/CoinsnapBTCPayAPI.service";
import CoinchargeCoinsnapApiService from "./service/CoinsnapAPI.service";
import localeDE from "./snippets/de_DE.json";
import localeEN from "./snippets/en_GB.json";

const { Application } = Shopware;

Application.addServiceProvider("coinsnapBTCPayApiService", (container) => {
	const initContainer = Application.getContainer("init");
	return new CoinchargeBtcpayApiService(
		initContainer.httpClient,
		container.loginService,
	);
});

Application.addServiceProvider("coinsnapApiService", (container) => {
	const initContainer = Application.getContainer("init");
	return new CoinchargeCoinsnapApiService(
		initContainer.httpClient,
		container.loginService,
	);
});

Shopware.Locale.extend("de-DE", localeDE);
Shopware.Locale.extend("en-GB", localeEN);
