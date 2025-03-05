/**
This file is part of the SEB-Plugin for ILIAS.

SEB-Plugin for ILIAS is free software: you can redistribute
it and/or modify it under the terms of the GNU General Public License
as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

SEB-Plugin for ILIAS is distributed in the hope that
it will be useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with SEB-Plugin for ILIAS.  If not,
see http://www.gnu.org/licenses/.

The SEB-Plugin for ILIAS is a refactoring of a previous Plugin by Stefan
Schneider that can be found on Github
https://github.com/hrz-unimr/Ilias.SEBPlugin
*/
!function(e,t){"use strict";function i(e){return e&&"object"==typeof e&&"default"in e?e:{default:e}}var n=i(e),o=i(t);function a(e,t){t.defaultView.addEventListener("beforeunload",(()=>function(e){const t=e;"undefined"!=typeof SafeExamBrowser&&void 0!==SafeExamBrowser.security&&""!==SafeExamBrowser.security.browserExamKey&&(t.cookie="examKey=;expires=-1",t.cookie="configKey=;expires=-1",t.cookie="sebClientVersion=;expires=-1")}(t))),function(e,t){fetch(e,{credentials:"same-origin"}).then((e=>{403!==e.status||e.text().then((e=>{t.open("text/html"),t.write(e),t.close()}))}))}(e,t)}function r(e){e.innerHTML=`${(new Date).toLocaleTimeString([],{hour:"2-digit",minute:"2-digit"})}`}o.default.seb=o.default.seb||{},o.default.seb.saveAndCheckSEBKey=e=>a(e,n.default),o.default.seb.sebClockInit=e=>function(e,t){r(e),t((()=>{r(e)}),500)}(e,n.default.defaultView.setInterval)}(document,il);
