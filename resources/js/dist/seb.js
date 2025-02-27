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
!function(e,t){"use strict";function i(e){return e&&"object"==typeof e&&"default"in e?e:{default:e}}var o=i(e),n=i(t);let a,r;function f(){r.cookie=`examKey=${SafeExamBrowser.security.browserExamKey}`,r.cookie=`configKey=${SafeExamBrowser.security.configKey}`,r.cookie=`sebClientVersion=${SafeExamBrowser.version}`,fetch(a,{credentials:"same-origin"}).then((e=>(403===e.status&&(r.open("text/html"),r.write(e.text()),r.close()),e.text())))}function s(e){e.innerHTML=`${(new Date).toLocaleTimeString([],{hour:"2-digit",minute:"2-digit"})}`}n.default.seb=n.default.seb||{},n.default.seb.saveAndCheckSEBKey=e=>function(e,t){a=e,r=t,r.cookie=`uri=${r.defaultView.location.href}`,"undefined"!=typeof SafeExamBrowser&&void 0!==SafeExamBrowser.security&&SafeExamBrowser.security.updateKeys((()=>f()))}(e,o.default),n.default.seb.sebClockInit=e=>function(e,t){s(e),t((()=>{s(e)}),500)}(e,o.default.defaultView.setInterval)}(document,il);
