/**
 * This file is part of the SEB-Plugin for ILIAS.
 *
 * SEB-Plugin for ILIAS is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * SEB-Plugin for ILIAS is distributed in the hope that
 * it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SEB-Plugin for ILIAS.  If not,
 * see <http://www.gnu.org/licenses/>.
 *
 * The SEB-Plugin for ILIAS is a refactoring of a previous Plugin by Stefan
 * Schneider that can be found on Github
 * <https://github.com/hrz-unimr/Ilias.SEBPlugin>
 */

/**
 * @param {DOMDocument} documentParam
 * @returns {void}
 */
function tearDownSEBKey(documentParam) {
  const document = documentParam;
  if (typeof SafeExamBrowser !== 'undefined'
    && typeof SafeExamBrowser.security !== 'undefined'
    && SafeExamBrowser.security.browserExamKey !== '') {
    document.cookie = 'examKey=;expires=-1';
    document.cookie = 'configKey=;expires=-1';
    document.cookie = 'sebClientVersion=;expires=-1';
  }
}

/**
 * @param {String} url
 * @param {DOMDocument} document
 * @returns {void}
 */
function sendRequest(url, document) {
  fetch(url, { credentials: 'same-origin' })
    .then((response) => {
      if (response.status === 403) {
        response.text().then((data) => {
          document.open('text/html');
          document.write(data);
          document.close();
        });
        return;
      }

      return;
    });
}

/**
 * @param {String} url
 * @param {DOMDocument} document
 * @returns {void}
 */
export default function setupCheckSEBKey(url, document) {
  document.defaultView.addEventListener('beforeunload', () => tearDownSEBKey(document));
  sendRequest(url, document);
}
