/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./src/index.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./src/functions.js":
/*!**************************!*\
  !*** ./src/functions.js ***!
  \**************************/
/*! exports provided: extend_toolbar, get_click_handler, addProviderFilter, addInlineStyle, toggleUI */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "extend_toolbar", function() { return extend_toolbar; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "get_click_handler", function() { return get_click_handler; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "addProviderFilter", function() { return addProviderFilter; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "addInlineStyle", function() { return addInlineStyle; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "toggleUI", function() { return toggleUI; });
/**
 * @param {wp.media.view.Toolbar} toolbar
 * @param {string} selector
 * @return {wp.media.view.Toolbar}
 */
function extend_toolbar(toolbar, selector) {
  return toolbar.extend({
    /**
     * @param {string|Object} id
     * @param {Backbone.View|Object} view
     * @param {Object} [options={}]
     * @return {wp.media.view.Toolbar} Returns itself to allow chaining.
     */
    set: function set(id, view, options) {
      if (selector === id) {
        view.click = get_click_handler(view);
      }

      return toolbar.prototype.set.apply(this, arguments);
    }
  });
}
function get_click_handler(item) {
  var click_handler = item.click;
  var attribute = item.requires.library ? 'library' : 'selection';
  return function (event) {
    var selection_state = this.controller.state().get(attribute);
    var new_attachments = selection_state.models.filter(function (model) {
      return !model.attributes.attachmentExists;
    });
    click_handler = _.bind(click_handler, this);

    if (!new_attachments.length) {
      click_handler();
      return;
    } // Get the current provider.


    var provider = this.controller.state().get('library').props.get('provider');

    if (!provider && Object.keys(AMF_DATA.providers).length > 0) {
      provider = Object.keys(AMF_DATA.providers)[0];
    } // Short circuit for local media provider.


    if (provider === 'local') {
      click_handler();
      return;
    }

    event.target.disabled = true;
    wp.ajax.post('amf-select', {
      selection: selection_state.toJSON(),
      post: wp.media.view.settings.post.id,
      provider: provider
    }).done(function (response) {
      Object.keys(response).forEach(function (key) {
        selection_state.get(key).set('id', response[key]);
      });
      event.target.disabled = false;
      click_handler();
    }).fail(function (response) {
      var message = 'An unknown error occurred.';

      if (response && response[0] && response[0].message) {
        message = response[0].message;
      }

      alert(message);
      event.target.disabled = false;
    });
  };
}
function addProviderFilter() {
  // Short circuit if we don't have providers
  if (!AMF_DATA || !AMF_DATA.hasOwnProperty('providers')) {
    return;
  }

  addInlineStyle("\n\t\t.view-switch { display: none !important; }\n\t\t.media-toolbar-secondary { padding: 12px 0; }\n\t\t.amf-hidden { display: none !important; }\n\t"); // If we have only 1 provider then it's the default, no need for a filter.

  if (Object.keys(AMF_DATA.providers).length < 2) {
    var provider = Object.values(AMF_DATA.providers)[0];
    toggleUI(provider.supports);
    return;
  } // Override core styles that allow only two filter inputs


  addInlineStyle("\n\t\t.media-modal-content .media-frame select.attachment-filters { width: 150px }\n\t\t.media-modal-content .media-frame #media-attachment-provider-filter + .spinner { float: right; margin: -25px -0px 5px 15px; }\n\t"); // Create a new MediaLibraryProviderFilter we later will instantiate

  var MediaLibraryProviderFilter = wp.media.view.AttachmentFilters.extend({
    id: 'media-attachment-provider-filter',
    createFilters: function createFilters() {
      var filters = {}; // Formats the 'providers' we've included via wp_localize_script()

      _.each(AMF_DATA.providers || {}, function (value, index) {
        filters[index] = {
          text: value.name,
          props: {
            provider: index
          }
        };
      });

      this.filters = filters;
    },
    select: function select() {
      var model = this.model,
          value = Object.keys(AMF_DATA.providers)[0],
          props = model.toJSON();

      _.find(this.filters, function (filter, id) {
        var equal = _.all(filter.props, function (prop, key) {
          return prop === (_.isUndefined(props[key]) ? null : props[key]);
        });

        if (equal) {
          value = id;
          return value;
        }
      });

      this.$el.val(value); // Show / hide components based on provider capabilities.

      if (props.provider) {
        toggleUI(AMF_DATA.providers[props.provider].supports);
      }
    }
  }); // Extend and override wp.media.view.AttachmentsBrowser to include our new filter

  var AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
  wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
    createToolbar: function createToolbar() {
      // Make sure to load the original toolbar
      AttachmentsBrowser.prototype.createToolbar.call(this);
      this.toolbar.set('MediaLibraryProviderFilter', new MediaLibraryProviderFilter({
        controller: this.controller,
        model: this.collection.props,
        priority: -75
      }).render());
    }
  });
}
function addInlineStyle(styles) {
  var css = document.createElement('style');
  css.type = 'text/css';

  if (css.styleSheet) {
    css.styleSheet.cssText = styles;
  } else {
    css.appendChild(document.createTextNode(styles));
  }

  document.getElementsByTagName('head')[0].appendChild(css);
}
function toggleUI(supports) {
  jQuery('a[href*="/media-new.php"],.uploader-inline').toggleClass('amf-hidden', !supports.create);
  jQuery('.media-button.delete-selected-button').toggleClass('amf-hidden', !supports.delete);
  jQuery('#media-attachment-date-filters').toggleClass('amf-hidden', !supports.filterDate);
  jQuery('#media-attachment-filters').toggleClass('amf-hidden', !supports.filterType);
}

/***/ }),

/***/ "./src/index.js":
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
/*! no exports provided */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _functions__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./functions */ "./src/functions.js");
/**
 * Overrides for the wp.media library.
 */


(function () {
  wp.media.view.Toolbar = Object(_functions__WEBPACK_IMPORTED_MODULE_0__["extend_toolbar"])(wp.media.view.Toolbar, 'insert');
  wp.media.view.Toolbar.Select = Object(_functions__WEBPACK_IMPORTED_MODULE_0__["extend_toolbar"])(wp.media.view.Toolbar.Select, 'select'); // Support for Smart Media.

  wp.media.view.Toolbar = Object(_functions__WEBPACK_IMPORTED_MODULE_0__["extend_toolbar"])(wp.media.view.Toolbar, 'apply');
  Object(_functions__WEBPACK_IMPORTED_MODULE_0__["addProviderFilter"])();
})();

/***/ })

/******/ });
//# sourceMappingURL=index.js.map