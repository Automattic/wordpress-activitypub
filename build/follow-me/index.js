(()=>{"use strict";var e,t={399:(e,t,r)=>{const o=window.wp.blocks,n=window.wp.primitives;var i=r(848);const a=(0,i.jsx)(n.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,i.jsx)(n.Path,{d:"M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z",fillRule:"evenodd"})});var l=r(609);const c=window.wp.blockEditor,s=window.wp.i18n,u=window.wp.data,p=window.wp.coreData,d=window.wp.components,m=window.wp.element,v=window._activityPubOptions?.enabled,f=window.wp.apiFetch;var y=r.n(f);function _(e){return`var(--wp--preset--color--${e})`}function b(e){if("string"!=typeof e)return null;if(e.match(/^#/))return e.substring(0,7);const[,,t]=e.split("|");return _(t)}function w(e,t,r=null,o=""){return r?`${e}${o} { ${t}: ${r}; }\n`:""}function h(e,t,r,o){return w(e,"background-color",t)+w(e,"color",r)+w(e,"background-color",o,":hover")+w(e,"background-color",o,":focus")}function g({selector:e,style:t,backgroundColor:r}){const o=function(e,t,r){const o=`${e} .components-button`,n=("string"==typeof(i=r)?_(i):i?.color?.background||null)||t?.color?.background;var i;return h(o,b(t?.elements?.link?.color?.text),n,b(t?.elements?.link?.[":hover"]?.color?.text))}(e,t,r);return(0,l.createElement)("style",null,o)}const E=(0,i.jsx)(n.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,i.jsx)(n.Path,{fillRule:"evenodd",clipRule:"evenodd",d:"M5 4.5h11a.5.5 0 0 1 .5.5v11a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V5a.5.5 0 0 1 .5-.5ZM3 5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5Zm17 3v10.75c0 .69-.56 1.25-1.25 1.25H6v1.5h12.75a2.75 2.75 0 0 0 2.75-2.75V8H20Z"})}),k=(0,i.jsx)(n.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,i.jsx)(n.Path,{d:"M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"})}),x=(0,m.forwardRef)((function({icon:e,size:t=24,...r},o){return(0,m.cloneElement)(e,{width:t,height:t,...r,ref:o})})),S=window.wp.compose,O="fediverse-remote-user";function C(e){try{return new URL(e),!0}catch(e){return!1}}function I({actionText:e,copyDescription:t,handle:r,resourceUrl:o,myProfile:n=!1,rememberProfile:i=!1}){const c=(0,s.__)("Loading...","activitypub"),u=(0,s.__)("Opening...","activitypub"),p=(0,s.__)("Error","activitypub"),v=(0,s.__)("Invalid","activitypub"),f=n||(0,s.__)("My Profile","activitypub"),[_,b]=(0,m.useState)(e),[w,h]=(0,m.useState)(E),g=(0,S.useCopyToClipboard)(r,(()=>{h(k),setTimeout((()=>h(E)),1e3)})),[I,N]=(0,m.useState)(""),[R,U]=(0,m.useState)(!0),{setRemoteUser:$}=function(){const[e,t]=(0,m.useState)(function(){const e=localStorage.getItem(O);return e?JSON.parse(e):{}}()),r=(0,m.useCallback)((e=>{!function(e){localStorage.setItem(O,JSON.stringify(e))}(e),t(e)}),[]),o=(0,m.useCallback)((()=>{localStorage.removeItem(O),t({})}),[]);return{template:e?.template||!1,profileURL:e?.profileURL||!1,setRemoteUser:r,deleteRemoteUser:o}}(),P=(0,m.useCallback)((()=>{let t;if(!C(I)&&!function(e){const t=e.replace(/^@/,"").split("@");return 2===t.length&&C(`https://${t[1]}`)}(I))return b(v),t=setTimeout((()=>b(e)),2e3),()=>clearTimeout(t);const r=o+I;b(c),y()({path:r}).then((({url:t,template:r})=>{R&&$({profileURL:I,template:r}),b(u),setTimeout((()=>{window.open(t,"_blank"),b(e)}),200)})).catch((()=>{b(p),setTimeout((()=>b(e)),2e3)}))}),[I]);return(0,l.createElement)("div",{className:"activitypub__dialog"},(0,l.createElement)("div",{className:"activitypub-dialog__section"},(0,l.createElement)("h4",null,f),(0,l.createElement)("div",{className:"activitypub-dialog__description"},t),(0,l.createElement)("div",{className:"activitypub-dialog__button-group"},(0,l.createElement)("input",{type:"text",value:r,readOnly:!0}),(0,l.createElement)(d.Button,{ref:g},(0,l.createElement)(x,{icon:w}),(0,s.__)("Copy","activitypub")))),(0,l.createElement)("div",{className:"activitypub-dialog__section"},(0,l.createElement)("h4",null,(0,s.__)("Your Profile","activitypub")),(0,l.createElement)("div",{className:"activitypub-dialog__description"},(0,m.createInterpolateElement)((0,s.__)("Or, if you know your own profile, we can start things that way! (eg <code>@yourusername@example.com</code>)","activitypub"),{code:(0,l.createElement)("code",null)})),(0,l.createElement)("div",{className:"activitypub-dialog__button-group"},(0,l.createElement)("input",{type:"text",value:I,onKeyDown:e=>{"Enter"===e?.code&&P()},onChange:e=>N(e.target.value)}),(0,l.createElement)(d.Button,{onClick:P},(0,l.createElement)(x,{icon:a}),_)),i&&(0,l.createElement)("div",{className:"activitypub-dialog__remember"},(0,l.createElement)(d.CheckboxControl,{checked:R,label:(0,s.__)("Remember me for easier comments","activitypub"),onChange:()=>{U(!R)}}))))}const{namespace:N}=window._activityPubOptions,R={avatar:"",webfinger:"@well@hello.dolly",name:(0,s.__)("Hello Dolly Fan Account","activitypub"),url:"#"};function U(e){if(!e)return R;const t={...R,...e};return t.avatar=t?.icon?.url,t}function $({profile:e,popupStyles:t,userId:r}){const{webfinger:o,avatar:n,name:i}=e,a=o.startsWith("@")?o:`@${o}`;return(0,l.createElement)("div",{className:"activitypub-profile"},(0,l.createElement)("img",{className:"activitypub-profile__avatar",src:n,alt:i}),(0,l.createElement)("div",{className:"activitypub-profile__content"},(0,l.createElement)("div",{className:"activitypub-profile__name"},i),(0,l.createElement)("div",{className:"activitypub-profile__handle",title:a},a)),(0,l.createElement)(P,{profile:e,popupStyles:t,userId:r}))}function P({profile:e,popupStyles:t,userId:r}){const[o,n]=(0,m.useState)(!1),i=(0,s.sprintf)((0,s.__)("Follow %s","activitypub"),e?.name);return(0,l.createElement)(l.Fragment,null,(0,l.createElement)(d.Button,{className:"activitypub-profile__follow",onClick:()=>n(!0)},(0,s.__)("Follow","activitypub")),o&&(0,l.createElement)(d.Modal,{className:"activitypub-profile__confirm activitypub__modal",onRequestClose:()=>n(!1),title:i},(0,l.createElement)(T,{profile:e,userId:r}),(0,l.createElement)("style",null,t)))}function T({profile:e,userId:t}){const{webfinger:r}=e,o=(0,s.__)("Follow","activitypub"),n=`/${N}/actors/${t}/remote-follow?resource=`,i=(0,s.__)("Copy and paste my profile into the search field of your favorite fediverse app or server.","activitypub"),a=r.startsWith("@")?r:`@${r}`;return(0,l.createElement)(I,{actionText:o,copyDescription:i,handle:a,resourceUrl:n})}function j({selectedUser:e,style:t,backgroundColor:r,id:o,useId:n=!1,profileData:i=!1}){const[a,c]=(0,m.useState)(U()),s="site"===e?0:e,u=function(e){return h(".apfmd__button-group .components-button",b(e?.elements?.link?.color?.text)||"#111","#fff",b(e?.elements?.link?.[":hover"]?.color?.text)||"#333")}(t),p=n?{id:o}:{};function d(e){c(U(e))}return(0,m.useEffect)((()=>{if(i)return d(i);(function(e){const t={headers:{Accept:"application/activity+json"},path:`/${N}/actors/${e}`};return y()(t)})(s).then(d)}),[s,i]),(0,l.createElement)("div",{...p},(0,l.createElement)(g,{selector:`#${o}`,style:t,backgroundColor:r}),(0,l.createElement)($,{profile:a,userId:s,popupStyles:u}))}const B=window._activityPubOptions?.enabled;function M({name:e}){const t=B?.site?"":(0,s.__)("It will be empty in other non-author contexts.","activitypub"),r=(0,s.sprintf)(/* translators: %1$s: block name, %2$s: extra information for non-author context */ /* translators: %1$s: block name, %2$s: extra information for non-author context */
(0,s.__)("This <strong>%1$s</strong> block will adapt to the page it is on, displaying the user profile associated with a post author (in a loop) or a user archive. %2$s","activitypub"),e,t).trim();return(0,l.createElement)(d.Card,null,(0,l.createElement)(d.CardBody,null,(0,m.createInterpolateElement)(r,{strong:(0,l.createElement)("strong",null)})))}(0,o.registerBlockType)("activitypub/follow-me",{edit:function({attributes:e,setAttributes:t,context:{postType:r,postId:o}}){const n=(0,c.useBlockProps)({className:"activitypub-follow-me-block-wrapper"}),i=function({withInherit:e=!1}){const t=v?.users?(0,u.useSelect)((e=>e("core").getUsers({who:"authors"}))):[];return(0,m.useMemo)((()=>{if(!t)return[];const r=[];return v?.site&&r.push({label:(0,s.__)("Site","activitypub"),value:"site"}),e&&v?.users&&r.push({label:(0,s.__)("Dynamic User","activitypub"),value:"inherit"}),t.reduce(((e,t)=>(e.push({label:t.name,value:`${t.id}`}),e)),r)}),[t])}({withInherit:!0}),{selectedUser:a}=e,f="inherit"===a,y=(0,u.useSelect)((e=>{const{getEditedEntityRecord:t}=e(p.store),n=t("postType",r,o)?.author;return null!=n?n:null}),[r,o]);return(0,m.useEffect)((()=>{i.length&&(i.find((({value:e})=>e===a))||t({selectedUser:i[0].value}))}),[a,i]),(0,l.createElement)("div",{...n},i.length>1&&(0,l.createElement)(c.InspectorControls,{key:"setting"},(0,l.createElement)(d.PanelBody,{title:(0,s.__)("Followers Options","activitypub")},(0,l.createElement)(d.SelectControl,{label:(0,s.__)("Select User","activitypub"),value:e.selectedUser,options:i,onChange:e=>t({selectedUser:e})}))),f?y?(0,l.createElement)(j,{...e,id:n.id,selectedUser:y}):(0,l.createElement)(M,{name:(0,s.__)("Follow Me","activitypub")}):(0,l.createElement)(j,{...e,id:n.id}))},save:()=>null,icon:a})},20:(e,t,r)=>{var o=r(609),n=Symbol.for("react.element"),i=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),a=o.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,l={key:!0,ref:!0,__self:!0,__source:!0};t.jsx=function(e,t,r){var o,c={},s=null,u=null;for(o in void 0!==r&&(s=""+r),void 0!==t.key&&(s=""+t.key),void 0!==t.ref&&(u=t.ref),t)i.call(t,o)&&!l.hasOwnProperty(o)&&(c[o]=t[o]);if(e&&e.defaultProps)for(o in t=e.defaultProps)void 0===c[o]&&(c[o]=t[o]);return{$$typeof:n,type:e,key:s,ref:u,props:c,_owner:a.current}}},848:(e,t,r)=>{e.exports=r(20)},609:e=>{e.exports=window.React}},r={};function o(e){var n=r[e];if(void 0!==n)return n.exports;var i=r[e]={exports:{}};return t[e](i,i.exports,o),i.exports}o.m=t,e=[],o.O=(t,r,n,i)=>{if(!r){var a=1/0;for(u=0;u<e.length;u++){r=e[u][0],n=e[u][1],i=e[u][2];for(var l=!0,c=0;c<r.length;c++)(!1&i||a>=i)&&Object.keys(o.O).every((e=>o.O[e](r[c])))?r.splice(c--,1):(l=!1,i<a&&(a=i));if(l){e.splice(u--,1);var s=n();void 0!==s&&(t=s)}}return t}i=i||0;for(var u=e.length;u>0&&e[u-1][2]>i;u--)e[u]=e[u-1];e[u]=[r,n,i]},o.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return o.d(t,{a:t}),t},o.d=(e,t)=>{for(var r in t)o.o(t,r)&&!o.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},o.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={338:0,301:0};o.O.j=t=>0===e[t];var t=(t,r)=>{var n,i,a=r[0],l=r[1],c=r[2],s=0;if(a.some((t=>0!==e[t]))){for(n in l)o.o(l,n)&&(o.m[n]=l[n]);if(c)var u=c(o)}for(t&&t(r);s<a.length;s++)i=a[s],o.o(e,i)&&e[i]&&e[i][0](),e[i]=0;return o.O(u)},r=self.webpackChunkwordpress_activitypub=self.webpackChunkwordpress_activitypub||[];r.forEach(t.bind(null,0)),r.push=t.bind(null,r.push.bind(r))})();var n=o.O(void 0,[301],(()=>o(399)));n=o.O(n)})();