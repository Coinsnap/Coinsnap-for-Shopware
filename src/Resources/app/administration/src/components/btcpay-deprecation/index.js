// Import necessary components
const { Component, Mixin } = Shopware;

// Register the component
Component.register('swag-payment-deprecation', {
  template: './swag-payment-deprecation.html', // Path to the separate template file
  mixins: [Mixin.getByName('notification')],
  data() {
    return {
      replacementPluginName: 'CoinchargeBTCPayShopware', // Replace with actual name
      replacementLink: 'https://github.com/coincharge-io/CoinchargeBTCPayShopware', // Replace with actual link
    };
  },
});
