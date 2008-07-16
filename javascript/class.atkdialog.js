if (!window.ATK) {
  var ATK = {};
}

ATK.Dialog = Class.create();
ATK.Dialog.stack = [];
ATK.Dialog.prototype = {
  /**
   * Constructor.
   */
  initialize: function(title, url, theme, options, windowOptions) {
    this.title = title;
    this.url = url;
    this.theme = theme || 'alphacube';
    this.options  = options || {};
    this.windowOptions = windowOptions || {};
  },

  /**
   * Eval JavaScripts and optionally resizes afterwards.
   */
  evalScripts: function(resize) {
    var dialog = this;
    
    (function() {
      dialog.scripts.map(function(script) { 
        atkEval(script);
      });
      
      if (resize && !dialog.options.width && !dialog.options.height) {
        dialog.delayedResize();
      }
    }).defer();
  },
  
  /**
   * Used internally.
   */
  onUpdate: function(transport) {
    this.scripts = transport.responseText.extractScripts();
    this.evalScripts(true);
  },

  /**
   * Used internally.
   */
  onShow: function(transport) {
    var windowParameters = { className: this.theme, title: this.title, onShow: this.evalScripts.bind(this, true) };
    if (this.options.width)
      windowParameters['width'] = this.options.width;
    if (this.options.height)
      windowParameters['height'] = this.options.height;
    windowParameters = Object.extend(windowParameters, this.windowOptions);
    
    this.scripts = transport.responseText.extractScripts();
    Dialog.info(transport.responseText.stripScripts(), { windowParameters: windowParameters });
  },

  /**
   * Delayed auto-resize. Sometimes needed because the content is not always
   * fully updated yet (you don't always know how long it takes to update the DOM).
   */
  delayedResize: function() {
    setTimeout(this.resize.bind(this), 800);
  },

  /**
   * Auto-resize the dialog.
   */
  resize: function() {
    var element = $('modal_dialog_message');
    if (!element) return;

    var d = Element.getDimensions(element);
    var p = Position.cumulativeOffset(element);

    var window = Windows.getWindow(Dialog.dialogId);
    window.setSize(d.width, d.height);
    window.setLocation(p[1] - window.heightN, p[0] - window.widthW);
  },

  /**
   * Serialize form.
   */
  serializeForm: function() {
    var elements = Form.getElements(this.options.serializeForm);
    var queryComponents = new Array();

    for (var i = 0; i < elements.length; i++) {
      if (elements[i].name && elements[i].name.substring(0, 3) != 'atk') {
        var queryComponent = Form.Element.serialize(elements[i]);
        if (queryComponent)
          queryComponents.push(queryComponent);
      }
    }

    return queryComponents.join('&');
  },

  /**
   * Show dialog.
   */
  show: function() {
    ATK.Dialog.stack.push(this);

    var options = {};
    options['evalJS'] = false;
    options['onSuccess'] = this.onShow.bind(this);
    if (this.options.serializeForm) {
      options['parameters'] = this.serializeForm();
    }

    new Ajax.Request(this.url, options);
  },

  /**
   * Save dialog contents using an Ajax request to the given URL.
   * The given extraParams (optional) will be appended at the end of the
   * serialized dialog form.
   */
  save: function(url, form, extraParams, options) {
    var params = Form.serialize(form);

    if (extraParams != null)
      params += '&' + $H(extraParams).toQueryString();

    if (window.getCurrentTab)
    	params += '&atkparenttab=' + getCurrentTab();

    var options = options || {};

    var updateFunc = this.onUpdate.bind(this);
    var successFunc = options['onSuccess'] || function() { };

    var options = options || {};
    options['parameters'] = params;
    options['onSuccess'] = function(transport) { updateFunc(transport); successFunc(transport); };

    new Ajax.Request(url, options);
  },

  /**
   * Save dialog contents and closes the dialog immediately.
   */
  saveAndClose: function(url, form, extraParams) {
    this.save(url, form, extraParams, { onSuccess: this.close.bind(this) });
    this.close();
  },

  /**
   * Close / cancel dialog.
   */
  close: function() {
    ATK.Dialog.stack.pop();
    Dialog.closeInfo();
  },

  /**
   * Update dialog contents.
   */
  update: function(content) {
    var element = $('modal_dialog_message');
    element.update(content);
    this.resize();
  },

  /**
   * Update dialog contents with the results of the given URL.
   */
  ajaxUpdate: function(url) {
    var options = {};   
    options['evalJS'] = false;
    options['onSuccess'] = this.onUpdate.bind(this);
    if (this.options.serializeForm) {
      options['parameters'] = this.serializeForm();
    }
	
    new Ajax.Updater('modal_dialog_message', url, options);
  },

  /**
   * Reload dialog contents.
   */
  reload: function() {
	  this.ajaxUpdate(this.url);
  }
};

/**
 * Returns the active dialog.
 */
ATK.Dialog.getCurrent = function() {
  return ATK.Dialog.stack[ATK.Dialog.stack.length - 1];
}