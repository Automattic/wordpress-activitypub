(()=>{"use strict";var e,t={729:(e,t,r)=>{var o=r(609);const n=window.wp.element,a=window.wp.domReady;var l=r.n(a);const i=window.wp.apiFetch;var c=r.n(i);const s=window.wp.components,u=window.wp.i18n;function p(e){return`var(--wp--preset--color--${e})`}function m(e){if("string"!=typeof e)return null;if(e.match(/^#/))return e.substring(0,7);const[,,t]=e.split("|");return p(t)}function v(e,t,r=null,o=""){return r?`${e}${o} { ${t}: ${r}; }\n`:""}function d(e,t,r,o){return v(e,"background-color",t)+v(e,"color",r)+v(e,"background-color",o,":hover")+v(e,"background-color",o,":focus")}function f({selector:e,style:t,backgroundColor:r}){const n=function(e,t,r){const o=`${e} .components-button`,n=("string"==typeof(a=r)?p(a):a?.color?.background||null)||t?.color?.background;var a;return d(o,m(t?.elements?.link?.color?.text),n,m(t?.elements?.link?.[":hover"]?.color?.text))}(e,t,r);return(0,o.createElement)("style",null,n)}const y=window.wp.primitives;var _=r(848);const b=(0,_.jsx)(y.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,_.jsx)(y.Path,{fillRule:"evenodd",clipRule:"evenodd",d:"M5 4.5h11a.5.5 0 0 1 .5.5v11a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V5a.5.5 0 0 1 .5-.5ZM3 5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5Zm17 3v10.75c0 .69-.56 1.25-1.25 1.25H6v1.5h12.75a2.75 2.75 0 0 0 2.75-2.75V8H20Z"})}),w=(0,_.jsx)(y.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,_.jsx)(y.Path,{d:"M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"})}),h=(0,n.forwardRef)((function({icon:e,size:t=24,...r},o){return(0,n.cloneElement)(e,{width:t,height:t,...r,ref:o})})),g=(0,_.jsx)(y.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,_.jsx)(y.Path,{d:"M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z",fillRule:"evenodd"})}),E=window.wp.compose,k="fediverse-remote-user";function x(e){try{return new URL(e),!0}catch(e){return!1}}function O({actionText:e,copyDescription:t,handle:r,resourceUrl:a,myProfile:l=!1}){const i=(0,u.__)("Loading...","activitypub"),p=(0,u.__)("Opening...","activitypub"),m=(0,u.__)("Error","activitypub"),v=(0,u.__)("Invalid","activitypub"),d=l||(0,u.__)("My Profile","activitypub"),[f,y]=(0,n.useState)(e),[_,O]=(0,n.useState)(b),S=(0,E.useCopyToClipboard)(r,(()=>{O(w),setTimeout((()=>O(b)),1e3)})),[C,N]=(0,n.useState)(""),[R,I]=(0,n.useState)(!0),{setRemoteUser:$}=function(){const[e,t]=(0,n.useState)(function(){const e=localStorage.getItem(k);return e?JSON.parse(e):{}}()),r=(0,n.useCallback)((e=>{!function(e){localStorage.setItem(k,JSON.stringify(e))}(e),t(e)}),[]),o=(0,n.useCallback)((()=>{localStorage.removeItem(k),t({})}),[]);return{template:e?.template||!1,profileURL:e?.profileURL||!1,setRemoteUser:r,deleteRemoteUser:o}}(),j=(0,n.useCallback)((()=>{let t;if(!x(C)&&!function(e){const t=e.replace(/^@/,"").split("@");return 2===t.length&&x(`https://${t[1]}`)}(C))return y(v),t=setTimeout((()=>y(e)),2e3),()=>clearTimeout(t);const r=a+C;y(i),c()({path:r}).then((({url:t,template:r})=>{R&&$({profileURL:C,template:r}),y(p),setTimeout((()=>{window.open(t,"_blank"),y(e)}),200)})).catch((()=>{y(m),setTimeout((()=>y(e)),2e3)}))}),[C]);return(0,o.createElement)("div",{className:"activitypub__dialog"},(0,o.createElement)("div",{className:"activitypub-dialog__section"},(0,o.createElement)("h4",null,d),(0,o.createElement)("div",{className:"activitypub-dialog__description"},t),(0,o.createElement)("div",{className:"activitypub-dialog__button-group"},(0,o.createElement)("input",{type:"text",value:r,readOnly:!0}),(0,o.createElement)(s.Button,{ref:S},(0,o.createElement)(h,{icon:_}),(0,u.__)("Copy","activitypub")))),(0,o.createElement)("div",{className:"activitypub-dialog__section"},(0,o.createElement)("h4",null,(0,u.__)("Your Profile","activitypub")),(0,o.createElement)("div",{className:"activitypub-dialog__description"},(0,n.createInterpolateElement)((0,u.__)("Or, if you know your own profile, we can start things that way! (eg <code>yourusername@example.com</code>)","activitypub"),{code:(0,o.createElement)("code",null)})),(0,o.createElement)("div",{className:"activitypub-dialog__button-group"},(0,o.createElement)("input",{type:"text",value:C,onKeyDown:e=>{"Enter"===e?.code&&j()},onChange:e=>N(e.target.value)}),(0,o.createElement)(s.Button,{onClick:j},(0,o.createElement)(h,{icon:g}),f)),(0,o.createElement)("div",{className:"activitypub-dialog__remember"},(0,o.createElement)(s.CheckboxControl,{checked:R,label:(0,u.__)("Remember me for easier comments","activitypub"),onChange:()=>{I(!R)}}))))}const{namespace:S}=window._activityPubOptions,C={avatar:"",webfinger:"@well@hello.dolly",name:(0,u.__)("Hello Dolly Fan Account","activitypub"),url:"#"};function N(e){if(!e)return C;const t={...C,...e};return t.avatar=t?.icon?.url,t}function R({profile:e,popupStyles:t,userId:r}){const{avatar:n,name:a,webfinger:l}=e;return(0,o.createElement)("div",{className:"activitypub-profile"},(0,o.createElement)("img",{className:"activitypub-profile__avatar",src:n,alt:a}),(0,o.createElement)("div",{className:"activitypub-profile__content"},(0,o.createElement)("div",{className:"activitypub-profile__name"},a),(0,o.createElement)("div",{className:"activitypub-profile__handle",title:l},l)),(0,o.createElement)(I,{profile:e,popupStyles:t,userId:r}))}function I({profile:e,popupStyles:t,userId:r}){const[a,l]=(0,n.useState)(!1),i=(0,u.sprintf)((0,u.__)("Follow %s","activitypub"),e?.name);return(0,o.createElement)(o.Fragment,null,(0,o.createElement)(s.Button,{className:"activitypub-profile__follow",onClick:()=>l(!0)},(0,u.__)("Follow","activitypub")),a&&(0,o.createElement)(s.Modal,{className:"activitypub-profile__confirm activitypub__modal",onRequestClose:()=>l(!1),title:i},(0,o.createElement)($,{profile:e,userId:r}),(0,o.createElement)("style",null,t)))}function $({profile:e,userId:t}){const{webfinger:r}=e,n=(0,u.__)("Follow","activitypub"),a=`/${S}/actors/${t}/remote-follow?resource=`,l=(0,u.__)("Copy and paste my profile into the search field of your favorite fediverse app or server.","activitypub");return(0,o.createElement)(O,{actionText:n,copyDescription:l,handle:r,resourceUrl:a})}function j({selectedUser:e,style:t,backgroundColor:r,id:a,useId:l=!1,profileData:i=!1}){const[s,u]=(0,n.useState)(N()),p="site"===e?0:e,v=function(e){return d(".apfmd__button-group .components-button",m(e?.elements?.link?.color?.text)||"#111","#fff",m(e?.elements?.link?.[":hover"]?.color?.text)||"#333")}(t),y=l?{id:a}:{};function _(e){u(N(e))}return(0,n.useEffect)((()=>{if(i)return _(i);(function(e){const t={headers:{Accept:"application/activity+json"},path:`/${S}/actors/${e}`};return c()(t)})(p).then(_)}),[p,i]),(0,o.createElement)("div",{...y},(0,o.createElement)(f,{selector:`#${a}`,style:t,backgroundColor:r}),(0,o.createElement)(R,{profile:s,userId:p,popupStyles:v}))}let P=1;l()((()=>{[].forEach.call(document.querySelectorAll(".activitypub-follow-me-block-wrapper"),(e=>{const t=JSON.parse(e.dataset.attrs);(0,n.createRoot)(e).render((0,o.createElement)(j,{...t,id:"activitypub-follow-me-block-"+P++,useId:!0}))}))}))},20:(e,t,r)=>{var o=r(609),n=Symbol.for("react.element"),a=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),l=o.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,i={key:!0,ref:!0,__self:!0,__source:!0};t.jsx=function(e,t,r){var o,c={},s=null,u=null;for(o in void 0!==r&&(s=""+r),void 0!==t.key&&(s=""+t.key),void 0!==t.ref&&(u=t.ref),t)a.call(t,o)&&!i.hasOwnProperty(o)&&(c[o]=t[o]);if(e&&e.defaultProps)for(o in t=e.defaultProps)void 0===c[o]&&(c[o]=t[o]);return{$$typeof:n,type:e,key:s,ref:u,props:c,_owner:l.current}}},848:(e,t,r)=>{e.exports=r(20)},609:e=>{e.exports=window.React}},r={};function o(e){var n=r[e];if(void 0!==n)return n.exports;var a=r[e]={exports:{}};return t[e](a,a.exports,o),a.exports}o.m=t,e=[],o.O=(t,r,n,a)=>{if(!r){var l=1/0;for(u=0;u<e.length;u++){for(var[r,n,a]=e[u],i=!0,c=0;c<r.length;c++)(!1&a||l>=a)&&Object.keys(o.O).every((e=>o.O[e](r[c])))?r.splice(c--,1):(i=!1,a<l&&(l=a));if(i){e.splice(u--,1);var s=n();void 0!==s&&(t=s)}}return t}a=a||0;for(var u=e.length;u>0&&e[u-1][2]>a;u--)e[u]=e[u-1];e[u]=[r,n,a]},o.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return o.d(t,{a:t}),t},o.d=(e,t)=>{for(var r in t)o.o(t,r)&&!o.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},o.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={41:0,301:0};o.O.j=t=>0===e[t];var t=(t,r)=>{var n,a,[l,i,c]=r,s=0;if(l.some((t=>0!==e[t]))){for(n in i)o.o(i,n)&&(o.m[n]=i[n]);if(c)var u=c(o)}for(t&&t(r);s<l.length;s++)a=l[s],o.o(e,a)&&e[a]&&e[a][0](),e[a]=0;return o.O(u)},r=globalThis.webpackChunkwordpress_activitypub=globalThis.webpackChunkwordpress_activitypub||[];r.forEach(t.bind(null,0)),r.push=t.bind(null,r.push.bind(r))})();var n=o.O(void 0,[301],(()=>o(729)));n=o.O(n)})();