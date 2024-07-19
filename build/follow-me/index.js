(()=>{"use strict";var e,t={17:(e,t,r)=>{const o=window.wp.blocks,n=window.wp.primitives;var l=r(848);const a=(0,l.jsx)(n.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,l.jsx)(n.Path,{d:"M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z",fillRule:"evenodd"})});var i=r(609);const c=window.wp.blockEditor,s=window.wp.i18n,u=window.wp.components,p=window.wp.data,m=window.wp.element,v=window._activityPubOptions?.enabled,d=window.wp.apiFetch;var f=r.n(d);function y(e){return`var(--wp--preset--color--${e})`}function b(e){if("string"!=typeof e)return null;if(e.match(/^#/))return e.substring(0,7);const[,,t]=e.split("|");return y(t)}function _(e,t,r=null,o=""){return r?`${e}${o} { ${t}: ${r}; }\n`:""}function w(e,t,r,o){return _(e,"background-color",t)+_(e,"color",r)+_(e,"background-color",o,":hover")+_(e,"background-color",o,":focus")}function h({selector:e,style:t,backgroundColor:r}){const o=function(e,t,r){const o=`${e} .components-button`,n=("string"==typeof(l=r)?y(l):l?.color?.background||null)||t?.color?.background;var l;return w(o,b(t?.elements?.link?.color?.text),n,b(t?.elements?.link?.[":hover"]?.color?.text))}(e,t,r);return(0,i.createElement)("style",null,o)}const g=(0,l.jsx)(n.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,l.jsx)(n.Path,{fillRule:"evenodd",clipRule:"evenodd",d:"M5 4.5h11a.5.5 0 0 1 .5.5v11a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V5a.5.5 0 0 1 .5-.5ZM3 5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5Zm17 3v10.75c0 .69-.56 1.25-1.25 1.25H6v1.5h12.75a2.75 2.75 0 0 0 2.75-2.75V8H20Z"})}),E=(0,l.jsx)(n.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,l.jsx)(n.Path,{d:"M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"})}),k=(0,m.forwardRef)((function({icon:e,size:t=24,...r},o){return(0,m.cloneElement)(e,{width:t,height:t,...r,ref:o})})),x=window.wp.compose,S="fediverse-remote-user";function O(e){try{return new URL(e),!0}catch(e){return!1}}function C({actionText:e,copyDescription:t,handle:r,resourceUrl:o,myProfile:n=!1,rememberProfile:l=!1}){const c=(0,s.__)("Loading...","activitypub"),p=(0,s.__)("Opening...","activitypub"),v=(0,s.__)("Error","activitypub"),d=(0,s.__)("Invalid","activitypub"),y=n||(0,s.__)("My Profile","activitypub"),[b,_]=(0,m.useState)(e),[w,h]=(0,m.useState)(g),C=(0,x.useCopyToClipboard)(r,(()=>{h(E),setTimeout((()=>h(g)),1e3)})),[N,R]=(0,m.useState)(""),[U,P]=(0,m.useState)(!0),{setRemoteUser:I}=function(){const[e,t]=(0,m.useState)(function(){const e=localStorage.getItem(S);return e?JSON.parse(e):{}}()),r=(0,m.useCallback)((e=>{!function(e){localStorage.setItem(S,JSON.stringify(e))}(e),t(e)}),[]),o=(0,m.useCallback)((()=>{localStorage.removeItem(S),t({})}),[]);return{template:e?.template||!1,profileURL:e?.profileURL||!1,setRemoteUser:r,deleteRemoteUser:o}}(),$=(0,m.useCallback)((()=>{let t;if(!O(N)&&!function(e){const t=e.replace(/^@/,"").split("@");return 2===t.length&&O(`https://${t[1]}`)}(N))return _(d),t=setTimeout((()=>_(e)),2e3),()=>clearTimeout(t);const r=o+N;_(c),f()({path:r}).then((({url:t,template:r})=>{U&&I({profileURL:N,template:r}),_(p),setTimeout((()=>{window.open(t,"_blank"),_(e)}),200)})).catch((()=>{_(v),setTimeout((()=>_(e)),2e3)}))}),[N]);return(0,i.createElement)("div",{className:"activitypub__dialog"},(0,i.createElement)("div",{className:"activitypub-dialog__section"},(0,i.createElement)("h4",null,y),(0,i.createElement)("div",{className:"activitypub-dialog__description"},t),(0,i.createElement)("div",{className:"activitypub-dialog__button-group"},(0,i.createElement)("input",{type:"text",value:r,readOnly:!0}),(0,i.createElement)(u.Button,{ref:C},(0,i.createElement)(k,{icon:w}),(0,s.__)("Copy","activitypub")))),(0,i.createElement)("div",{className:"activitypub-dialog__section"},(0,i.createElement)("h4",null,(0,s.__)("Your Profile","activitypub")),(0,i.createElement)("div",{className:"activitypub-dialog__description"},(0,m.createInterpolateElement)((0,s.__)("Or, if you know your own profile, we can start things that way! (eg <code>yourusername@example.com</code>)","activitypub"),{code:(0,i.createElement)("code",null)})),(0,i.createElement)("div",{className:"activitypub-dialog__button-group"},(0,i.createElement)("input",{type:"text",value:N,onKeyDown:e=>{"Enter"===e?.code&&$()},onChange:e=>R(e.target.value)}),(0,i.createElement)(u.Button,{onClick:$},(0,i.createElement)(k,{icon:a}),b)),l&&(0,i.createElement)("div",{className:"activitypub-dialog__remember"},(0,i.createElement)(u.CheckboxControl,{checked:U,label:(0,s.__)("Remember me for easier comments","activitypub"),onChange:()=>{P(!U)}}))))}const{namespace:N}=window._activityPubOptions,R={avatar:"",webfinger:"@well@hello.dolly",name:(0,s.__)("Hello Dolly Fan Account","activitypub"),url:"#"};function U(e){if(!e)return R;const t={...R,...e};return t.avatar=t?.icon?.url,t}function P({profile:e,popupStyles:t,userId:r}){const{avatar:o,name:n,webfinger:l}=e;return(0,i.createElement)("div",{className:"activitypub-profile"},(0,i.createElement)("img",{className:"activitypub-profile__avatar",src:o,alt:n}),(0,i.createElement)("div",{className:"activitypub-profile__content"},(0,i.createElement)("div",{className:"activitypub-profile__name"},n),(0,i.createElement)("div",{className:"activitypub-profile__handle",title:l},l)),(0,i.createElement)(I,{profile:e,popupStyles:t,userId:r}))}function I({profile:e,popupStyles:t,userId:r}){const[o,n]=(0,m.useState)(!1),l=(0,s.sprintf)((0,s.__)("Follow %s","activitypub"),e?.name);return(0,i.createElement)(i.Fragment,null,(0,i.createElement)(u.Button,{className:"activitypub-profile__follow",onClick:()=>n(!0)},(0,s.__)("Follow","activitypub")),o&&(0,i.createElement)(u.Modal,{className:"activitypub-profile__confirm activitypub__modal",onRequestClose:()=>n(!1),title:l},(0,i.createElement)($,{profile:e,userId:r}),(0,i.createElement)("style",null,t)))}function $({profile:e,userId:t}){const{webfinger:r}=e,o=(0,s.__)("Follow","activitypub"),n=`/${N}/actors/${t}/remote-follow?resource=`,l=(0,s.__)("Copy and paste my profile into the search field of your favorite fediverse app or server.","activitypub");return(0,i.createElement)(C,{actionText:o,copyDescription:l,handle:r,resourceUrl:n})}function T({selectedUser:e,style:t,backgroundColor:r,id:o,useId:n=!1,profileData:l=!1}){const[a,c]=(0,m.useState)(U()),s="site"===e?0:e,u=function(e){return w(".apfmd__button-group .components-button",b(e?.elements?.link?.color?.text)||"#111","#fff",b(e?.elements?.link?.[":hover"]?.color?.text)||"#333")}(t),p=n?{id:o}:{};function v(e){c(U(e))}return(0,m.useEffect)((()=>{if(l)return v(l);(function(e){const t={headers:{Accept:"application/activity+json"},path:`/${N}/actors/${e}`};return f()(t)})(s).then(v)}),[s,l]),(0,i.createElement)("div",{...p},(0,i.createElement)(h,{selector:`#${o}`,style:t,backgroundColor:r}),(0,i.createElement)(P,{profile:a,userId:s,popupStyles:u}))}(0,o.registerBlockType)("activitypub/follow-me",{edit:function({attributes:e,setAttributes:t}){const r=(0,c.useBlockProps)({className:"activitypub-follow-me-block-wrapper"}),o=function(){const e=v?.users?(0,p.useSelect)((e=>e("core").getUsers({who:"authors"}))):[];return(0,m.useMemo)((()=>{if(!e)return[];const t=v?.site?[{label:(0,s.__)("Whole Site","activitypub"),value:"site"}]:[];return e.reduce(((e,t)=>(e.push({label:t.name,value:`${t.id}`}),e)),t)}),[e])}(),{selectedUser:n}=e;return(0,m.useEffect)((()=>{o.length&&(o.find((({value:e})=>e===n))||t({selectedUser:o[0].value}))}),[n,o]),(0,i.createElement)("div",{...r},o.length>1&&(0,i.createElement)(c.InspectorControls,{key:"setting"},(0,i.createElement)(u.PanelBody,{title:(0,s.__)("Followers Options","activitypub")},(0,i.createElement)(u.SelectControl,{label:(0,s.__)("Select User","activitypub"),value:e.selectedUser,options:o,onChange:e=>t({selectedUser:e})}))),(0,i.createElement)(T,{...e,id:r.id}))},save:()=>null,icon:a})},20:(e,t,r)=>{var o=r(609),n=Symbol.for("react.element"),l=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),a=o.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,i={key:!0,ref:!0,__self:!0,__source:!0};t.jsx=function(e,t,r){var o,c={},s=null,u=null;for(o in void 0!==r&&(s=""+r),void 0!==t.key&&(s=""+t.key),void 0!==t.ref&&(u=t.ref),t)l.call(t,o)&&!i.hasOwnProperty(o)&&(c[o]=t[o]);if(e&&e.defaultProps)for(o in t=e.defaultProps)void 0===c[o]&&(c[o]=t[o]);return{$$typeof:n,type:e,key:s,ref:u,props:c,_owner:a.current}}},848:(e,t,r)=>{e.exports=r(20)},609:e=>{e.exports=window.React}},r={};function o(e){var n=r[e];if(void 0!==n)return n.exports;var l=r[e]={exports:{}};return t[e](l,l.exports,o),l.exports}o.m=t,e=[],o.O=(t,r,n,l)=>{if(!r){var a=1/0;for(u=0;u<e.length;u++){for(var[r,n,l]=e[u],i=!0,c=0;c<r.length;c++)(!1&l||a>=l)&&Object.keys(o.O).every((e=>o.O[e](r[c])))?r.splice(c--,1):(i=!1,l<a&&(a=l));if(i){e.splice(u--,1);var s=n();void 0!==s&&(t=s)}}return t}l=l||0;for(var u=e.length;u>0&&e[u-1][2]>l;u--)e[u]=e[u-1];e[u]=[r,n,l]},o.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return o.d(t,{a:t}),t},o.d=(e,t)=>{for(var r in t)o.o(t,r)&&!o.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},o.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={338:0,301:0};o.O.j=t=>0===e[t];var t=(t,r)=>{var n,l,[a,i,c]=r,s=0;if(a.some((t=>0!==e[t]))){for(n in i)o.o(i,n)&&(o.m[n]=i[n]);if(c)var u=c(o)}for(t&&t(r);s<a.length;s++)l=a[s],o.o(e,l)&&e[l]&&e[l][0](),e[l]=0;return o.O(u)},r=globalThis.webpackChunkwordpress_activitypub=globalThis.webpackChunkwordpress_activitypub||[];r.forEach(t.bind(null,0)),r.push=t.bind(null,r.push.bind(r))})();var n=o.O(void 0,[301],(()=>o(17)));n=o.O(n)})();