!(function(e) {
  var n = {};
  function t(i) {
    if (n[i]) return n[i].exports;
    var o = (n[i] = { i: i, l: !1, exports: {} });
    return e[i].call(o.exports, o, o.exports, t), (o.l = !0), o.exports;
  }
  (t.m = e),
    (t.c = n),
    (t.d = function(e, n, i) {
      t.o(e, n) || Object.defineProperty(e, n, { enumerable: !0, get: i });
    }),
    (t.r = function(e) {
      "undefined" != typeof Symbol &&
        Symbol.toStringTag &&
        Object.defineProperty(e, Symbol.toStringTag, { value: "Module" }),
        Object.defineProperty(e, "__esModule", { value: !0 });
    }),
    (t.t = function(e, n) {
      if ((1 & n && (e = t(e)), 8 & n)) return e;
      if (4 & n && "object" == typeof e && e && e.__esModule) return e;
      var i = Object.create(null);
      if (
        (t.r(i),
          Object.defineProperty(i, "default", { enumerable: !0, value: e }),
          2 & n && "string" != typeof e)
      )
        for (var o in e)
          t.d(
            i,
            o,
            function(n) {
              return e[n];
            }.bind(null, o),
          );
      return i;
    }),
    (t.n = function(e) {
      var n =
        e && e.__esModule
          ? function() {
            return e.default;
          }
          : function() {
            return e;
          };
      return t.d(n, "a", n), n;
    }),
    (t.o = function(e, n) {
      return Object.prototype.hasOwnProperty.call(e, n);
    }),
    (t.p = "/bundles/coinsnapshopware/"),
    t((t.s = "IxbO"));
})({
  "0Hz+": function(e) {
    e.exports = JSON.parse(
      '{"coinsnap-btcpay-generate-credentials":{"button":"Generate credentials","missing_server":"You need to provide BTCPay Server url","info":"You will be redirected to the provided BTCPayServer url to generate required credentials"},"coinsnap-btcpay-test-connection":{"button":"Test connection","success":"The plugin is connected to the server","error":"Something went wrong. Check if credentials are valid and try again.","missing_credentials":"You need to generate credentials first."},"coinsnap-coinsnap-test-connection":{"save":"Save credentials","button":"Test connection","success":"The plugin is connected to the server","error":"Something went wrong. Check if credentials are valid and try again.","missing_credentials":"You need to save API key and store ID first."}}',
    );
  },
  AfEz: function(e, n, t) {
    var i = t("bWgD");
    i.__esModule && (i = i.default),
      "string" == typeof i && (i = [[e.i, i, ""]]),
      i.locals && (e.exports = i.locals);
    (0, t("SZ7m").default)("be634178", i, !0, {});
  },
  IxbO: function(e, n, t) {
    "use strict";
    t.r(n);
    t("ayfy");
    var i = Shopware,
      o = i.Component,
      r = i.Mixin,
      a = i.ApiService;
    o.register("coinsnap-btcpay-buttons", {
      template:
        '<sw-container columns="repeat(2, auto)" gap="0px 10px">\n\t<sw-button v-tooltip.top="{ message:$tc(\'coinsnap-btcpay-generate-credentials.info\')}" class="sw-button-coinsnap" @click="generateAPIKey">{{ $tc(\'coinsnap-btcpay-generate-credentials.button\') }}</sw-button>\n\t<sw-button-process class="sw-button-coinsnap" :process-success="isLoading" :is-loading="isLoading" @click="testConnection">{{ $tc(\'coinsnap-btcpay-test-connection.button\') }}</sw-button-process>\n</sw-container>\n',
      inject: [["coinsnapBTCPayApiService"]],
      mixins: [r.getByName("notification")],
      data: function() {
        return {
          isLoading: !1,
          config: { "CoinsnapShopware.config.btcpayServerUrl": "" },
        };
      },
      methods: {
        generateAPIKey: function() {
          var e = a.getByName("systemConfigApiService"),
            n = document.getElementById(
              "CoinsnapShopware.config.btcpayServerUrl",
            ).value;
          if (!n)
            return this.createNotificationWarning({
              title: "BTCPay Server",
              message: this.$tc(
                "coinsnap-btcpay-generate-credentials.missing_server",
              ),
            });
          var t = this.removeTrailingSlash(n);
          this.config["CoinsnapShopware.config.btcpayServerUrl"] = t;
          var i = window.location.pathname.replace("/admin", "/"),
            o = window.location.origin + i + "api/_action/coinsnap/credentials";
          return (
            e.saveValues({
              "CoinsnapShopware.config.btcpayServerUrl":
                this.config["CoinsnapShopware.config.btcpayServerUrl"],
              "CoinsnapShopware.config.btcpayApiKey": "",
              "CoinsnapShopware.config.btcpayServerStoreId": "",
              "CoinsnapShopware.config.btcpayWebhookId": "",
              "CoinsnapShopware.config.btcpayWebhookSecret": "",
              "CoinsnapShopware.config.integrationStatus": !1,
              "CoinsnapShopware.config.btcpayStorePaymentMethodBTC": !1,
              "CoinsnapShopware.config.btcpayStorePaymentMethodLightning": !1,
              "CoinsnapShopware.config.btcpayStorePaymentMethodMonero": !1,
              "CoinsnapShopware.config.btcpayStorePaymentMethodLitecoin": !1,
            }),
            window.open(
              t +
              "/api-keys/authorize/?applicationName=CoinsnapShopwarePlugin&permissions=btcpay.store.cancreateinvoice&permissions=btcpay.store.canviewinvoices&permissions=btcpay.store.webhooks.canmodifywebhooks&permissions=btcpay.store.canviewstoresettings&selectiveStores=true&redirect=" +
              o,
              "_blank",
              "noopener",
            )
          );
        },
        removeTrailingSlash: function(e) {
          return e.replace(/\/$/, "");
        },
        testConnection: function() {
          var e = this;
          if (((this.isLoading = !0), !this.credentialsExist()))
            return (
              (this.isLoading = !1),
              this.createNotificationWarning({
                title: "BTCPay Server",
                message: this.$tc(
                  "coinsnap-btcpay-test-connection.missing_credentials",
                ),
              })
            );
          this.coinsnapBTCPayApiService
            .verifyApiKey()
            .then(function(n) {
              if (!1 === n.success)
                return (
                  e.createNotificationWarning({
                    title: "BTCPay Server",
                    message: n.message,
                  }),
                  void (e.isLoading = !1)
                );
              e.createNotificationSuccess({
                title: "BTCPay Server",
                message: e.$tc("coinsnap-btcpay-test-connection.success"),
              }),
                (e.isLoading = !1),
                window.location.reload();
            })
            .catch(function(n) {
              return (
                (e.isLoading = !1),
                e.createNotificationError({
                  title: "BTCPay Server",
                  message: e.$tc("coinsnap-btcpay-test-connection.error"),
                })
              );
            });
        },
        credentialsExist: function() {
          return (
            "" !==
            document.getElementById("CoinsnapShopware.config.btcpayServerUrl")
              .value &&
            "" !==
            document.getElementById("CoinsnapShopware.config.btcpayApiKey")
              .value &&
            "" !==
            document.getElementById(
              "CoinsnapShopware.config.btcpayServerStoreId",
            ).value
          );
        },
      },
    });
    t("AfEz");
    var s = Shopware,
      c = s.Component,
      u = s.Mixin,
      p = s.ApiService;
    c.register("coinsnap-button", {
      template:
        '<sw-container columns="repeat(2, auto)" gap="0px 10px">\n\t<sw-button v-tooltip.top="{ message:$tc(\'coinsnap-coinsnap-test-connection.save\')}" class="sw-button-coinsnap" @click="saveCredentials">{{ $tc(\'coinsnap-coinsnap-test-connection.save\') }}</sw-button>\n\t<sw-button-process class="sw-button-coinsnap" :process-success="isLoading" :disabled="isDisabled" :is-loading="isLoading" @click="testConnection">{{ $tc(\'coinsnap-coinsnap-test-connection.button\') }}</sw-button-process>\n</sw-container>\n',
      inject: [["coinsnapApiService"]],
      mixins: [u.getByName("notification")],
      data: function() {
        return { isLoading: !1, coinsnapStoreId: "", coinsnapApiKey: "" };
      },
      mounted: function() {
        var e = this;
        p.getByName("systemConfigApiService")
          .getValues("CoinsnapShopware.config")
          .then(function(n) {
            (e.coinsnapApiKey = n["CoinsnapShopware.config.coinsnapApiKey"]),
              (e.coinsnapStoreId =
                n["CoinsnapShopware.config.coinsnapStoreId"]);
          })
          .catch(function(e) {
            return console.log(e);
          });
      },
      computed: {
        isDisabled: function() {
          if (!this.coinsnapStoreId || !this.coinsnapApiKey) return !0;
        },
      },
      methods: {
        testConnection: function() {
          var e = this;
          if (((this.isLoading = !0), !this.credentialsExist()))
            return (
              (this.isLoading = !1),
              this.createNotificationWarning({
                title: "BTCPay Server",
                message: this.$tc(
                  "coinsnap-coinsnap-test-connection.missing_credentials",
                ),
              })
            );
          this.coinsnapApiService
            .verifyApiKey()
            .then(function(n) {
              if (!1 === n.success)
                return (
                  e.createNotificationWarning({
                    title: "Coinsnap",
                    message: n.message,
                  }),
                  void (e.isLoading = !1)
                );
              e.createNotificationSuccess({
                title: "Coinsnap",
                message: e.$tc("coinsnap-coinsnap-test-connection.success"),
              }),
                (e.isLoading = !1),
                window.location.reload();
            })
            .catch(function(n) {
              return (
                (e.isLoading = !1),
                e.createNotificationError({
                  title: "Coinsnap",
                  message: e.$tc("coinsnap-coinsnap-test-connection.error"),
                })
              );
            });
        },
        saveCredentials: function() {
          var e = p.getByName("systemConfigApiService"),
            n = document.getElementById(
              "CoinsnapShopware.config.coinsnapStoreId",
            ).value,
            t = document.getElementById(
              "CoinsnapShopware.config.coinsnapApiKey",
            ).value;
          e.saveValues({
            "CoinsnapShopware.config.coinsnapStoreId": n,
            "CoinsnapShopware.config.coinsnapApiKey": t,
          }),
            window.location.reload();
        },
        credentialsExist: function() {
          var e = p.getByName("systemConfigApiService");
          return (
            ("" !=
              document.getElementById("CoinsnapShopware.config.coinsnapStoreId")
                .value &&
              "" !=
              document.getElementById(
                "CoinsnapShopware.config.coinsnapApiKey",
              ).value) ||
            (e.saveValues({
              "CoinsnapShopware.config.coinsnapIntegrationStatus": !1,
            }),
              !1)
          );
        },
      },
    });
    t("YLip");
    function l(e) {
      return (
        (l =
          "function" == typeof Symbol && "symbol" == typeof Symbol.iterator
            ? function(e) {
              return typeof e;
            }
            : function(e) {
              return e &&
                "function" == typeof Symbol &&
                e.constructor === Symbol &&
                e !== Symbol.prototype
                ? "symbol"
                : typeof e;
            }),
        l(e)
      );
    }
    function f(e, n) {
      if (!(e instanceof n))
        throw new TypeError("Cannot call a class as a function");
    }
    function d(e, n) {
      for (var t = 0; t < n.length; t++) {
        var i = n[t];
        (i.enumerable = i.enumerable || !1),
          (i.configurable = !0),
          "value" in i && (i.writable = !0),
          Object.defineProperty(
            e,
            ((o = i.key),
              (r = void 0),
              (r = (function(e, n) {
                if ("object" !== l(e) || null === e) return e;
                var t = e[Symbol.toPrimitive];
                if (void 0 !== t) {
                  var i = t.call(e, n || "default");
                  if ("object" !== l(i)) return i;
                  throw new TypeError(
                    "@@toPrimitive must return a primitive value.",
                  );
                }
                return ("string" === n ? String : Number)(e);
              })(o, "string")),
              "symbol" === l(r) ? r : String(r)),
            i,
          );
      }
      var o, r;
    }
    function y(e, n) {
      return (
        (y = Object.setPrototypeOf
          ? Object.setPrototypeOf.bind()
          : function(e, n) {
            return (e.__proto__ = n), e;
          }),
        y(e, n)
      );
    }
    function g(e) {
      var n = (function() {
        if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
        if (Reflect.construct.sham) return !1;
        if ("function" == typeof Proxy) return !0;
        try {
          return (
            Boolean.prototype.valueOf.call(
              Reflect.construct(Boolean, [], function() { }),
            ),
            !0
          );
        } catch (e) {
          return !1;
        }
      })();
      return function() {
        var t,
          i = v(e);
        if (n) {
          var o = v(this).constructor;
          t = Reflect.construct(i, arguments, o);
        } else t = i.apply(this, arguments);
        return h(this, t);
      };
    }
    function h(e, n) {
      if (n && ("object" === l(n) || "function" == typeof n)) return n;
      if (void 0 !== n)
        throw new TypeError(
          "Derived constructors may only return object or undefined",
        );
      return (function(e) {
        if (void 0 === e)
          throw new ReferenceError(
            "this hasn't been initialised - super() hasn't been called",
          );
        return e;
      })(e);
    }
    function v(e) {
      return (
        (v = Object.setPrototypeOf
          ? Object.getPrototypeOf.bind()
          : function(e) {
            return e.__proto__ || Object.getPrototypeOf(e);
          }),
        v(e)
      );
    }
    var b = Shopware.Classes.ApiService,
      m = (function(e) {
        !(function(e, n) {
          if ("function" != typeof n && null !== n)
            throw new TypeError(
              "Super expression must either be null or a function",
            );
          (e.prototype = Object.create(n && n.prototype, {
            constructor: { value: e, writable: !0, configurable: !0 },
          })),
            Object.defineProperty(e, "prototype", { writable: !1 }),
            n && y(e, n);
        })(r, e);
        var n,
          t,
          i,
          o = g(r);
        function r(e, n) {
          var t =
            arguments.length > 2 && void 0 !== arguments[2]
              ? arguments[2]
              : "coinsnap";
          return f(this, r), o.call(this, e, n, t);
        }
        return (
          (n = r),
          (t = [
            {
              key: "verifyApiKey",
              value: function() {
                var e = "/_action/".concat(this.getApiBasePath(), "/verify"),
                  n = this.getBasicHeaders();
                return this.httpClient
                  .get(e, { headers: n })
                  .then(function(e) {
                    return b.handleResponse(e);
                  })
                  .catch(function(e) {
                    throw e;
                  });
              },
            },
            {
              key: "generateWebhook",
              value: function() {
                var e = "/_action/".concat(this.getApiBasePath(), "/webhook");
                return this.httpClient
                  .post(e, {}, { headers: this.getBasicHeaders() })
                  .then(function(e) {
                    return b.handleResponse(e);
                  })
                  .catch(function(e) {
                    throw e;
                  });
              },
            },
          ]) && d(n.prototype, t),
          i && d(n, i),
          Object.defineProperty(n, "prototype", { writable: !1 }),
          r
        );
      })(b);
    function S(e) {
      return (
        (S =
          "function" == typeof Symbol && "symbol" == typeof Symbol.iterator
            ? function(e) {
              return typeof e;
            }
            : function(e) {
              return e &&
                "function" == typeof Symbol &&
                e.constructor === Symbol &&
                e !== Symbol.prototype
                ? "symbol"
                : typeof e;
            }),
        S(e)
      );
    }
    function w(e, n) {
      if (!(e instanceof n))
        throw new TypeError("Cannot call a class as a function");
    }
    function C(e, n) {
      for (var t = 0; t < n.length; t++) {
        var i = n[t];
        (i.enumerable = i.enumerable || !1),
          (i.configurable = !0),
          "value" in i && (i.writable = !0),
          Object.defineProperty(
            e,
            ((o = i.key),
              (r = void 0),
              (r = (function(e, n) {
                if ("object" !== S(e) || null === e) return e;
                var t = e[Symbol.toPrimitive];
                if (void 0 !== t) {
                  var i = t.call(e, n || "default");
                  if ("object" !== S(i)) return i;
                  throw new TypeError(
                    "@@toPrimitive must return a primitive value.",
                  );
                }
                return ("string" === n ? String : Number)(e);
              })(o, "string")),
              "symbol" === S(r) ? r : String(r)),
            i,
          );
      }
      var o, r;
    }
    function P(e, n) {
      return (
        (P = Object.setPrototypeOf
          ? Object.setPrototypeOf.bind()
          : function(e, n) {
            return (e.__proto__ = n), e;
          }),
        P(e, n)
      );
    }
    function B(e) {
      var n = (function() {
        if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
        if (Reflect.construct.sham) return !1;
        if ("function" == typeof Proxy) return !0;
        try {
          return (
            Boolean.prototype.valueOf.call(
              Reflect.construct(Boolean, [], function() { }),
            ),
            !0
          );
        } catch (e) {
          return !1;
        }
      })();
      return function() {
        var t,
          i = O(e);
        if (n) {
          var o = O(this).constructor;
          t = Reflect.construct(i, arguments, o);
        } else t = i.apply(this, arguments);
        return A(this, t);
      };
    }
    function A(e, n) {
      if (n && ("object" === S(n) || "function" == typeof n)) return n;
      if (void 0 !== n)
        throw new TypeError(
          "Derived constructors may only return object or undefined",
        );
      return (function(e) {
        if (void 0 === e)
          throw new ReferenceError(
            "this hasn't been initialised - super() hasn't been called",
          );
        return e;
      })(e);
    }
    function O(e) {
      return (
        (O = Object.setPrototypeOf
          ? Object.getPrototypeOf.bind()
          : function(e) {
            return e.__proto__ || Object.getPrototypeOf(e);
          }),
        O(e)
      );
    }
    var _ = Shopware.Classes.ApiService,
      j = (function(e) {
        !(function(e, n) {
          if ("function" != typeof n && null !== n)
            throw new TypeError(
              "Super expression must either be null or a function",
            );
          (e.prototype = Object.create(n && n.prototype, {
            constructor: { value: e, writable: !0, configurable: !0 },
          })),
            Object.defineProperty(e, "prototype", { writable: !1 }),
            n && P(e, n);
        })(r, e);
        var n,
          t,
          i,
          o = B(r);
        function r(e, n) {
          var t =
            arguments.length > 2 && void 0 !== arguments[2]
              ? arguments[2]
              : "coinsnap";
          return w(this, r), o.call(this, e, n, t);
        }
        return (
          (n = r),
          (t = [
            {
              key: "verifyApiKey",
              value: function() {
                var e = "/_action/".concat(
                  this.getApiBasePath(),
                  "/coinsnap_verify",
                ),
                  n = this.getBasicHeaders();
                return this.httpClient
                  .get(e, { headers: n })
                  .then(function(e) {
                    return _.handleResponse(e);
                  })
                  .catch(function(e) {
                    throw e.message;
                  });
              },
            },
          ]) && C(n.prototype, t),
          i && C(n, i),
          Object.defineProperty(n, "prototype", { writable: !1 }),
          r
        );
      })(_),
      x = t("vWtx"),
      T = t("0Hz+"),
      E = Shopware.Application;
    E.addServiceProvider("coinsnapBTCPayApiService", function(e) {
      var n = E.getContainer("init");
      return new m(n.httpClient, e.loginService);
    }),
      E.addServiceProvider("coinsnapApiService", function(e) {
        var n = E.getContainer("init");
        return new j(n.httpClient, e.loginService);
      }),
      Shopware.Locale.extend("de-DE", x),
      Shopware.Locale.extend("en-GB", T);
  },
  "L+7P": function(e, n, t) { },
  SZ7m: function(e, n, t) {
    "use strict";
    function i(e, n) {
      for (var t = [], i = {}, o = 0; o < n.length; o++) {
        var r = n[o],
          a = r[0],
          s = { id: e + ":" + o, css: r[1], media: r[2], sourceMap: r[3] };
        i[a] ? i[a].parts.push(s) : t.push((i[a] = { id: a, parts: [s] }));
      }
      return t;
    }
    t.r(n),
      t.d(n, "default", function() {
        return y;
      });
    var o = "undefined" != typeof document;
    if ("undefined" != typeof DEBUG && DEBUG && !o)
      throw new Error(
        "vue-style-loader cannot be used in a non-browser environment. Use { target: 'node' } in your Webpack config to indicate a server-rendering environment.",
      );
    var r = {},
      a = o && (document.head || document.getElementsByTagName("head")[0]),
      s = null,
      c = 0,
      u = !1,
      p = function() { },
      l = null,
      f = "data-vue-ssr-id",
      d =
        "undefined" != typeof navigator &&
        /msie [6-9]\b/.test(navigator.userAgent.toLowerCase());
    function y(e, n, t, o) {
      (u = t), (l = o || {});
      var a = i(e, n);
      return (
        g(a),
        function(n) {
          for (var t = [], o = 0; o < a.length; o++) {
            var s = a[o];
            (c = r[s.id]).refs--, t.push(c);
          }
          n ? g((a = i(e, n))) : (a = []);
          for (o = 0; o < t.length; o++) {
            var c;
            if (0 === (c = t[o]).refs) {
              for (var u = 0; u < c.parts.length; u++) c.parts[u]();
              delete r[c.id];
            }
          }
        }
      );
    }
    function g(e) {
      for (var n = 0; n < e.length; n++) {
        var t = e[n],
          i = r[t.id];
        if (i) {
          i.refs++;
          for (var o = 0; o < i.parts.length; o++) i.parts[o](t.parts[o]);
          for (; o < t.parts.length; o++) i.parts.push(v(t.parts[o]));
          i.parts.length > t.parts.length && (i.parts.length = t.parts.length);
        } else {
          var a = [];
          for (o = 0; o < t.parts.length; o++) a.push(v(t.parts[o]));
          r[t.id] = { id: t.id, refs: 1, parts: a };
        }
      }
    }
    function h() {
      var e = document.createElement("style");
      return (e.type = "text/css"), a.appendChild(e), e;
    }
    function v(e) {
      var n,
        t,
        i = document.querySelector("style[" + f + '~="' + e.id + '"]');
      if (i) {
        if (u) return p;
        i.parentNode.removeChild(i);
      }
      if (d) {
        var o = c++;
        (i = s || (s = h())),
          (n = S.bind(null, i, o, !1)),
          (t = S.bind(null, i, o, !0));
      } else
        (i = h()),
          (n = w.bind(null, i)),
          (t = function() {
            i.parentNode.removeChild(i);
          });
      return (
        n(e),
        function(i) {
          if (i) {
            if (
              i.css === e.css &&
              i.media === e.media &&
              i.sourceMap === e.sourceMap
            )
              return;
            n((e = i));
          } else t();
        }
      );
    }
    var b,
      m =
        ((b = []),
          function(e, n) {
            return (b[e] = n), b.filter(Boolean).join("\n");
          });
    function S(e, n, t, i) {
      var o = t ? "" : i.css;
      if (e.styleSheet) e.styleSheet.cssText = m(n, o);
      else {
        var r = document.createTextNode(o),
          a = e.childNodes;
        a[n] && e.removeChild(a[n]),
          a.length ? e.insertBefore(r, a[n]) : e.appendChild(r);
      }
    }
    function w(e, n) {
      var t = n.css,
        i = n.media,
        o = n.sourceMap;
      if (
        (i && e.setAttribute("media", i),
          l.ssrId && e.setAttribute(f, n.id),
          o &&
          ((t += "\n/*# sourceURL=" + o.sources[0] + " */"),
            (t +=
              "\n/*# sourceMappingURL=data:application/json;base64," +
              btoa(unescape(encodeURIComponent(JSON.stringify(o)))) +
              " */")),
          e.styleSheet)
      )
        e.styleSheet.cssText = t;
      else {
        for (; e.firstChild;) e.removeChild(e.firstChild);
        e.appendChild(document.createTextNode(t));
      }
    }
  },
  YLip: function(e, n, t) {
    var i = t("okTO");
    i.__esModule && (i = i.default),
      "string" == typeof i && (i = [[e.i, i, ""]]),
      i.locals && (e.exports = i.locals);
    (0, t("SZ7m").default)("332ffe77", i, !0, {});
  },
  ayfy: function(e, n, t) {
    var i = t("L+7P");
    i.__esModule && (i = i.default),
      "string" == typeof i && (i = [[e.i, i, ""]]),
      i.locals && (e.exports = i.locals);
    (0, t("SZ7m").default)("43e7fb28", i, !0, {});
  },
  bWgD: function(e, n, t) { },
  okTO: function(e, n, t) { },
  vWtx: function(e) {
    e.exports = JSON.parse(
      '{"coinsnap-btcpay-generate-credentials":{"button":"Berechtigungsnachweise generieren","missing_server":"Sie müssen die BTCPay Server url angeben","info":"Sie werden zur angegebenen BTCPayServer-URL weitergeleitet, um die erforderlichen Anmeldedaten zu generieren"},"coinsnap-btcpay-test-connection":{"button":"Verbindung testen","success":"Das Plugin ist mit dem Server verbunden","error":"Ein Fehler ist aufgetreten. Prüfen Sie, ob die Anmeldedaten gültig sind und versuchen Sie es erneut.","missing_credentials":"Sie müssen zunächst einen Berechtigungsnachweis erstellen."},"coinsnap-coinsnap-test-connection":{"save":"Anmeldeinformationen speichern","button":"Verbindung testen","success":"Das Plugin ist mit dem Server verbunden","error":"Ein Fehler ist aufgetreten. Prüfen Sie, ob die Anmeldedaten gültig sind und versuchen Sie es erneut.","missing_credentials":"Sie müssen zuerst den API-Schlüssel speichern und die ID speichern."}}',
    );
  },
});
