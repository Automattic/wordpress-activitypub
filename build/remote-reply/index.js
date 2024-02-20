(()=>{"use strict";var e,t={664:(e,t,n)=>{const a=window.wp.element,o=window.wp.domReady;var r=n.n(o);const i=window.wp.apiFetch;var c=n.n(i);const l=window.wp.components,u=window.wp.i18n,p=window.wp.primitives,s=(0,a.createElement)(p.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,a.createElement)(p.Path,{d:"M20.2 8v11c0 .7-.6 1.2-1.2 1.2H6v1.5h13c1.5 0 2.7-1.2 2.7-2.8V8zM18 16.4V4.6c0-.9-.7-1.6-1.6-1.6H4.6C3.7 3 3 3.7 3 4.6v11.8c0 .9.7 1.6 1.6 1.6h11.8c.9 0 1.6-.7 1.6-1.6zm-13.5 0V4.6c0-.1.1-.1.1-.1h11.8c.1 0 .1.1.1.1v11.8c0 .1-.1.1-.1.1H4.6l-.1-.1z"})),m=(0,a.createElement)(p.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,a.createElement)(p.Path,{d:"M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"})),d=function({icon:e,size:t=24,...n}){return(0,a.cloneElement)(e,{width:t,height:t,...n})},v=window.wp.compose,{namespace:y}=window._activityPubOptions;function _(e){try{return new URL(e),!0}catch(e){return!1}}function w({selectedComment:e,commentId:t}){const n=(0,u.__)("Reply","activitypub"),o=(0,u.__)("Loading...","activitypub"),r=(0,u.__)("Opening...","activitypub"),i=(0,u.__)("Error","activitypub"),p=(0,u.__)("Invalid","activitypub"),[w,b]=(0,a.useState)(n),[h,f]=(0,a.useState)(s),E=(0,v.useCopyToClipboard)(e,(()=>{f(m),setTimeout((()=>f(s)),1e3)})),[g,C]=(0,a.useState)(""),O=(0,a.useCallback)((()=>{let e;if(!_(g)&&!function(e){const t=e.replace(/^@/,"").split("@");return 2===t.length&&_(`https://${t[1]}`)}(g))return b(p),e=setTimeout((()=>b(n)),2e3),()=>clearTimeout(e);const a=`/${y}/comments/${t}/remote-reply?resource=${g}`;b(o),c()({path:a}).then((({url:e})=>{b(r),setTimeout((()=>{window.open(e,"_blank"),b(n)}),200)})).catch((()=>{b(i),setTimeout((()=>b(n)),2e3)}))}),[g]);return(0,a.createElement)("div",{className:"activitypub__dialog"},(0,a.createElement)("div",{className:"activitypub-dialog__section"},(0,a.createElement)("h4",null,(0,u.__)("The Comment-URL","activitypub")),(0,a.createElement)("div",{className:"activitypub-dialog__description"},(0,u.__)("Copy and paste the Comment-URL into the search field of your favorite fediverse app or server to reply to this Comment.","activitypub")),(0,a.createElement)("div",{className:"activitypub-dialog__button-group"},(0,a.createElement)("input",{type:"text",value:e,readOnly:!0}),(0,a.createElement)(l.Button,{ref:E},(0,a.createElement)(d,{icon:h}),(0,u.__)("Copy","activitypub")))),(0,a.createElement)("div",{className:"activitypub-dialog__section"},(0,a.createElement)("h4",null,(0,u.__)("Your Profile","activitypub")),(0,a.createElement)("div",{className:"activitypub-dialog__description"},(0,a.createInterpolateElement)((0,u.__)("Or, if you know your own profile, we can start things that way! (eg <code>https://example.com/yourusername</code> or <code>yourusername@example.com</code>)","activitypub"),{code:(0,a.createElement)("code",null)})),(0,a.createElement)("div",{className:"activitypub-dialog__button-group"},(0,a.createElement)("input",{type:"text",value:g,onKeyDown:e=>{"Enter"===e?.code&&O()},onChange:e=>C(e.target.value)}),(0,a.createElement)(l.Button,{onClick:O},w))))}function b({selectedComment:e,commentId:t}){const[n,o]=(0,a.useState)(!1),r=(0,u.__)("Remote Reply","activitypub");return(0,a.createElement)(a.Fragment,null,(0,a.createElement)("a",{href:"javascript:;",className:"comment-reply-link activitypub-remote-reply__button",onClick:()=>o(!0)},(0,u.__)("Reply on the Fediverse","activitypub")),n&&(0,a.createElement)(l.Modal,{className:"activitypub-remote-reply__modal activitypub__modal",onRequestClose:()=>o(!1),title:r},(0,a.createElement)(w,{selectedComment:e,commentId:t})))}let h=1;r()((()=>{[].forEach.call(document.querySelectorAll(".activitypub-remote-reply"),(e=>{const t=JSON.parse(e.dataset.attrs);(0,a.render)((0,a.createElement)(b,{...t,id:"activitypub-remote-reply-link-"+h++,useId:!0}),e)}))}))}},n={};function a(e){var o=n[e];if(void 0!==o)return o.exports;var r=n[e]={exports:{}};return t[e](r,r.exports,a),r.exports}a.m=t,e=[],a.O=(t,n,o,r)=>{if(!n){var i=1/0;for(p=0;p<e.length;p++){n=e[p][0],o=e[p][1],r=e[p][2];for(var c=!0,l=0;l<n.length;l++)(!1&r||i>=r)&&Object.keys(a.O).every((e=>a.O[e](n[l])))?n.splice(l--,1):(c=!1,r<i&&(i=r));if(c){e.splice(p--,1);var u=o();void 0!==u&&(t=u)}}return t}r=r||0;for(var p=e.length;p>0&&e[p-1][2]>r;p--)e[p]=e[p-1];e[p]=[n,o,r]},a.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return a.d(t,{a:t}),t},a.d=(e,t)=>{for(var n in t)a.o(t,n)&&!a.o(e,n)&&Object.defineProperty(e,n,{enumerable:!0,get:t[n]})},a.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={805:0,881:0};a.O.j=t=>0===e[t];var t=(t,n)=>{var o,r,i=n[0],c=n[1],l=n[2],u=0;if(i.some((t=>0!==e[t]))){for(o in c)a.o(c,o)&&(a.m[o]=c[o]);if(l)var p=l(a)}for(t&&t(n);u<i.length;u++)r=i[u],a.o(e,r)&&e[r]&&e[r][0](),e[r]=0;return a.O(p)},n=self.webpackChunkwordpress_activitypub=self.webpackChunkwordpress_activitypub||[];n.forEach(t.bind(null,0)),n.push=t.bind(null,n.push.bind(n))})();var o=a.O(void 0,[881],(()=>a(664)));o=a.O(o)})();