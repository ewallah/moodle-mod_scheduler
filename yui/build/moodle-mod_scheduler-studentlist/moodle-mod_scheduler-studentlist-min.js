YUI.add("moodle-mod_scheduler-studentlist",function(l,e){var t,o="expanded",a="collapsed";M.mod_scheduler=M.mod_scheduler||{},(t=M.mod_scheduler.studentlist={}).setState=function(e,t){var s=l.one("#"+e),d=l.one("#list"+e);t?(d.removeClass(a),d.addClass(o),s.set("src",M.util.image_url("t/expanded"))):(d.removeClass(o),d.addClass(a),s.set("src",M.util.image_url("t/collapsed")))},t.toggleState=function(e){var t=l.one("#list"+e),s=t.hasClass(o);this.setState(e,!s)},t.init=function(e,t){this.setState(e,t),l.one("#"+e).on("click",function(){M.mod_scheduler.studentlist.toggleState(e)})}},"@VERSION@",{requires:["base","node","event","io"]});