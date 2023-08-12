(()=>{"use strict";var e,t={991:(e,t,n)=>{const o=window.wp.blocks,r=window.wp.element,l=window.wp.primitives,a=(0,r.createElement)(l.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,r.createElement)(l.Path,{d:"M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z",fillRule:"evenodd"}));function c(){return c=Object.assign?Object.assign.bind():function(e){for(var t=1;t<arguments.length;t++){var n=arguments[t];for(var o in n)Object.prototype.hasOwnProperty.call(n,o)&&(e[o]=n[o])}return e},c.apply(this,arguments)}const i=window.wp.blockEditor,s=window.wp.i18n,u=window.wp.components,p=window.wp.data,v=window._activityPubOptions?.enabled,d=window.wp.apiFetch;var m=n.n(d);function f(e){return`var(--wp--preset--color--${e})`}function b(e){if("string"==typeof e&&e.match(/^#/))return e.substring(0,7);const[,,t]=e.split("|");return f(t)}function w(e,t){let n=arguments.length>2&&void 0!==arguments[2]?arguments[2]:null;return n?`${e}${arguments.length>3&&void 0!==arguments[3]?arguments[3]:""} { ${t}: ${n}; }\n`:""}const h="activitypub-follow-modal-active";function y(e){let{selector:t,style:n,backgroundColor:o}=e;const l=function(e,t,n){const o=`${e} .components-button`,r=("string"==typeof(l=n)?f(l):l?.color?.background||null)||t?.color?.background;var l;const a=b(t?.elements?.link?.color?.text),c=b(t?.elements?.link?.[":hover"]?.color?.text);return w(o,"color",r)+w(o,"background-color",a)+w(o,"background-color",c,":hover")}(t,n,o);return(0,r.createElement)("style",null,l)}const{namespace:g}=window._activityPubOptions;function _(e){let{profile:t,popupStyles:n}=e;const{handle:o,avatar:l,name:a}=t;return(0,r.createElement)("div",{className:"activitypub-profile"},(0,r.createElement)("img",{className:"activitypub-profile__avatar",src:l}),(0,r.createElement)("div",{className:"activitypub-profile__content"},(0,r.createElement)("div",{className:"activitypub-profile__name"},a),(0,r.createElement)("div",{className:"activitypub-profile__handle"},o)),(0,r.createElement)(O,{profile:t,popupStyles:n}))}const k={avatar:"",handle:"@well@hello.dolly",name:(0,s.__)("Hello Dolly Fan Account","fediverse"),url:"#"};function E(e){if(!e)return k;e.handle=function(e){try{var t;const{host:n,pathname:o}=new URL(e.url);return`${null!==(t=e.preferredUsername)&&void 0!==t?t:o.replace(/^\//,"")}@${n}`}catch(e){return"@error@error"}}(e);const t={...k,...e};return t.avatar=t?.icon?.url,t}function O(e){let{profile:t,popupStyles:n}=e;const[o,l]=(0,r.useState)(!1);function a(e){const t=e?"add":"remove";document.body.classList[t](h),l(e)}return(0,r.createElement)(r.Fragment,null,(0,r.createElement)(u.Button,{className:"activitypub-profile__follow",onClick:()=>a(!0)},(0,s.__)("Follow","fediverse")),(0,r.createElement)(u.__experimentalConfirmDialog,{className:"activitypub-profile__confirm",isOpen:o,onConfirm:()=>a(!1),onCancel:()=>a(!1)},(0,r.createElement)("p",null,"Howdy let's put some dialogs here"),(0,r.createElement)("style",null,n)))}function $(e){const[t,n]=(0,r.useState)(E()),{selectedUser:o}=e,l="site"===o?0:o,a=e?.id?`#${e.id}`:".activitypub-follow-me-block-wrapper",c=function(e){const t=`.${h} .components-modal__content .components-button`,n=`${t}.is-primary`,o=`${t}.is-tertiary`,r=b(e?.elements?.link?.color?.text),l=b(e?.elements?.link?.[":hover"]?.color?.text);return w(n,"background-color",r)+w(n,"background-color",l,":hover")+w(o,"color",r)}(e.style);function i(e){n(E(e))}return(0,r.useEffect)((()=>{(function(e){const t={headers:{Accept:"application/activity+json"},path:`/${g}/users/${e}`};return m()(t)})(l).then(i)}),[l]),(0,r.createElement)(r.Fragment,null,(0,r.createElement)(y,{selector:a,style:e.style,backgroundColor:e.backgroundColor}),(0,r.createElement)(_,{profile:t,popupStyles:c}))}(0,o.registerBlockType)("activitypub/follow-me",{edit:function(e){let{attributes:t,setAttributes:n}=e;const o=(0,i.useBlockProps)(),l=function(){const e=v?.users?(0,p.useSelect)((e=>e("core").getUsers({who:"authors"}))):[];return(0,r.useMemo)((()=>{if(!e)return[];const t=v?.site?[{label:(0,s.__)("Whole Site","activitypub"),value:"site"}]:[];return e.reduce(((e,t)=>(e.push({label:t.name,value:t.id}),e)),t)}),[e])}();return(0,r.createElement)("div",o,(0,r.createElement)(i.InspectorControls,{key:"setting"},(0,r.createElement)(u.PanelBody,{title:(0,s.__)("Followers Options","activitypub")},(0,r.createElement)(u.SelectControl,{label:(0,s.__)("Select User","activitypub"),value:t.selectedUser,options:l,onChange:e=>n({selectedUser:e})}))),(0,r.createElement)($,c({},t,{id:o.id})))},save:()=>null,icon:a})}},n={};function o(e){var r=n[e];if(void 0!==r)return r.exports;var l=n[e]={exports:{}};return t[e](l,l.exports,o),l.exports}o.m=t,e=[],o.O=(t,n,r,l)=>{if(!n){var a=1/0;for(u=0;u<e.length;u++){for(var[n,r,l]=e[u],c=!0,i=0;i<n.length;i++)(!1&l||a>=l)&&Object.keys(o.O).every((e=>o.O[e](n[i])))?n.splice(i--,1):(c=!1,l<a&&(a=l));if(c){e.splice(u--,1);var s=r();void 0!==s&&(t=s)}}return t}l=l||0;for(var u=e.length;u>0&&e[u-1][2]>l;u--)e[u]=e[u-1];e[u]=[n,r,l]},o.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return o.d(t,{a:t}),t},o.d=(e,t)=>{for(var n in t)o.o(t,n)&&!o.o(e,n)&&Object.defineProperty(e,n,{enumerable:!0,get:t[n]})},o.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={127:0,740:0};o.O.j=t=>0===e[t];var t=(t,n)=>{var r,l,[a,c,i]=n,s=0;if(a.some((t=>0!==e[t]))){for(r in c)o.o(c,r)&&(o.m[r]=c[r]);if(i)var u=i(o)}for(t&&t(n);s<a.length;s++)l=a[s],o.o(e,l)&&e[l]&&e[l][0](),e[l]=0;return o.O(u)},n=globalThis.webpackChunkwordpress_activitypub=globalThis.webpackChunkwordpress_activitypub||[];n.forEach(t.bind(null,0)),n.push=t.bind(null,n.push.bind(n))})();var r=o.O(void 0,[740],(()=>o(991)));r=o.O(r)})();