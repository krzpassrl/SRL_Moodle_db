YUI.add("moodle-mod_setask-history",function(e,t){var n={LINK:"mod-setask-history-link",OPEN:"mod-setask-history-link-open",CLOSED:"mod-setask-history-link-closed",PANEL:"mod-setask-history-panel"},r=0,i=function(){var t=this.get("for"),r=e.one("#"+t);this.hasClass(n.OPEN)?(this.removeClass(n.OPEN),this.addClass(n.CLOSED),this.setStyle("overflow","hidden"),r.hide()):(this.removeClass(n.CLOSED),this.addClass(n.OPEN),r.show())},s=function(){var t=null,s=null,o=null,u=this;this.get("children").each(function(){t?(r++,o=e.Node.create("<a/>"),s=this,u.insertBefore(o,t),t.remove(!1),o.appendChild(t),s.get("id")||s.set("id",n.PANEL+r),o.set("for",s.get("id")),s.set("aria-live","polite"),o.addClass(n.LINK),o.addClass(n.CLOSED),s.addClass(n.PANEL),s.hide(),t=null):t=this}),this.delegate("click",i,"."+n.LINK)};e.Node.addMethod("history",s),e.NodeList.importMethod(e.Node.prototype,"history")},"@VERSION@",{requires:["node","transition"]});
