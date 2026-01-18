/*
Copyright (c) 2010 - 2019 Annpoint, s.r.o.

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

-------------------------------------------------------------------------

NOTE: Reuse requires the following acknowledgement (see also NOTICE):
This product includes DayPilot (http://www.daypilot.org) developed by Annpoint, s.r.o.

v2.8
http://code.daypilot.org/81367/daypilot-modal

*/

if (typeof(DayPilot) === 'undefined') {
    DayPilot = {};
}

(function() {
    'use strict';

    DayPilot.ModalStatic = {};

    DayPilot.ModalStatic.list = [];

    // hide the last one
    DayPilot.ModalStatic.hide = function() {
        if (this.list.length > 0) {
            var last = this.list.pop();
            if (last) {
                last.hide();
            }
        }
    };

    DayPilot.ModalStatic.remove = function(modal) {
        var list = DayPilot.ModalStatic.list;
        for (var i = 0; i < list.length; i++) {
            if (list[i] === modal) {
                list.splice(i, 1);
                return;
            }
        }
    };

    DayPilot.ModalStatic.close = function(result) {
        DayPilot.ModalStatic.result(result);
        DayPilot.ModalStatic.hide();
    };

    DayPilot.ModalStatic.result = function(r) {
        var list = DayPilot.ModalStatic.list;
        if (list.length > 0) {
            list[list.length - 1].result = r;
        }
    };

    DayPilot.ModalStatic.displayed = function(modal) {
        var list = DayPilot.ModalStatic.list;
        for (var i = 0; i < list.length; i++) {
            if (list[i] === modal) {
                return true;
            }
        }
        return false;
    };

    DayPilot.ModalStatic.stretch = function() {
        if (this.list.length > 0) {
            var last = this.list[this.list.length - 1];
            if (last) {
                last.stretch();
            }
        }
    };

    DayPilot.ModalStatic.last = function() {
        var list = DayPilot.ModalStatic.list;
        if (list.length > 0) {
            return list[list.length - 1];
        }
        return null;
    };

    var Sheet = function() {
        var style = document.createElement("style");
        style.setAttribute("type", "text/css");
        if (!style.styleSheet) {   // ie
            style.appendChild(document.createTextNode(""));
        }

        var h = document.head || document.getElementsByTagName('head')[0];
        h.appendChild(style);

        var oldStyle = !! style.styleSheet; // old ie

        var sheet = {};
        sheet.rules = [];
        sheet.commit = function() {
            try {
                if (oldStyle) {
                    style.styleSheet.cssText = this.rules.join("\n");
                }
            }
            catch (e) {
                //alert("Error registering the built-in stylesheet (IE stylesheet limit reached). Stylesheet count: " + document.styleSheets.length);
            }
        };

        sheet.add = function(selector, rules, index) {
            if (oldStyle) {
                this.rules.push(selector + "{" + rules + "\u007d");
                return;
            }
            if(style.sheet.insertRule) {  // normal browsers, ie9+
                if (typeof index === "undefined") {
                    index = style.sheet.cssRules.length;
                }
                style.sheet.insertRule(selector + "{" + rules + "\u007d", index);
            }
            else if (style.sheet.addRule) {
                style.sheet.addRule(selector, rules, index);
            }
            else {
                throw "No CSS registration method found";
            }
        };
        return sheet;
    };

    var sheet = new Sheet();
    sheet.add(".modal_default_main", "border: 10px solid #ccc; max-width: 90%;");
    sheet.add(".modal_default_content", "padding: 10px 0px;");
    sheet.add(".modal_default_inner", "padding: 20px;");
    sheet.add(".modal_default_input", "padding: 10px 0px;");
    sheet.add(".modal_default_buttons", "padding: 10px 0px;");
    sheet.add(".modal_default_form_item", "margin: 10px 0px;");
    sheet.add(".modal_default_form_item.modal_default_form_title", "font-size: 2em; font-weight: bold;");
    sheet.add(".modal_default_form_item input", "width: 100%;");
    sheet.add(".modal_default_form_item_label", "margin: 2px 0px;");
    sheet.add(".modal_default_background", "opacity: 0.3; background-color: #000;");
    sheet.add(".modal_default_ok", "padding: 3px; width: 80px;");
    sheet.add(".modal_default_cancel", "padding: 3px; width: 80px;");

    sheet.add(".modal_min_main", "border: 1px solid #ccc; max-width: 90%;");
    sheet.add(".modal_min_background", "opacity: 0.3; background-color: #000;");
    sheet.add(".modal_min_ok", "padding: 3px 10px;");
    sheet.add(".modal_min_cancel", "padding: 3px 10px;");
    sheet.commit();

    //var isIE = (navigator && navigator.userAgent && navigator.userAgent.indexOf("MSIE") !== -1);

    DayPilot.Modal = function(options) {

        // default values
        this.autoFocus = true;
        this.autoStretch = true;  // height will be increased automatically to avoid scrollbar, until this.maxHeight is reached
        this.autoStretchFirstLoadOnly = false;
        this.className = null;
        this.theme = "modal_default";
        this.disposeOnClose = true;
        this.dragDrop = true;
        this.loadingHtml = null;
        this.maxHeight = null; // if not set, it will stretch until the bottom space is equal to this.top
        this.scrollWithPage = true;  // modal window will scroll with the page
        this.useIframe = true; // only for showHtml()
        this.zIndex = 99999;

        this.left = null;  // will be centered if null
        this.width = 600;
        this.top = 20;
        this.height = 200;  // see also autoStretch

        // event handler
        this.closed = null;
        this.onClosed = null;
        this.onShow = null;

        // internal
        var This = this;
        this.id = '_' + new Date().getTime() + 'n' + (Math.random() * 10);
        this._registered = false;

        // drag&drop
        this._start = null;
        this._coords = null;

        this.showHtml = function(html) {

            if (DayPilot.ModalStatic.displayed(this)) {
                throw "This modal dialog is already displayed.";
            }

            if (!this.div) {
                this._create();
            }
            this._update();

            if (this.useIframe) {
                var delayed = function(p, innerHTML) {
                    return function() {
                        p.setInnerHTML(p.id + "iframe", innerHTML);
                    };
                };

                window.setTimeout(delayed(this, html), 0);
            }
            else {
                if (html.nodeType) {
                    this.div.appendChild(html);
                }
                else {
                    this.div.innerHTML = html;
                }
            }

            this._update();
            this._register();
            this._doShow();

        };

        this._rounded = function() {
            return this.corners && this.corners.toLowerCase() === 'rounded';
        };

        this.showUrl = function(url) {

            if (DayPilot.ModalStatic.displayed(this)) {
                throw "This modal dialog is already displayed.";
            }

            //this.useIframe = true; // forced

            if (this.useIframe) {
                if (!this.div) {
                    this._create();
                }

                var loadingHtml = this.loadingHtml;
                if (loadingHtml) {
                    this.iframe.src = "about:blank";
                    this.setInnerHTML(this.id + "iframe", loadingHtml);
                }

                this.re(this.iframe, "load", this._onIframeLoad);

                this.iframe.src = url;
                //this.iframe.contentWindow.modal = This;

                this._update();
                this._register();
                this._doShow();
            }
            else {
                This._ajax({
                    "url": url,
                    "success": function(args) {
                        var html = args.request.responseText;
                        This.showHtml(html);
                    },
                    "error": function(args) {
                        This.showHtml("Error loading the modal dialog");
                    }
                })
            }

        };

        this._doShow = function() {
            if (typeof This.onShow === "function") {
                var args = {};
                args.root = This._body();
                This.onShow(args);
            }
        };

        this._body = function() {
            return This.iframe ? This.iframe.contentWindow.document : This.div;
        };

        this._ajax = function(object) {
            var req = new XMLHttpRequest();
            if (!req) {
                return;
            }

            var method = object.method || "GET";
            var success = object.success || function() {};
            var error = object.error || function() {};
            var data = object.data;
            var url = object.url;

            req.open(method, url, true);
            req.setRequestHeader('Content-type', 'text/plain');
            req.onreadystatechange = function() {
                if (req.readyState !== 4)
                    return;
                if (req.status !== 200 && req.status !== 304) {
                    if (error) {
                        var args = {};
                        args.request = req;
                        error(args);
                    }
                    else {
                        if (window.console) { console.log('HTTP error ' + req.status); }
                    }
                    return;
                }
                var args = {};
                args.request = req;
                success(args);
            };
            if (req.readyState === 4) {
                return;
            }
            if (typeof data === 'object') {
                data = JSON.stringify(data);
            }
            req.send(data);
        };

        this._update = function() {

            delete this.result;

            var win = window;
            var doc = document;

            var scrollY = win.pageYOffset ? win.pageYOffset : ((doc.documentElement && doc.documentElement.scrollTop) ? doc.documentElement.scrollTop : doc.body.scrollTop);

            var height = function() {
                return This._windowRect().y;
            };

            //this.hideDiv.style.height = height() + "px";
            if (this.theme) {
                this.hideDiv.className = this.theme + "_background";
            }
            /*
            this.hideDiv.style.filter = "alpha(opacity=" + this.opacity + ")";
            this.hideDiv.style.opacity = "0." + this.opacity;
            this.hideDiv.style.backgroundColor = "black";
            */
            if (this.zIndex) {
                this.hideDiv.style.zIndex = this.zIndex;
            }
            this.hideDiv.style.display = '';

            window.setTimeout(function() {
                if (This.hideDiv) {
                    This.hideDiv.onclick = function() {
                        This.hide({"backgroundClick": true});
                    };
                }
            }, 500);

            if (this.theme) {
                this.div.className = this.theme + "_main";
            }
            else {
                this.div.className = "";
            }

            if (this.className) {
                this.div.className += " " + this.className;
            }
            /*
            this.div.style.border = this.border;
            if (this._rounded()) {
                this.div.style.MozBorderRadius = "5px";
                this.div.style.webkitBorderRadius = "5px";
                this.div.style.borderRadius = "5px";
            }
            */
            if (this.left) {
                this.div.style.left = this.left + "px";
            }
            else {
                this.div.style.marginLeft = '-' + Math.floor(this.width / 2) + "px";  // '-45%'
            }
            this.div.style.position = 'absolute';
            this.div.style.boxSizing = "content-box";
            this.div.style.top = (scrollY + this.top) + 'px';
            this.div.style.width = this.width + 'px';  // '90%'
            if (this.zIndex) {
                this.div.style.zIndex = this.zIndex;
            }

            if (this.height) {
                if (this.useIframe || !this.autoStretch) {
                    this.div.style.height = this.height + 'px';
                }
                else {
                    this.div.style.height = '';
                }
            }
            if (this.useIframe && this.height) {
                this.iframe.style.height = (this.height) + 'px';
            }

            this.div.style.display = '';

            this._updateHorizontal();

            // make sure it's there just once
            DayPilot.ModalStatic.remove(this);
            DayPilot.ModalStatic.list.push(this);

            /*
            if (this.iframe) {
            this.iframe.onload = null;
            }
            */
        };

        this._onIframeLoad = function() {
            This.iframe.contentWindow.modal = This;
            if (This.autoStretch) {
                This.stretch();
            }
        };

        this.stretch = function() {
            var height = function() {
                return This._windowRect().y;
            };

            var width = function() {
                return This._windowRect().x;
            };


            if (this.useIframe) {
                // width first
                var maxWidth = width() - 40;  // fixed 20px margin
                for (var w = this.width; w < maxWidth && this._hasHorizontalScrollbar(); w += 10) {
                    //this.iframe.style.width = (w) + 'px';
                    this.div.style.width = w + 'px';
                    this.div.style.marginLeft = '-' + Math.floor(w / 2) + "px";  //
                }

                // height
                var maxHeight = this.maxHeight || height() - 2 * this.top;
                for (var h = this.height; h < maxHeight && this._hasVerticalScrollbar(); h += 10) {
                    this.iframe.style.height = (h) + 'px';
                    this.div.style.height = h + 'px';
                }

                if (this.autoStretchFirstLoadOnly) {
                    this.ue(this.iframe, "load", this._onIframeLoad);
                }
            }
            else {
                this.div.style.height = '';
            }


        };

        this._hasHorizontalScrollbar = function() {
            var document = this.iframe.contentWindow.document;
            var root = document.compatMode === 'BackCompat' ? document.body : document.documentElement;

            var scrollWidth = root.scrollWidth;
            var children = document.body.children;
            for (var i = 0; i < children.length; i++) {
                var bottom = children[i].offsetLeft + children[i].offsetWidth;
                scrollWidth = Math.max(scrollWidth, bottom);
            }

            var isHorizontalScrollbar = scrollWidth > root.clientWidth;
            return isHorizontalScrollbar;

        };

        this._hasVerticalScrollbar = function() {
            var document = this.iframe.contentWindow.document;
            var root = document.compatMode === 'BackCompat' ? document.body : document.documentElement;

            var scrollHeight = root.scrollHeight;
            var children = document.body.children;
            for (var i = 0; i < children.length; i++) {
                var bottom = children[i].offsetTop + children[i].offsetHeight;
                scrollHeight = Math.max(scrollHeight, bottom);
            }

            var isVerticalScrollbar =  scrollHeight > root.clientHeight;
            //var isHorizontalScrollbar = root.scrollWidth > root.clientWidth;
            return isVerticalScrollbar;
        };

        this._windowRect = function() {
            var doc = document;

            if (doc.compatMode === "CSS1Compat" && doc.documentElement && doc.documentElement.clientWidth) {
                var x = doc.documentElement.clientWidth;
                var y = doc.documentElement.clientHeight;
                return { x: x, y: y };
            }
            else {
                var x = doc.body.clientWidth;
                var y = doc.body.clientHeight;
                return { x: x, y: y };
            }
        };

        this._register = function() {
            if (this._registered) {
                return;
            }
            this.re(window, 'resize', this._onWindowResize);
            this.re(window, 'scroll', this._onWindowScroll);

            if (this.dragDrop) {
                this.re(document, 'mousemove', this._onMouseMove);
                this.re(document, 'mouseup', this._onMouseUp);
            }
            this._registered = true;
        };

        this._unregister = function() {
            this.ue(window, 'resize', this._onWindowResize);
            this.ue(window, 'scroll', this._onWindowScroll);
            if (this.dragDrop) {
                this.ue(document, 'mousemove', this._onMouseMove);
                this.ue(document, 'mouseup', this._onMouseUp);
            }
            this._registered = false;
        };

        this._onDragStart = function(e) {
            if (e.target !== This.div) {
                return;
            }
            This.div.style.cursor = "move";
            This._maskIframe();
            This._coords = This.mc(e || window.event);
            This._start = { x: This.div.offsetLeft, y: This.div.offsetTop };

        };

        this._onMouseMove = function(e) {
            if (!This._coords) {
                return;
            }

            var e = e || window.event;
            var now = This.mc(e);

            var x = now.x - This._coords.x;
            var y = now.y - This._coords.y;

            //This.iframe.style.display = 'none';
            This.div.style.marginLeft = '0px';
            This.div.style.top = (This._start.y + y) + "px";
            This.div.style.left = (This._start.x + x) + "px";

        };

        this._onMouseUp = function(e) {
            // no drag&drop
            if (!This._coords) {
                return;
            }
            //This.iframe.style.display = '';
            This._unmaskIframe();
            This.div.style.cursor = null;

            This._coords = null;
        };

        this._maskIframe = function() {
            if (!this.useIframe) {
                return;
            }

            var opacity = 80;

            var mask = document.createElement("div");
            mask.style.backgroundColor = "#ffffff";
            mask.style.filter = "alpha(opacity=" + opacity + ")";
            mask.style.opacity = "0." + opacity;
            mask.style.width = "100%";
            mask.style.height = this.height + "px";
            mask.style.position = "absolute";
            mask.style.left = '0px';
            mask.style.top = '0px';

            this.div.appendChild(mask);
            this.mask = mask;
        };

        this._unmaskIframe = function() {
            if (!this.useIframe) {
                return;
            }

            this.div.removeChild(this.mask);
            this.mask = null;
        };

        this._onWindowResize = function() {
            This._updateTop();
            This._updateHorizontal();
        };

        this._onWindowScroll = function() {
            This._updateTop();
        };

        this._updateHorizontal = function() {
            if (This.left) {
                return;
            }

            if (!This.div) {
                return;
            }

            var width = This.div.offsetWidth;
            This.div.style.marginLeft = '-' + Math.floor(width / 2) + "px";  // '-45%'
        };

        this._updateTop = function() {
            if (!This.hideDiv) {
                return;
            }
            if (!This.div) {
                return;
            }
            if (This.hideDiv.style.display === 'none') {
                return;
            }
            if (This.div.style.display === 'none') {
                return;
            }

            var scrollY = This._parent.scrollY();


            //This.hideDiv.style.height = height() + "px";
            if (!This.scrollWithPage) {
                This.div.style.top = (scrollY + This.top) + 'px';
            }
        };

        this._parent = {};
        this._parent.container = function() {
            return This.container || document.body;
        };
        this._parent.scrollY = function() {
            var c = This._parent.container();
            if (c === document.body) {
                return window.pageYOffset ? window.pageYOffset : ((document.documentElement && document.documentElement.scrollTop) ? document.documentElement.scrollTop : document.body.scrollTop);
            }
            else {
                return c.scrollTop;
            }
        };

        // already available in common.js but this file should be standalone
        this.re = function(el, ev, func) {
            if (el.addEventListener) {
                el.addEventListener(ev, func, false);
            } else if (el.attachEvent) {
                el.attachEvent("on" + ev, func);
            }
        };

        // unregister event
        this.ue = function(el, ev, func) {
            if (el.removeEventListener) {
                el.removeEventListener(ev, func, false);
            } else if (el.detachEvent) {
                el.detachEvent("on" + ev, func);
            }
        };

        // mouse coords
        this.mc = function(ev) {
            if (ev.pageX || ev.pageY) {
                return { x: ev.pageX, y: ev.pageY };
            }
            return {
                x: ev.clientX + document.documentElement.scrollLeft,
                y: ev.clientY + document.documentElement.scrollTop
            };
        };

        // absolute element position on page
        this.abs = function(element) {
            var r = {
                x: element.offsetLeft,
                y: element.offsetTop
            };

            while (element.offsetParent) {
                element = element.offsetParent;
                r.x += element.offsetLeft;
                r.y += element.offsetTop;
            }

            return r;
        };

        this._create = function() {

            var container = This._parent.container();
            var isRoot = container === document.body;
            var position = isRoot ? "fixed" : "absolute";

            var hide = document.createElement("div");
            hide.id = this.id + "hide";
            hide.style.position = position;
            hide.style.left = "0px";
            hide.style.top = "0px";
            hide.style.right = "0px";
            hide.style.bottom = "0px";
            hide.oncontextmenu = function() { return false; };
            hide.onmousedown = function() { return false; };  // prevent selecting

            container.appendChild(hide);

            var div = document.createElement("div");
            div.id = this.id + 'popup';
            div.style.position = position;
            div.style.left = '50%';
            div.style.top = '0px';
            div.style.backgroundColor = 'white';
            div.style.width = "50px";
            div.style.height = "50px";
            if (this.dragDrop) {
                div.onmousedown = this._onDragStart;
            }

            var defaultHeight = 50;

            var iframe = null;
            if (this.useIframe) {
                iframe = document.createElement("iframe");
                iframe.id = this.id + "iframe";
                iframe.name = this.id + "iframe";
                iframe.frameBorder = '0';
                iframe.style.width = '100%';
                iframe.style.height = defaultHeight + 'px';
                div.appendChild(iframe);
            }

            container.appendChild(div);

            this.div = div;
            this.iframe = iframe;
            this.hideDiv = hide;
        };

        this.setInnerHTML = function(id, innerHTML) {
            var frame = window.frames[id];

            var doc = frame.contentWindow || frame.document || frame.contentDocument;
            if (doc.document) {
                doc = doc.document;
            }

            if (doc.body == null) {       // null in IE
                doc.write("<body></body>");
            }

            if (innerHTML.nodeType) {
                doc.body.appendChild(innerHTML);
            }
            else {
                doc.body.innerHTML = innerHTML;
            }

            if (This.autoStretch) {
                if (!This.autoStretchFirstLoadOnly || !This._stretched) {
                    This.stretch();
                    This._stretched = true;
                }
            }
        };

        this.close = function(result) {
            this.result = result;
            this.hide();
        };

        this.closeSerialized = function() {
            var ref = This._body();
            var fields = ref.querySelectorAll("input, textarea, select");
            var result = {};
            for (var i = 0; i < fields.length; i++) {
                var field = fields[i];
                var name = field.name;
                if (!name) {
                    continue;
                }
                var value = field.value;
                result[name] = value;
            }
            This.close(result);
        };

        this.hide = function(options) {

            options = options || {};

            var args = {};
            args.backgroundClick = !!options.backgroundClick;
            args.result = this.result;
            args.canceled = typeof this.result === "undefined";
            args.preventDefault = function() {
                this.preventDefault.value = true;
            };

            if (typeof this.onClose === "function") {
                this.onClose(args);
                if (args.preventDefault.value) {
                    return;
                }
            }

            if (this.div) {
                this.div.style.display = 'none';
                this.hideDiv.style.display = 'none';
                if (!this.useIframe) {
                    this.div.innerHTML = null;
                }
            }

            // return focus to the main window (Firefox)
            window.focus();

            //DayPilot.ModalStatic = null;
            DayPilot.ModalStatic.remove(this);

            if (typeof this.onClosed === "function") {
                this.onClosed(args);
            }
            else if (this.closed) {
                this.closed();
            }

            delete this.result;

            if (this.disposeOnClose) {

                This._unregister();

                This._de(This.div);
                This._de(This.hideDiv);

                This.div = null;
                This.hideDiv = null;
                This.iframe = null;
            }
        };

        this._de = function(e) {
            if (!e) {
                return;
            }
            e.parentNode && e.parentNode.removeChild(e);
        };

        this._applyOptions = function() {
            if (!options) {
                return;
            }

            for (var name in options) {
                this[name] = options[name];
            }
        };

        this._applyOptions();

    };

    DayPilot.Modal.alert = function(message, options) {
        options = options || {};
        options.height = options.height || 40;
        options.useIframe = false;

        var okText = options.okText || "OK";
        var cancelText = options.cancelText || "Cancel";

        return DayPilot.getPromise(function(success, failure) {
            options.onClosed = function(args) {
                success(args);
                /*
                if (typeof args.result === "undefined") {
                    failure(args);
                }
                else {

                }*/
            };

            var modal = new DayPilot.Modal(options);

            var div = document.createElement("div");
            div.className = modal.theme + "_inner";
            // div.style.padding = "10px";

            var text = document.createElement("div");
            text.className = modal.theme + "_content";
            text.innerHTML = message;

            var buttons =  document.createElement("div");
            buttons.className = modal.theme + "_buttons";
            // buttons.style.margin = "10px 0px";

            var buttonOK = document.createElement("button");
            buttonOK.innerText = okText;
            buttonOK.className = modal.theme + "_ok";
            buttonOK.onclick = function(e) {
                DayPilot.ModalStatic.close("OK");
            };

            buttons.appendChild(buttonOK);

            div.appendChild(text);
            div.appendChild(buttons);

            //var buttons = "<div style='margin: 10px 0px'><button onclick='parent.DayPilot.ModalStatic.close();' autofocus>OK</button></div>";

            modal.showHtml(div);

            if (modal.autoFocus) {
                buttonOK.focus();
            }
        });

    };

    DayPilot.Modal.confirm = function(message, options) {
        options = options || {};
        options.height = options.height || 40;
        options.useIframe = false;

        var okText = options.okText || "OK";
        var cancelText = options.cancelText || "Cancel";

        return DayPilot.getPromise(function(success, failure) {
            options.onClosed = function(args) {
                success(args);
            };

            var modal = new DayPilot.Modal(options);

            var div = document.createElement("div");
            div.className = modal.theme + "_inner";
            // div.style.padding = "10px";

            var text = document.createElement("div");
            text.className = modal.theme + "_content";
            text.innerHTML = message;

            var buttons =  document.createElement("div");
            buttons.className = modal.theme + "_buttons";
            // buttons.style.margin = "10px 0px";

            var buttonOK = document.createElement("button");
            buttonOK.innerText = okText;
            buttonOK.className = modal.theme + "_ok";
            buttonOK.onclick = function(e) {
                DayPilot.ModalStatic.close("OK");
            };

            var space = document.createTextNode(" ");

            var buttonCancel = document.createElement("button");
            buttonCancel.innerText = cancelText;
            buttonCancel.className = modal.theme + "_cancel";
            buttonCancel.onclick = function(e) {
                DayPilot.ModalStatic.close();
            };

            buttons.appendChild(buttonOK);
            buttons.appendChild(space);
            buttons.appendChild(buttonCancel);

            div.appendChild(text);
            div.appendChild(buttons);

            modal.showHtml(div);

            if (modal.autoFocus) {
                buttonOK.focus();
            }

        });
    };


    DayPilot.Modal.prompt = function(message, defaultValue, options) {
        if (typeof defaultValue === "object") {
            options = defaultValue;
            defaultValue = "";
        }

        options = options || {};
        options.height = options.height || 40;
        options.useIframe = false;

        var okText = options.okText || "OK";
        var cancelText = options.cancelText || "Cancel";

        var inputText = defaultValue || "";

        return DayPilot.getPromise(function(success, failure) {
            options.onClosed = function(args) {
                success(args);
            };

            var modal = new DayPilot.Modal(options);

            var div = document.createElement("div");
            div.className = modal.theme + "_inner";

            var text = document.createElement("div");
            text.className = modal.theme + "_content";
            text.innerHTML = message;

            var inputs = document.createElement("div");
            inputs.className = modal.theme + "_input";
            //inputs.style.margin = "10px 0px";

            var input = document.createElement("input");
            input.value = inputText;
            input.style.width = "100%";
            input.onkeydown = function(e) {
                var letcontinue = false;
                switch (e.keyCode) {
                    case 13:
                        modal.close(this.value);
                        break;
                    case 27:
                        modal.close();
                        break;
                    default:
                        letcontinue = true;
                        break;
                }
                if (!letcontinue) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            };

            inputs.appendChild(input);

            var buttons =  document.createElement("div");
            buttons.className = modal.theme + "_buttons";
            //buttons.style.margin = "10px 0px";

            var buttonOK = document.createElement("button");
            buttonOK.innerText = okText;
            buttonOK.className = modal.theme + "_ok";
            buttonOK.onclick = function(e) {
                modal.close(input.value);
            };

            var space = document.createTextNode(" ");

            var buttonCancel = document.createElement("button");
            buttonCancel.innerText = cancelText;
            buttonCancel.className = modal.theme + "_cancel";
            buttonCancel.onclick = function(e) {
                modal.close();
            };

            buttons.appendChild(buttonOK);
            buttons.appendChild(space);
            buttons.appendChild(buttonCancel);


            div.appendChild(text);
            div.appendChild(inputs);
            div.appendChild(buttons);

            modal.showHtml(div);

            if (modal.autoFocus) {
                input.focus();
            }

        });
    };

    /*
    item.def = default value
    item.id = id + name
    item.cssClass = custom css
    item.name = label (HTML)
     */
    var createFormField = function(modal, item) {
        var input = null;

        if (typeof item.id === "string") {
            input = document.createElement("input");
            input.id = item.id;
            input.name = item.id;
            input.value = item.def || "";
            input.onkeydown = function(e) {
                var letcontinue = false;
                switch (e.keyCode) {
                    case 13:
                        modal.closeSerialized();
                        break;
                    case 27:
                        modal.close();
                        break;
                    default:
                        letcontinue = true;
                        break;
                }
                if (!letcontinue) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            };
        }

        var label = document.createElement("div");
        label.className = modal.theme + "_form_item_label";
        label.innerHTML = item.name;

        var div = document.createElement("div");
        div.className = modal.theme + "_form_item";
        if (!item.id) {
            div.className += " " + modal.theme + "_form_title";
        }
        if (item.cssClass) {
            div.className += " " + item.cssClass;
        }
        div.appendChild(label);

        if (input) {
            div.appendChild(input);
        }

        var field = {};
        field.div = div;
        field.label = label;
        field.element = input;
        field.set = function(value) {
            input.value = value;
        };
        field.focus = function() {
            if (input) {
                input.focus();
            }
        };
        field.canFocus = function() {
            return !!input;
        };

        return field;
    };

    // form = array of items
    // data = object with properties
    // options = standard modal properties
    // DayPilot.Modal.form([]);  // form only - new data
    // DayPilot.Modal.form({});  // data only - automatic form
    //
    DayPilot.Modal.form = function(form, data, options) {
        if (arguments.length === 1) {
            var arg = form;
            var isArray = Object.prototype.toString.call(arg) === '[object Array]';

            if (isArray) {
                data = {};
            }
            else if (typeof arg === "object") {
                data = form;
                form = [];
                for (var name in data) {
                    var item = {};
                    item.name = name;
                    item.id = name;
                    form.push(item);
                }
            }
            else {
                throw "Invalid DayPilot.Modal.form() parameter";
            }

        }

        options = options || {};
        options.height = options.height || 40;
        options.useIframe = false;

        var okText = options.okText || "OK";
        var cancelText = options.cancelText || "Cancel";

        var message = options.message || "";

        return DayPilot.getPromise(function(success, failure) {
            options.onClosed = function(args) {
                success(args);
            };

            var modal = new DayPilot.Modal(options);

            var div = document.createElement("div");
            div.className = modal.theme + "_inner";

            var text = document.createElement("div");
            text.className = modal.theme + "_content";
            text.innerHTML = message;

            var inputs = document.createElement("div");
            inputs.className = modal.theme + "_input";

            var fields = [];
            var refs = {};

            // create fields
            form.forEach(function(item) {
                var field = createFormField(modal, item);

                fields.push(field);
                refs[item.id] = field;
            });

            // set values
            for(var name in data) {
                if (refs[name]) {
                    refs[name].set(data[name]);
                }
            }

            // add to DOM
            fields.forEach(function(field) {
                inputs.appendChild(field.div);
            });

            var buttons =  document.createElement("div");
            buttons.className = modal.theme + "_buttons";

            var buttonOK = document.createElement("button");
            buttonOK.innerText = okText;
            buttonOK.className = modal.theme + "_ok";
            buttonOK.onclick = function(e) {
                modal.closeSerialized();
            };

            var space = document.createTextNode(" ");

            var buttonCancel = document.createElement("button");
            buttonCancel.innerText = cancelText;
            buttonCancel.className = modal.theme + "_cancel";
            buttonCancel.onclick = function(e) {
                modal.close();
            };

            buttons.appendChild(buttonOK);
            buttons.appendChild(space);
            buttons.appendChild(buttonCancel);


            div.appendChild(text);
            div.appendChild(inputs);
            div.appendChild(buttons);

            modal.showHtml(div);

            if (modal.autoFocus) {
                // focus the first one
                var first = fields.find(function(field) {
                    return field.canFocus();
                });
                first && first.focus();
            }

        });
    };

    DayPilot.Modal.close = function(result) {
        var opener = DayPilot.Modal.opener();
        if (!opener) {
            // throw "Unable to find the opener DayPilot.Modal instance.";
            //console.log("Unable to find opener (DayPilot.Modal instance).");
            return;
        }
        opener.close(result);
    };

    DayPilot.Modal.stretch = function(result) {
        var opener = DayPilot.Modal.opener();
        if (!opener) {
            throw "Unable to find the opener DayPilot.Modal instance.";
        }
        opener.stretch();
    };

    DayPilot.Modal.closeSerialized = function() {
        var last = DayPilot.Modal.opener() || DayPilot.ModalStatic.last();
    };

    DayPilot.Modal.opener = function() {
        if (typeof DayPilot !== "undefined" && typeof DayPilot.ModalStatic !== "undefined" && DayPilot.ModalStatic.list.length > 0) {
            return DayPilot.ModalStatic.list[DayPilot.ModalStatic.list.length - 1];
        }
        return parent && parent.DayPilot && parent.DayPilot.ModalStatic && parent.DayPilot.ModalStatic.list[parent.DayPilot.ModalStatic.list.length - 1];
    };

    if (typeof DayPilot.getPromise === "undefined") {
        DayPilot.getPromise = function(f) {
            if (typeof Promise !== 'undefined') {
                return new Promise(f);
            }

            DayPilot.Promise = function(f) {
                var p = this;

                this.then = function(onFulfilled, onRejected) {
                    onFulfilled = onFulfilled || function() {};
                    onRejected = onRejected || function() {};
                    f(onFulfilled, onRejected);
                    return DayPilot.getPromise(f);
                };

                this['catch'] = function(onRejected) {
                    p.then(null, onRejected);
                    return DayPilot.getPromise(f);
                };
            };

            return new DayPilot.Promise(f);

        };
    }

})();
