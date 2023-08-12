(()=>{"use strict";var e,t={749:(e,t,o)=>{const r=window.wp.element,n=window.wp.domReady;var l=o.n(n);const a=window.wp.apiFetch;var c=o.n(a);const i=window.wp.components,s=window.wp.i18n;function u(e){return`var(--wp--preset--color--${e})`}function p(e){if("string"==typeof e&&e.match(/^#/))return e.substring(0,7);const[,,t]=e.split("|");return u(t)}function d(e,t){let o=arguments.length>2&&void 0!==arguments[2]?arguments[2]:null;return o?`${e}${arguments.length>3&&void 0!==arguments[3]?arguments[3]:""} { ${t}: ${o}; }\n`:""}const m="activitypub-follow-modal-active";function f(e){let{selector:t,style:o,backgroundColor:n}=e;const l=function(e,t,o){const r=`${e} .components-button`,n=("string"==typeof(l=o)?u(l):l?.color?.background||null)||t?.color?.background;var l;const a=p(t?.elements?.link?.color?.text),c=p(t?.elements?.link?.[":hover"]?.color?.text);return d(r,"color",n)+d(r,"background-color",a)+d(r,"background-color",c,":hover")}(t,o,n);return(0,r.createElement)("style",null,l)}const{namespace:v}=window._activityPubOptions;function y(e){let{profile:t,popupStyles:o}=e;const{handle:n,avatar:l,name:a}=t;return(0,r.createElement)("div",{className:"activitypub-profile"},(0,r.createElement)("img",{className:"activitypub-profile__avatar",src:l}),(0,r.createElement)("div",{className:"activitypub-profile__content"},(0,r.createElement)("div",{className:"activitypub-profile__name"},a),(0,r.createElement)("div",{className:"activitypub-profile__handle"},n)),(0,r.createElement)(w,{profile:t,popupStyles:o}))}const b={avatar:"",handle:"@well@hello.dolly",name:(0,s.__)("Hello Dolly Fan Account","fediverse"),url:"#"};function h(e){if(!e)return b;e.handle=function(e){try{var t;const{host:o,pathname:r}=new URL(e.url);return`${null!==(t=e.preferredUsername)&&void 0!==t?t:r.replace(/^\//,"")}@${o}`}catch(e){return"@error@error"}}(e);const t={...b,...e};return t.avatar=t?.icon?.url,t}function w(e){let{profile:t,popupStyles:o}=e;const[n,l]=(0,r.useState)(!1);function a(e){const t=e?"add":"remove";document.body.classList[t](m),l(e)}return(0,r.createElement)(r.Fragment,null,(0,r.createElement)(i.Button,{className:"activitypub-profile__follow",onClick:()=>a(!0)},(0,s.__)("Follow","fediverse")),(0,r.createElement)(i.__experimentalConfirmDialog,{className:"activitypub-profile__confirm",isOpen:n,onConfirm:()=>a(!1),onCancel:()=>a(!1)},(0,r.createElement)("p",null,"Howdy let's put some dialogs here"),(0,r.createElement)("style",null,o)))}function g(e){const[t,o]=(0,r.useState)(h()),{selectedUser:n}=e,l="site"===n?0:n,a=e?.id?`#${e.id}`:".activitypub-follow-me-block-wrapper",i=function(e){const t=`.${m} .components-modal__content .components-button`,o=`${t}.is-primary`,r=`${t}.is-tertiary`,n=p(e?.elements?.link?.color?.text),l=p(e?.elements?.link?.[":hover"]?.color?.text);return d(o,"background-color",n)+d(o,"background-color",l,":hover")+d(r,"color",n)}(e.style);function s(e){o(h(e))}return(0,r.useEffect)((()=>{(function(e){const t={headers:{Accept:"application/activity+json"},path:`/${v}/users/${e}`};return c()(t)})(l).then(s)}),[l]),(0,r.createElement)(r.Fragment,null,(0,r.createElement)(f,{selector:a,style:e.style,backgroundColor:e.backgroundColor}),(0,r.createElement)(y,{profile:t,popupStyles:i}))}l()((()=>{[].forEach.call(document.querySelectorAll(".activitypub-follow-me-block-wrapper"),(e=>{const t=JSON.parse(e.dataset.attrs);(0,r.render)((0,r.createElement)(g,t),e)}))}))}},o={};function r(e){var n=o[e];if(void 0!==n)return n.exports;var l=o[e]={exports:{}};return t[e](l,l.exports,r),l.exports}r.m=t,e=[],r.O=(t,o,n,l)=>{if(!o){var a=1/0;for(u=0;u<e.length;u++){for(var[o,n,l]=e[u],c=!0,i=0;i<o.length;i++)(!1&l||a>=l)&&Object.keys(r.O).every((e=>r.O[e](o[i])))?o.splice(i--,1):(c=!1,l<a&&(a=l));if(c){e.splice(u--,1);var s=n();void 0!==s&&(t=s)}}return t}l=l||0;for(var u=e.length;u>0&&e[u-1][2]>l;u--)e[u]=e[u-1];e[u]=[o,n,l]},r.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return r.d(t,{a:t}),t},r.d=(e,t)=>{for(var o in t)r.o(t,o)&&!r.o(e,o)&&Object.defineProperty(e,o,{enumerable:!0,get:t[o]})},r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={529:0,740:0};r.O.j=t=>0===e[t];var t=(t,o)=>{var n,l,[a,c,i]=o,s=0;if(a.some((t=>0!==e[t]))){for(n in c)r.o(c,n)&&(r.m[n]=c[n]);if(i)var u=i(r)}for(t&&t(o);s<a.length;s++)l=a[s],r.o(e,l)&&e[l]&&e[l][0](),e[l]=0;return r.O(u)},o=globalThis.webpackChunkwordpress_activitypub=globalThis.webpackChunkwordpress_activitypub||[];o.forEach(t.bind(null,0)),o.push=t.bind(null,o.push.bind(o))})();var n=r.O(void 0,[740],(()=>r(749)));n=r.O(n)})();