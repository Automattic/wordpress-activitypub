(()=>{var e,t={189:(e,t,a)=>{"use strict";const r=window.wp.element;function n(){return n=Object.assign?Object.assign.bind():function(e){for(var t=1;t<arguments.length;t++){var a=arguments[t];for(var r in a)Object.prototype.hasOwnProperty.call(a,r)&&(e[r]=a[r])}return e},n.apply(this,arguments)}const l=window.React,o=window.wp.apiFetch;var i=a.n(o);const c=window.wp.url,s=window.wp.i18n;var p=a(184),u=a.n(p);function m(e){let{active:t,children:a,page:n,pageClick:l,className:o}=e;const i=u()("wp-block activitypub-pager",o,{current:t});return(0,r.createElement)("a",{className:i,onClick:e=>{e.preventDefault(),!t&&l(n)}},a)}const v={outlined:"outlined",minimal:"minimal"};function f(e){let{compact:t,nextLabel:a,page:n,pageClick:l,perPage:o,prevLabel:i,total:c,variant:s=v.outlined}=e;const p=((e,t)=>{let a=[1,e-2,e-1,e,e+1,e+2,t];a.sort(((e,t)=>e-t)),a=a.filter(((e,a,r)=>e>=1&&e<=t&&r.lastIndexOf(e)===a));for(let e=a.length-2;e>=0;e--)a[e]===a[e+1]&&a.splice(e+1,1);return a})(n,Math.ceil(c/o)),f=u()("alignwide wp-block-query-pagination is-content-justification-space-between is-layout-flex wp-block-query-pagination-is-layout-flex",`is-${s}`,{"is-compact":t});return(0,r.createElement)("nav",{className:f},i&&(0,r.createElement)(m,{key:"prev",page:n-1,pageClick:l,active:1===n,"aria-label":i,className:"wp-block-query-pagination-previous block-editor-block-list__block"},i),!t&&(0,r.createElement)("div",{className:"block-editor-block-list__block wp-block wp-block-query-pagination-numbers"},p.map((e=>(0,r.createElement)(m,{key:e,page:e,pageClick:l,active:e===n,className:"page-numbers"},e)))),a&&(0,r.createElement)(m,{key:"next",page:n+1,pageClick:l,active:n===Math.ceil(c/o),"aria-label":a,className:"wp-block-query-pagination-next block-editor-block-list__block"},a))}const b=window.wp.components,{namespace:d}=window._activityPubOptions;function w(e){let{selectedUser:t,per_page:a,order:o,title:p,page:u,setPage:m,className:v="",followLinks:b=!0,followerData:w=!1}=e;const y="site"===t?0:t,[k,h]=(0,l.useState)([]),[E,O]=(0,l.useState)(0),[x,_]=(0,l.useState)(0),[N,j]=function(){const[e,t]=(0,l.useState)(1);return[e,t]}(),S=u||N,C=m||j,L=(0,r.createInterpolateElement)(/* translators: arrow for previous followers link */
(0,s.__)("<span>←</span> Less","activitypub"),{span:(0,r.createElement)("span",{class:"wp-block-query-pagination-previous-arrow is-arrow-arrow","aria-hidden":"true"})}),q=(0,r.createInterpolateElement)(/* translators: arrow for next followers link */
(0,s.__)("More <span>→</span>","activitypub"),{span:(0,r.createElement)("span",{class:"wp-block-query-pagination-next-arrow is-arrow-arrow","aria-hidden":"true"})}),P=(e,t)=>{h(e),_(t),O(Math.ceil(t/a))};return(0,l.useEffect)((()=>{if(w&&1===S)return P(w.followers,w.total);const e=function(e,t,a,r){const n=`/${d}/users/${e}/followers`,l={per_page:t,order:a,page:r,context:"full"};return(0,c.addQueryArgs)(n,l)}(y,a,o,S);i()({path:e}).then((e=>P(e.orderedItems,e.totalItems))).catch((()=>{}))}),[y,a,o,S,w]),(0,r.createElement)("div",{className:"activitypub-follower-block "+v},(0,r.createElement)("h3",null,p),(0,r.createElement)("ul",null,k&&k.map((e=>(0,r.createElement)("li",{key:e.url},(0,r.createElement)(g,n({},e,{followLinks:b})))))),E>1&&(0,r.createElement)(f,{page:S,perPage:a,total:x,pageClick:C,nextLabel:q,prevLabel:L,compact:"is-style-compact"===v}))}function g(e){let{name:t,icon:a,url:l,preferredUsername:o,followLinks:i=!0}=e;const c=`@${o}`,s={};return i||(s.onClick=e=>e.preventDefault()),(0,r.createElement)(b.ExternalLink,n({className:"activitypub-link",href:l,title:c},s),(0,r.createElement)("img",{width:"40",height:"40",src:a.url,class:"avatar activitypub-avatar",alt:t}),(0,r.createElement)("span",{class:"activitypub-actor"},(0,r.createElement)("strong",{className:"activitypub-name"},t),(0,r.createElement)("span",{class:"sep"},"/"),(0,r.createElement)("span",{class:"activitypub-handle"},c)))}const y=window.wp.domReady;a.n(y)()((()=>{[].forEach.call(document.querySelectorAll(".activitypub-follower-block"),(e=>{const t=JSON.parse(e.dataset.attrs);(0,r.render)((0,r.createElement)(w,t),e)}))}))},184:(e,t)=>{var a;!function(){"use strict";var r={}.hasOwnProperty;function n(){for(var e=[],t=0;t<arguments.length;t++){var a=arguments[t];if(a){var l=typeof a;if("string"===l||"number"===l)e.push(a);else if(Array.isArray(a)){if(a.length){var o=n.apply(null,a);o&&e.push(o)}}else if("object"===l){if(a.toString!==Object.prototype.toString&&!a.toString.toString().includes("[native code]")){e.push(a.toString());continue}for(var i in a)r.call(a,i)&&a[i]&&e.push(i)}}}return e.join(" ")}e.exports?(n.default=n,e.exports=n):void 0===(a=function(){return n}.apply(t,[]))||(e.exports=a)}()}},a={};function r(e){var n=a[e];if(void 0!==n)return n.exports;var l=a[e]={exports:{}};return t[e](l,l.exports,r),l.exports}r.m=t,e=[],r.O=(t,a,n,l)=>{if(!a){var o=1/0;for(p=0;p<e.length;p++){for(var[a,n,l]=e[p],i=!0,c=0;c<a.length;c++)(!1&l||o>=l)&&Object.keys(r.O).every((e=>r.O[e](a[c])))?a.splice(c--,1):(i=!1,l<o&&(o=l));if(i){e.splice(p--,1);var s=n();void 0!==s&&(t=s)}}return t}l=l||0;for(var p=e.length;p>0&&e[p-1][2]>l;p--)e[p]=e[p-1];e[p]=[a,n,l]},r.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return r.d(t,{a:t}),t},r.d=(e,t)=>{for(var a in t)r.o(t,a)&&!r.o(e,a)&&Object.defineProperty(e,a,{enumerable:!0,get:t[a]})},r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={638:0,962:0};r.O.j=t=>0===e[t];var t=(t,a)=>{var n,l,[o,i,c]=a,s=0;if(o.some((t=>0!==e[t]))){for(n in i)r.o(i,n)&&(r.m[n]=i[n]);if(c)var p=c(r)}for(t&&t(a);s<o.length;s++)l=o[s],r.o(e,l)&&e[l]&&e[l][0](),e[l]=0;return r.O(p)},a=globalThis.webpackChunkwordpress_activitypub=globalThis.webpackChunkwordpress_activitypub||[];a.forEach(t.bind(null,0)),a.push=t.bind(null,a.push.bind(a))})();var n=r.O(void 0,[962],(()=>r(189)));n=r.O(n)})();