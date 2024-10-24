(()=>{"use strict";var e={20:(e,t,i)=>{var n=i(609),o=Symbol.for("react.element"),r=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),a=n.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,l={key:!0,ref:!0,__self:!0,__source:!0};t.jsx=function(e,t,i){var n,c={},p=null,s=null;for(n in void 0!==i&&(p=""+i),void 0!==t.key&&(p=""+t.key),void 0!==t.ref&&(s=t.ref),t)r.call(t,n)&&!l.hasOwnProperty(n)&&(c[n]=t[n]);if(e&&e.defaultProps)for(n in t=e.defaultProps)void 0===c[n]&&(c[n]=t[n]);return{$$typeof:o,type:e,key:p,ref:s,props:c,_owner:a.current}}},848:(e,t,i)=>{e.exports=i(20)},609:e=>{e.exports=window.React}},t={};function i(n){var o=t[n];if(void 0!==o)return o.exports;var r=t[n]={exports:{}};return e[n](r,r.exports,i),r.exports}(()=>{var e=i(609);const t=window.wp.editor,n=window.wp.plugins,o=window.wp.components,r=window.wp.element,a=(0,r.forwardRef)((function({icon:e,size:t=24,...i},n){return(0,r.cloneElement)(e,{width:t,height:t,...i,ref:n})})),l=window.wp.primitives;var c=i(848);const p=(0,c.jsx)(l.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,c.jsx)(l.Path,{d:"M12 3.3c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8s-4-8.8-8.8-8.8zm6.5 5.5h-2.6C15.4 7.3 14.8 6 14 5c2 .6 3.6 2 4.5 3.8zm.7 3.2c0 .6-.1 1.2-.2 1.8h-2.9c.1-.6.1-1.2.1-1.8s-.1-1.2-.1-1.8H19c.2.6.2 1.2.2 1.8zM12 18.7c-1-.7-1.8-1.9-2.3-3.5h4.6c-.5 1.6-1.3 2.9-2.3 3.5zm-2.6-4.9c-.1-.6-.1-1.1-.1-1.8 0-.6.1-1.2.1-1.8h5.2c.1.6.1 1.1.1 1.8s-.1 1.2-.1 1.8H9.4zM4.8 12c0-.6.1-1.2.2-1.8h2.9c-.1.6-.1 1.2-.1 1.8 0 .6.1 1.2.1 1.8H5c-.2-.6-.2-1.2-.2-1.8zM12 5.3c1 .7 1.8 1.9 2.3 3.5H9.7c.5-1.6 1.3-2.9 2.3-3.5zM10 5c-.8 1-1.4 2.3-1.8 3.8H5.5C6.4 7 8 5.6 10 5zM5.5 15.3h2.6c.4 1.5 1 2.8 1.8 3.7-1.8-.6-3.5-2-4.4-3.7zM14 19c.8-1 1.4-2.2 1.8-3.7h2.6C17.6 17 16 18.4 14 19z"})}),s=(0,c.jsx)(l.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,c.jsx)(l.Path,{d:"M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z",fillRule:"evenodd"})}),u=(0,c.jsx)(l.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,c.jsx)(l.Path,{fillRule:"evenodd",clipRule:"evenodd",d:"M12 18.5A6.5 6.5 0 0 1 6.93 7.931l9.139 9.138A6.473 6.473 0 0 1 12 18.5Zm5.123-2.498a6.5 6.5 0 0 0-9.124-9.124l9.124 9.124ZM4 12a8 8 0 1 1 16 0 8 8 0 0 1-16 0Z"})}),v=window.wp.data,w=window.wp.coreData,d=window.wp.i18n;(0,n.registerPlugin)("activitypub-editor-plugin",{render:()=>{const i=(0,v.useSelect)((e=>e("core/editor").getCurrentPostType()),[]),[n,r]=(0,w.useEntityProp)("postType",i,"meta"),l={verticalAlign:"middle",gap:"4px",justifyContent:"start",display:"inline-flex",alignItems:"center"},c=(t,i)=>(0,e.createElement)(o.__experimentalText,{style:l},(0,e.createElement)(a,{icon:i}),t);return(0,e.createElement)(t.PluginDocumentSettingPanel,{name:"activitypub",title:(0,d.__)("⁂ Fediverse","activitypub")},(0,e.createElement)(o.TextControl,{label:(0,d.__)("Content Warning","activitypub"),value:n?.activitypub_content_warning,onChange:e=>{r({...n,activitypub_content_warning:e})},placeholder:(0,d.__)("Optional content warning","activitypub")}),(0,e.createElement)(o.RadioControl,{label:(0,d.__)("Visibility","activitypub"),help:(0,d.__)("This adjusts the visibility of a post in the fediverse, but note that it won't affect how the post appears on the blog.","activitypub"),selected:n.activitypub_content_visibility?n.activitypub_content_visibility:"public",options:[{label:c((0,d.__)("Public","activitypub"),p),value:"public"},{label:c((0,d.__)("Quiet public","activitypub"),s),value:"quiet_public"},{label:c((0,d.__)("Do not federate","activitypub"),u),value:"local"}],onChange:e=>{r({...n,activitypub_content_visibility:e})},className:"activitypub-visibility"}))}})})()})();
