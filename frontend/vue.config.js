const ImportMetaEnvPlugin = require("@import-meta-env/unplugin");

module.exports = {
  lintOnSave: false,
  devServer: {
    host: "0.0.0.0", // allow external access
    port: 8001,
    allowedHosts: "all",
    hot: true,
    client: {
      webSocketURL: {
        hostname: "139.59.181.1", // your server IP
        port: 8001,
        protocol: "ws"
      }
    }
  },
  configureWebpack: {
    performance: {
      hints: false,
    },
    plugins: [
      ImportMetaEnvPlugin.webpack({
        example: ".env.example",
        env: ".env",
      }),
    ],
  },
};
