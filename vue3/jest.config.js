// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * jest.config module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

module.exports = {
  coverageReporters: [
    'lcov',
    'text',
  ],
  preset: '@vue/cli-plugin-unit-jest',
  verbose: true,
  moduleFileExtensions: ['js', 'ts', 'json', 'vue'],
  testMatch: [
    '**/tests/unit/**/*.spec.[jt]s?(x)',
  ],
  transform: {
    '^.+\\.vue$': '@vue/vue3-jest',  // Vue 3 component transformer
    '^.+\\.js$': 'babel-jest',        // JavaScript transformer
    '^.+\\.ts$': 'ts-jest',           // TypeScript transformer
  },
  watchPathIgnorePatterns: [
    '<rootDir>/node_modules/',
  ],
  reporters: [
    'default',
    ['jest-html-reporter', {
      pageTitle: 'Test Report',
      outputPath: 'test-report.html',
      includeFailureMsg: true,
      includeSuiteFailure: true,
    }],
  ],
  coverageProvider: "v8",
  testEnvironment: 'jsdom',           // Use jsdom for DOM simulation
  moduleNameMapper: {
    '^@vue/test-utils$': '<rootDir>/node_modules/@vue/test-utils',
  },
};
