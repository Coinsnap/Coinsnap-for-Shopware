// Import necessary components
const { Component, Mixin } = Shopware;

// Register the component
Component.register('btcpay-deprecation', {
  template: './btcpay-deprecation.html.twig', // Path to the separate template file
  mixins: [Mixin.getByName('notification')],
  data() {
    return {
      replacementPluginName: 'CoinchargeBTCPayShopware', // Replace with actual name
      replacementLink: 'https://github.com/coincharge-io/CoinchargeBTCPayShopware', // Replace with actual link
    };
  },
});
