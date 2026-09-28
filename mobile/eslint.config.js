// https://docs.expo.dev/guides/using-eslint/
const { defineConfig } = require('eslint/config');
const expoConfig = require("eslint-config-expo/flat");

module.exports = defineConfig([
  expoConfig,
  {
    ignores: ["dist/*"],
  },
  {
    // Tests: jest.mock() exige require() dentro de la fábrica del mock.
    files: ["jest.setup.ts", "src/**/__tests__/**"],
    rules: { "@typescript-eslint/no-require-imports": "off" },
  },
]);
