(()=>{var e,t={142:(e,t,a)=>{"use strict";const r=window.wp.element,n=window.React,l=window.wp.apiFetch;var i=a.n(l);const o=window.wp.url,c=window.wp.i18n;var s=a(184),p=a.n(s);const u=window.wp.components;function d(e){let{active:t,children:a,disabled:n,page:l,pageClick:i}=e;const o=p()("pagination__list-item",{"is-active":t,"is-disabled":n});return(0,r.createElement)("li",{className:o},(0,r.createElement)(u.Button,{className:"pagination__list-button",borderless:!0,onClick:e=>{e.preventDefault(),i(l)},disabled:n},a))}const v={outlined:"outlined",minimal:"minimal"};function f(e){let{compact:t,nextLabel:a,page:n,pageClick:l,perPage:i,prevLabel:o,total:c,variant:s=v.outlined}=e;const u=((e,t)=>{let a=[1,e-2,e-1,e,e+1,e+2,t];a.sort(((e,t)=>e-t)),a=a.filter(((e,a,r)=>e>=1&&e<=t&&r.lastIndexOf(e)===a));for(let e=a.length-2;e>=0;e--)a[e]===a[e+1]&&a.splice(e+1,1);return a})(n,Math.ceil(c/i));return(0,r.createElement)("nav",{className:p()("followers-pagination",`is-${s}`,{"is-compact":t})},(0,r.createElement)("ul",{className:"pagination__list"},o&&(0,r.createElement)(d,{key:"prev",page:n-1,pageClick:l,disabled:1===n,"aria-label":o},o),u.map((e=>(0,r.createElement)(d,{key:e,page:e,pageClick:l,active:e===n},e))),a&&(0,r.createElement)(d,{key:"next",page:n+1,pageClick:l,disabled:n===Math.ceil(c/i),"aria-label":a},a)))}const{namespace:m}=window._activityPubOptions;function g(e){let{selectedUser:t,per_page:a,order:l,title:s,page:p,setPage:u}=e;const d="site"===t?0:t,[v,g]=(0,n.useState)([]),[w,h]=(0,n.useState)(0),[y,E]=(0,n.useState)(0),[k,_]=function(){const[e,t]=(0,n.useState)(1);return[e,t]}(),O=p||k,x=u||_;return(0,n.useEffect)((()=>{const e=function(e,t,a,r){const n=`/${m}/users/${e}/followers`,l={per_page:t,order:a,page:r,context:"view"};return(0,o.addQueryArgs)(n,l)}(d,a,l,O);i()({path:e}).then((e=>{h(e.total_pages),E(e.total),g(e.followers)})).catch((e=>console.error(e)))}),[d,a,l,O]),(0,r.createElement)("div",{className:"activitypub-follower-block"},(0,r.createElement)("h3",null,s),(0,r.createElement)("ul",null,v&&v.map((e=>(0,r.createElement)("li",{key:e.url},(0,r.createElement)(b,e))))),w>1&&(0,r.createElement)(f,{page:O,perPage:a,total:y,pageClick:x,nextLabel:(0,c.__)("More","activitypub"),prevLabel:(0,c.__)("Back","activitypub")}))}function b(e){let{name:t,avatar:a,url:n,handle:l}=e;return(0,r.createElement)(u.ExternalLink,{href:n,title:l,onClick:e=>e.preventDefault()},(0,r.createElement)("img",{width:"40",height:"40",src:a,class:"avatar activitypub-avatar"}),(0,r.createElement)("span",{class:"activitypub-actor"},(0,r.createElement)("strong",null,t),(0,r.createElement)("span",{class:"sep"},"/"),l))}const w=window.wp.domReady;a.n(w)()((()=>{[].forEach.call(document.querySelectorAll(".activitypub-follower-block"),(e=>{const t=JSON.parse(e.dataset.attrs);(0,r.render)((0,r.createElement)(g,t),e)}))}))},184:(e,t)=>{var a;!function(){"use strict";var r={}.hasOwnProperty;function n(){for(var e=[],t=0;t<arguments.length;t++){var a=arguments[t];if(a){var l=typeof a;if("string"===l||"number"===l)e.push(a);else if(Array.isArray(a)){if(a.length){var i=n.apply(null,a);i&&e.push(i)}}else if("object"===l){if(a.toString!==Object.prototype.toString&&!a.toString.toString().includes("[native code]")){e.push(a.toString());continue}for(var o in a)r.call(a,o)&&a[o]&&e.push(o)}}}return e.join(" ")}e.exports?(n.default=n,e.exports=n):void 0===(a=function(){return n}.apply(t,[]))||(e.exports=a)}()}},a={};function r(e){var n=a[e];if(void 0!==n)return n.exports;var l=a[e]={exports:{}};return t[e](l,l.exports,r),l.exports}r.m=t,e=[],r.O=(t,a,n,l)=>{if(!a){var i=1/0;for(p=0;p<e.length;p++){for(var[a,n,l]=e[p],o=!0,c=0;c<a.length;c++)(!1&l||i>=l)&&Object.keys(r.O).every((e=>r.O[e](a[c])))?a.splice(c--,1):(o=!1,l<i&&(i=l));if(o){e.splice(p--,1);var s=n();void 0!==s&&(t=s)}}return t}l=l||0;for(var p=e.length;p>0&&e[p-1][2]>l;p--)e[p]=e[p-1];e[p]=[a,n,l]},r.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return r.d(t,{a:t}),t},r.d=(e,t)=>{for(var a in t)r.o(t,a)&&!r.o(e,a)&&Object.defineProperty(e,a,{enumerable:!0,get:t[a]})},r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={638:0,962:0};r.O.j=t=>0===e[t];var t=(t,a)=>{var n,l,[i,o,c]=a,s=0;if(i.some((t=>0!==e[t]))){for(n in o)r.o(o,n)&&(r.m[n]=o[n]);if(c)var p=c(r)}for(t&&t(a);s<i.length;s++)l=i[s],r.o(e,l)&&e[l]&&e[l][0](),e[l]=0;return r.O(p)},a=globalThis.webpackChunkwordpress_activitypub=globalThis.webpackChunkwordpress_activitypub||[];a.forEach(t.bind(null,0)),a.push=t.bind(null,a.push.bind(a))})();var n=r.O(void 0,[962],(()=>r(142)));n=r.O(n)})();