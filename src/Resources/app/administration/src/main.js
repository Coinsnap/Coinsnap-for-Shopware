/**
 * Copyright (c) 2023 Coinsnap
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Coinsnap<dev@coinsnap.io>
 */

import "./components/coinsnap-connection-button";
import "./main.scss";
import CoinsnapApiService from "./service/CoinsnapAPI.service";
import localeDE from "./snippets/de_DE.json";
import localeEN from "./snippets/en_GB.json";

const { Application } = Shopware;


Application.addServiceProvider("coinsnapApiService", (container) => {
	const initContainer = Application.getContainer("init");
	return new CoinsnapApiService(
		initContainer.httpClient,
		container.loginService,
	);
});

Shopware.Locale.extend("de-DE", localeDE);
Shopware.Locale.extend("en-GB", localeEN);
