var sbb_formSubmitted = false;
var lastKeepAliveCheck = new Date().getTime();
var sbb_editorArray = new Array();

// Some very basic browser detection - from Mozilla's sniffer page.
var ua = navigator.userAgent.toLowerCase();

var is_opera = ua.indexOf('opera') != -1;
var is_ff = (ua.indexOf('firefox') != -1 || ua.indexOf('iceweasel') != -1 || ua.indexOf('icecat') != -1 || ua.indexOf('shiretoko') != -1 || ua.indexOf('minefield') != -1) && !is_opera;
var is_gecko = ua.indexOf('gecko') != -1 && !is_opera;

var is_chrome = ua.indexOf('chrome') != -1;
var is_safari = ua.indexOf('applewebkit') != -1 && !is_chrome;
var is_webkit = ua.indexOf('applewebkit') != -1;

var is_ie = ua.indexOf('msie') != -1 && !is_opera;
// Stupid Microsoft...
var is_ie11 = ua.indexOf('trident') != -1 && ua.indexOf('gecko') != -1;
var is_iphone = ua.indexOf('iphone') != -1 || ua.indexOf('ipod') != -1;
var is_android = ua.indexOf('android') != -1;

var ajax_indicator_ele = null;

// Some older versions of Mozilla don't have this, for some reason.
if (!('forms' in document))
	document.forms = document.getElementsByTagName('form');

// Versions of ie < 9 do not have this built in
if (!('getElementsByClassName' in document))
{
	document.getElementsByClassName = function(className)
	{
		return $('".' + className + '"');
	}
}

// Get a response from the server.
function getServerResponse(sUrl, funcCallback, sType, sDataType)
{
	var oCaller = this;
	var oMyDoc = $.ajax({
		type: sType,
		url: sUrl,
		cache: false,
		dataType: sDataType,
		success: function(response) {
			if (typeof(funcCallback) != 'undefined')
			{
				funcCallback.call(oCaller, response);
			}
		},
	});

	return oMyDoc;
}

// Load an XML document.
function getXMLDocument(sUrl, funcCallback)
{
	var oCaller = this;
	var oMyDoc = $.ajax({
		type: 'GET',
		url: sUrl,
		cache: false,
		dataType: 'xml',
		success: function(responseXML) {
			if (typeof(funcCallback) != 'undefined')
			{
				funcCallback.call(oCaller, responseXML);
			}
		},
	});

	return oMyDoc;
}

// Send a post form to the server.
function sendXMLDocument(sUrl, sContent, funcCallback)
{
	var oCaller = this;
	var oSendDoc = $.ajax({
		type: 'POST',
		url: sUrl,
		data: sContent,
		beforeSend: function(xhr) {
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		},
		dataType: 'xml',
		success: function(responseXML) {
			if (typeof(funcCallback) != 'undefined')
			{
				funcCallback.call(oCaller, responseXML);
			}
		},
	});

	return true;
}

// A property we'll be needing for php_to8bit.
String.prototype.oCharsetConversion = {
	from: '',
	to: ''
};

// Convert a string to an 8 bit representation (like in PHP).
String.prototype.php_to8bit = function ()
{
		var n, sReturn = '';

		for (var i = 0, iTextLen = this.length; i < iTextLen; i++)
		{
			n = this.charCodeAt(i);
			if (n < 128)
				sReturn += String.fromCharCode(n);
			else if (n < 2048)
				sReturn += String.fromCharCode(192 | n >> 6) + String.fromCharCode(128 | n & 63);
			else if (n < 65536)
				sReturn += String.fromCharCode(224 | n >> 12) + String.fromCharCode(128 | n >> 6 & 63) + String.fromCharCode(128 | n & 63);
			else
				sReturn += String.fromCharCode(240 | n >> 18) + String.fromCharCode(128 | n >> 12 & 63) + String.fromCharCode(128 | n >> 6 & 63) + String.fromCharCode(128 | n & 63);
		}

		return sReturn;
	}

// Character-level replacement function.
String.prototype.php_strtr = function (sFrom, sTo)
{
	return this.replace(new RegExp('[' + sFrom + ']', 'g'), function (sMatch) {
		return sTo.charAt(sFrom.indexOf(sMatch));
	});
}

// Simulate PHP's strtolower (in SOME cases PHP uses ISO-8859-1 case folding).
String.prototype.php_strtolower = function ()
{
	return typeof(sbb_iso_case_folding) == 'boolean' && sbb_iso_case_folding == true ? this.php_strtr(
		'ABCDEFGHIJKLMNOPQRSTUVWXYZ\x8a\x8c\x8e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde',
		'abcdefghijklmnopqrstuvwxyz\x9a\x9c\x9e\xff\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe'
	) : this.php_strtr('ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
}

String.prototype.php_urlencode = function()
{
	return escape(this).replace(/\+/g, '%2b').replace('*', '%2a').replace('/', '%2f').replace('@', '%40');
}

String.prototype.php_htmlspecialchars = function()
{
	return this.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

String.prototype.php_unhtmlspecialchars = function()
{
	return this.replace(/&quot;/g, '"').replace(/&gt;/g, '>').replace(/&lt;/g, '<').replace(/&amp;/g, '&');
}

String.prototype.php_addslashes = function()
{
	return this.replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
}

String.prototype._replaceEntities = function(sInput, sDummy, sNum)
{
	return String.fromCharCode(parseInt(sNum));
}

String.prototype.removeEntities = function()
{
	return this.replace(/&(amp;)?#(\d+);/g, this._replaceEntities);
}

String.prototype.easyReplace = function (oReplacements)
{
	var sResult = this;
	for (var sSearch in oReplacements)
		sResult = sResult.replace(new RegExp('%' + sSearch + '%', 'g'), oReplacements[sSearch]);

	return sResult;
}

// Open a new window
function reqWin(desktopURL, alternateWidth, alternateHeight, noScrollbars)
{
	if ((alternateWidth && self.screen.availWidth * 0.8 < alternateWidth) || (alternateHeight && self.screen.availHeight * 0.8 < alternateHeight))
	{
		noScrollbars = false;
		alternateWidth = Math.min(alternateWidth, self.screen.availWidth * 0.8);
		alternateHeight = Math.min(alternateHeight, self.screen.availHeight * 0.8);
	}
	else
		noScrollbars = typeof(noScrollbars) == 'boolean' && noScrollbars == true;

	window.open(desktopURL, 'requested_popup', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=' + (noScrollbars ? 'no' : 'yes') + ',width=' + (alternateWidth ? alternateWidth : 480) + ',height=' + (alternateHeight ? alternateHeight : 220) + ',resizable=no');

	// Return false so the click won't follow the link ;).
	return false;
}

// Open a overlay div
function reqOverlayDiv(desktopURL, sHeader, sIcon)
{
	// Set up our div details
	var sAjax_indicator = '<div class="centertext"><img src="' + sbb_images_url + '/loading_sm.gif"></div>';
	var sIcon = sbb_images_url + '/' + (typeof(sIcon) == 'string' ? sIcon : 'helptopics.png');
	var sHeader = typeof(sHeader) == 'string' ? sHeader : help_popup_heading_text;

	// Create the div that we are going to load
	var oContainer = new smc_Popup({heading: sHeader, content: sAjax_indicator, icon: sIcon});
	var oPopup_body = $('#' + oContainer.popup_id).find('.popup_content');

	// Load the help page content (we just want the text to show)
	$.ajax({
		url: desktopURL,
		type: "GET",
		dataType: "html",
		beforeSend: function () {
		},
		success: function (data, textStatus, xhr) {
			var help_content = $('<div id="temp_help">').html(data).find('a[href$="self.close();"]').hide().prev('br').hide().parent().html();
			oPopup_body.html(help_content);
		},
		error: function (xhr, textStatus, errorThrown) {
			oPopup_body.html(textStatus);
		}
	});
	return false;
}

// Create the popup menus for the top level/user menu area.
function smc_PopupMenu(oOptions)
{
	this.opt = (typeof oOptions == 'object') ? oOptions : {};
	this.opt.menus = {};
}

smc_PopupMenu.prototype.add = function (sItem, sUrl)
{
	var $menu = $('#' + sItem + '_menu'), $item = $('#' + sItem + '_menu_top');
	if ($item.length == 0)
		return;

	this.opt.menus[sItem] = {open: false, loaded: false, sUrl: sUrl, itemObj: $item, menuObj: $menu };

	$item.click({obj: this}, function (e) {
		e.preventDefault();

		e.data.obj.toggle(sItem);
	});
}

smc_PopupMenu.prototype.toggle = function (sItem)
{
	if (!!this.opt.menus[sItem].open)
		this.close(sItem);
	else
		this.open(sItem);
}

smc_PopupMenu.prototype.open = function (sItem)
{
	this.closeAll();

	if (!this.opt.menus[sItem].loaded)
	{
		this.opt.menus[sItem].menuObj.html('<div class="loading">' + (typeof(ajax_notification_text) != null ? ajax_notification_text : '') + '</div>');
		this.opt.menus[sItem].menuObj.load(this.opt.menus[sItem].sUrl, function() {
			if ($(this).hasClass('scrollable'))
				$(this).customScrollbar({
					skin: "default-skin",
					hScroll: false,
					updateOnWindowResize: true
				});
		});
		this.opt.menus[sItem].loaded = true;
	}

	this.opt.menus[sItem].menuObj.addClass('visible');
	this.opt.menus[sItem].itemObj.addClass('open');
	this.opt.menus[sItem].open = true;

	// Now set up closing the menu if we click off.
	$(document).on('click.menu', {obj: this}, function(e) {
		if ($(e.target).closest(e.data.obj.opt.menus[sItem].menuObj.parent()).length)
			return;
		e.data.obj.closeAll();
		$(document).off('click.menu');
	});
}

smc_PopupMenu.prototype.close = function (sItem)
{
	this.opt.menus[sItem].menuObj.removeClass('visible');
	this.opt.menus[sItem].itemObj.removeClass('open');
	this.opt.menus[sItem].open = false;
	$(document).off('click.menu');
}

smc_PopupMenu.prototype.closeAll = function ()
{
	for (var prop in this.opt.menus)
		if (!!this.opt.menus[prop].open)
			this.close(prop);
}

// *** smc_Popup class.
function smc_Popup(oOptions)
{
	this.opt = oOptions;
	this.popup_id = this.opt.custom_id ? this.opt.custom_id : 'sbb_popup';
	this.show();
}

smc_Popup.prototype.show = function ()
{
	popup_class = 'popup_window ' + (this.opt.custom_class ? this.opt.custom_class : 'description');
	if (this.opt.icon_class)
		icon = '<span class="' + this.opt.icon_class + '"></span> ';
	else
		icon = this.opt.icon ? '<img src="' + this.opt.icon + '" class="icon" alt=""> ' : '';

	// Create the div that will be shown
	$('body').append('<div id="' + this.popup_id + '" class="popup_container"><div class="' + popup_class + '"><div class="catbg popup_heading"><a href="javascript:void(0);" class="generic_icons hide_popup"></a>' + icon + this.opt.heading + '</div><div class="popup_content">' + this.opt.content + '</div></div></div>');

	// Show it
	this.popup_body = $('#' + this.popup_id).children('.popup_window');
	this.popup_body.parent().fadeIn(300);

	// Trigger hide on escape or mouse click
	var popup_instance = this;
	$(document).mouseup(function (e) {
		if ($('#' + popup_instance.popup_id).has(e.target).length === 0)
			popup_instance.hide();
	}).keyup(function(e){
		if (e.keyCode == 27)
			popup_instance.hide();
	});
	$('#' + this.popup_id).find('.hide_popup').click(function (){ return popup_instance.hide(); });

	return false;
}

smc_Popup.prototype.hide = function ()
{
	$('#' + this.popup_id).fadeOut(300, function(){ $(this).remove(); });

	return false;
}

// Remember the current position.
function storeCaret(oTextHandle)
{
	// Only bother if it will be useful.
	if ('createTextRange' in oTextHandle)
		oTextHandle.caretPos = document.selection.createRange().duplicate();
}

// Replaces the currently selected text with the passed text.
function replaceText(text, oTextHandle)
{
	// Attempt to create a text range (IE).
	if ('caretPos' in oTextHandle && 'createTextRange' in oTextHandle)
	{
		var caretPos = oTextHandle.caretPos;

		caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text + ' ' : text;
		caretPos.select();
	}
	// Mozilla text range replace.
	else if ('selectionStart' in oTextHandle)
	{
		var begin = oTextHandle.value.substr(0, oTextHandle.selectionStart);
		var end = oTextHandle.value.substr(oTextHandle.selectionEnd);
		var scrollPos = oTextHandle.scrollTop;

		oTextHandle.value = begin + text + end;

		if (oTextHandle.setSelectionRange)
		{
			oTextHandle.focus();
			var goForward = is_opera ? text.match(/\n/g).length : 0;
			oTextHandle.setSelectionRange(begin.length + text.length + goForward, begin.length + text.length + goForward);
		}
		oTextHandle.scrollTop = scrollPos;
	}
	// Just put it on the end.
	else
	{
		oTextHandle.value += text;
		oTextHandle.focus(oTextHandle.value.length - 1);
	}
}

// Surrounds the selected text with text1 and text2.
function surroundText(text1, text2, oTextHandle)
{
	// Can a text range be created?
	if ('caretPos' in oTextHandle && 'createTextRange' in oTextHandle)
	{
		var caretPos = oTextHandle.caretPos, temp_length = caretPos.text.length;

		caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text1 + caretPos.text + text2 + ' ' : text1 + caretPos.text + text2;

		if (temp_length == 0)
		{
			caretPos.moveStart('character', -text2.length);
			caretPos.moveEnd('character', -text2.length);
			caretPos.select();
		}
		else
			oTextHandle.focus(caretPos);
	}
	// Mozilla text range wrap.
	else if ('selectionStart' in oTextHandle)
	{
		var begin = oTextHandle.value.substr(0, oTextHandle.selectionStart);
		var selection = oTextHandle.value.substr(oTextHandle.selectionStart, oTextHandle.selectionEnd - oTextHandle.selectionStart);
		var end = oTextHandle.value.substr(oTextHandle.selectionEnd);
		var newCursorPos = oTextHandle.selectionStart;
		var scrollPos = oTextHandle.scrollTop;

		oTextHandle.value = begin + text1 + selection + text2 + end;

		if (oTextHandle.setSelectionRange)
		{
			var goForward = is_opera ? text1.match(/\n/g).length : 0, goForwardAll = is_opera ? (text1 + text2).match(/\n/g).length : 0;
			if (selection.length == 0)
				oTextHandle.setSelectionRange(newCursorPos + text1.length + goForward, newCursorPos + text1.length + goForward);
			else
				oTextHandle.setSelectionRange(newCursorPos, newCursorPos + text1.length + selection.length + text2.length + goForwardAll);
			oTextHandle.focus();
		}
		oTextHandle.scrollTop = scrollPos;
	}
	// Just put them on the end, then.
	else
	{
		oTextHandle.value += text1 + text2;
		oTextHandle.focus(oTextHandle.value.length - 1);
	}
}

// Checks if the passed input's value is nothing.
function isEmptyText(theField)
{
	// Copy the value so changes can be made..
	if (typeof(theField) == 'string')
		var theValue = theField;
	else
		var theValue = theField.value;

	// Strip whitespace off the left side.
	while (theValue.length > 0 && (theValue.charAt(0) == ' ' || theValue.charAt(0) == '\t'))
		theValue = theValue.substring(1, theValue.length);
	// Strip whitespace off the right side.
	while (theValue.length > 0 && (theValue.charAt(theValue.length - 1) == ' ' || theValue.charAt(theValue.length - 1) == '\t'))
		theValue = theValue.substring(0, theValue.length - 1);

	if (theValue == '')
		return true;
	else
		return false;
}

// Only allow form submission ONCE.
function submitonce(theform)
{
	sbb_formSubmitted = true;

	// If there are any editors warn them submit is coming!
	for (var i = 0; i < sbb_editorArray.length; i++)
		sbb_editorArray[i].doSubmit();
}
function submitThisOnce(oControl)
{
	// oControl might also be a form.
	var oForm = 'form' in oControl ? oControl.form : oControl;

	var aTextareas = oForm.getElementsByTagName('textarea');
	for (var i = 0, n = aTextareas.length; i < n; i++)
		aTextareas[i].readOnly = true;

	return !sbb_formSubmitted;
}

// Deprecated, as innerHTML is supported everywhere.
function setInnerHTML(oElement, sToValue)
{
	oElement.innerHTML = sToValue;
}

function getInnerHTML(oElement)
{
	return oElement.innerHTML;
}

// Set the "outer" HTML of an element.
function setOuterHTML(oElement, sToValue)
{
	if ('outerHTML' in oElement)
		oElement.outerHTML = sToValue;
	else
	{
		var range = document.createRange();
		range.setStartBefore(oElement);
		oElement.parentNode.replaceChild(range.createContextualFragment(sToValue), oElement);
	}
}

// Checks for variable in theArray.
function in_array(variable, theArray)
{
	for (var i in theArray)
		if (theArray[i] == variable)
			return true;

	return false;
}

// Checks for variable in theArray.
function array_search(variable, theArray)
{
	for (var i in theArray)
		if (theArray[i] == variable)
			return i;

	return null;
}

// Find a specific radio button in its group and select it.
function selectRadioByName(oRadioGroup, sName)
{
	if (!('length' in oRadioGroup))
		return oRadioGroup.checked = true;

	for (var i = 0, n = oRadioGroup.length; i < n; i++)
		if (oRadioGroup[i].value == sName)
			return oRadioGroup[i].checked = true;

	return false;
}

function selectAllRadio(oInvertCheckbox, oForm, sMask, sValue, bIgnoreDisabled)
{
	for (var i = 0; i < oForm.length; i++)
		if (oForm[i].name != undefined && oForm[i].name.substr(0, sMask.length) == sMask && oForm[i].value == sValue && (!oForm[i].disabled || (typeof(bIgnoreDisabled) == 'boolean' && bIgnoreDisabled)))
			oForm[i].checked = true;
}
function selectAllRadioClass(oForm, sMask, sValue, sClass)
{
	$(oForm).find('input[name^="' + sMask + '"].' + sClass + '[value="' + sValue + '"]').prop('checked', true).trigger('change');
}

// Invert all checkboxes at once by clicking a single checkbox.
function invertAll(oInvertCheckbox, oForm, sMask, bIgnoreDisabled)
{
	for (var i = 0; i < oForm.length; i++)
	{
		if (!('name' in oForm[i]) || (typeof(sMask) == 'string' && oForm[i].name.substr(0, sMask.length) != sMask && oForm[i].id.substr(0, sMask.length) != sMask))
			continue;

		if (!oForm[i].disabled || (typeof(bIgnoreDisabled) == 'boolean' && bIgnoreDisabled))
			oForm[i].checked = oInvertCheckbox.checked;
	}
}

// Keep the session alive - always!
var lastKeepAliveCheck = new Date().getTime();
function sbb_sessionKeepAlive()
{
	var curTime = new Date().getTime();

	// Prevent a Firefox bug from hammering the server.
	if (sbb_scripturl && curTime - lastKeepAliveCheck > 900000)
	{
		var tempImage = new Image();
		tempImage.src = sbb_prepareScriptUrl(sbb_scripturl) + 'action=keepalive;time=' + curTime;
		lastKeepAliveCheck = curTime;
	}

	window.setTimeout('sbb_sessionKeepAlive();', 1200000);
}
window.setTimeout('sbb_sessionKeepAlive();', 1200000);

// Set a theme option through javascript.
function sbb_setThemeOption(theme_var, theme_value, theme_id, theme_cur_session_id, theme_cur_session_var, theme_additional_vars)
{
	// Compatibility.
	if (theme_cur_session_id == null)
		theme_cur_session_id = sbb_session_id;
	if (typeof(theme_cur_session_var) == 'undefined')
		theme_cur_session_var = 'sesc';

	if (theme_additional_vars == null)
		theme_additional_vars = '';

	var tempImage = new Image();
	tempImage.src = sbb_prepareScriptUrl(sbb_scripturl) + 'action=jsoption;var=' + theme_var + ';val=' + theme_value + ';' + theme_cur_session_var + '=' + theme_cur_session_id + theme_additional_vars + (theme_id == null ? '' : '&th=' + theme_id) + ';time=' + (new Date().getTime());
}

// Shows the page numbers by clicking the dots (in compact view).
function expandPages(spanNode, baseLink, firstPage, lastPage, perPage)
{
	var replacement = '', i, oldLastPage = 0;
	var perPageLimit = 50;

	// Prevent too many pages to be loaded at once.
	if ((lastPage - firstPage) / perPage > perPageLimit)
	{
		oldLastPage = lastPage;
		lastPage = firstPage + perPageLimit * perPage;
	}

	// Calculate the new pages.
	for (i = firstPage; i < lastPage; i += perPage)
		replacement += baseLink.replace(/%1\$d/, i).replace(/%2\$s/, 1 + i / perPage).replace(/%%/g, '%');

	// Add the new page links.
	$(spanNode).before(replacement);

	if (oldLastPage)
		// Access the raw DOM element so the native onclick event can be overridden.
		spanNode.onclick = function ()
		{
			expandPages(spanNode, baseLink, lastPage, oldLastPage, perPage);
		};
	else
		$(spanNode).remove();
}

function smc_preCacheImage(sSrc)
{
	if (!('smc_aCachedImages' in window))
		window.smc_aCachedImages = [];

	if (!in_array(sSrc, window.smc_aCachedImages))
	{
		var oImage = new Image();
		oImage.src = sSrc;
	}
}


// *** smc_Cookie class.
function smc_Cookie(oOptions)
{
	this.opt = oOptions;
	this.oCookies = {};
	this.init();
}

smc_Cookie.prototype.init = function()
{
	if ('cookie' in document && document.cookie != '')
	{
		var aCookieList = document.cookie.split(';');
		for (var i = 0, n = aCookieList.length; i < n; i++)
		{
			var aNameValuePair = aCookieList[i].split('=');
			this.oCookies[aNameValuePair[0].replace(/^\s+|\s+$/g, '')] = decodeURIComponent(aNameValuePair[1]);
		}
	}
}

smc_Cookie.prototype.get = function(sKey)
{
	return sKey in this.oCookies ? this.oCookies[sKey] : null;
}

smc_Cookie.prototype.set = function(sKey, sValue)
{
	document.cookie = sKey + '=' + encodeURIComponent(sValue);
}


// *** smc_Toggle class.
function smc_Toggle(oOptions)
{
	this.opt = oOptions;
	this.bCollapsed = false;
	this.oCookie = null;
	this.init();
}

smc_Toggle.prototype.init = function ()
{
	// The master switch can disable this toggle fully.
	if ('bToggleEnabled' in this.opt && !this.opt.bToggleEnabled)
		return;

	// If cookies are enabled and they were set, override the initial state.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
	{
		// Initialize the cookie handler.
		this.oCookie = new smc_Cookie({});

		// Check if the cookie is set.
		var cookieValue = this.oCookie.get(this.opt.oCookieOptions.sCookieName)
		if (cookieValue != null)
			this.opt.bCurrentlyCollapsed = cookieValue == '1';
	}

	// Initialize the images to be clickable.
	if ('aSwapImages' in this.opt)
	{
		for (var i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			this.opt.aSwapImages[i].isCSS = (typeof this.opt.aSwapImages[i].srcCollapsed == 'undefined');
			if (this.opt.aSwapImages[i].isCSS)
			{
				if (!this.opt.aSwapImages[i].cssCollapsed)
					this.opt.aSwapImages[i].cssCollapsed = 'toggle_down';
				if (!this.opt.aSwapImages[i].cssExpanded)
					this.opt.aSwapImages[i].cssExpanded = 'toggle_up';
			}
			else
			{
				// Preload the collapsed image.
				smc_preCacheImage(this.opt.aSwapImages[i].srcCollapsed);
			}

			// Display the image in case it was hidden.
			$('#' + this.opt.aSwapImages[i].sId).show();
			var oImage = document.getElementById(this.opt.aSwapImages[i].sId);
			if (typeof(oImage) == 'object' && oImage != null)
			{
				oImage.instanceRef = this;
				oImage.onclick = function () {
					this.instanceRef.toggle();
					this.blur();
				}
				oImage.style.cursor = 'pointer';
			}
		}
	}

	// Initialize links.
	if ('aSwapLinks' in this.opt)
	{
		for (var i = 0, n = this.opt.aSwapLinks.length; i < n; i++)
		{
			var oLink = document.getElementById(this.opt.aSwapLinks[i].sId);
			if (typeof(oLink) == 'object' && oLink != null)
			{
				// Display the link in case it was hidden.
				if (oLink.style.display == 'none')
					oLink.style.display = '';

				oLink.instanceRef = this;
				oLink.onclick = function () {
					this.instanceRef.toggle();
					this.blur();
					return false;
				}
			}
		}
	}

	// If the init state is set to be collapsed, collapse it.
	if (this.opt.bCurrentlyCollapsed)
		this.changeState(true, true);
}

// Collapse or expand the section.
smc_Toggle.prototype.changeState = function(bCollapse, bInit)
{
	// Default bInit to false.
	bInit = typeof(bInit) == 'undefined' ? false : true;

	// Handle custom function hook before collapse.
	if (!bInit && bCollapse && 'funcOnBeforeCollapse' in this.opt)
	{
		this.tmpMethod = this.opt.funcOnBeforeCollapse;
		this.tmpMethod();
		delete this.tmpMethod;
	}

	// Handle custom function hook before expand.
	else if (!bInit && !bCollapse && 'funcOnBeforeExpand' in this.opt)
	{
		this.tmpMethod = this.opt.funcOnBeforeExpand;
		this.tmpMethod();
		delete this.tmpMethod;
	}

	// Loop through all the images that need to be toggled.
	if ('aSwapImages' in this.opt)
	{
		for (var i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			if (this.opt.aSwapImages[i].isCSS)
			{
				$('#' + this.opt.aSwapImages[i].sId).toggleClass(this.opt.aSwapImages[i].cssCollapsed, bCollapse).toggleClass(this.opt.aSwapImages[i].cssExpanded, !bCollapse).attr('title', bCollapse ? this.opt.aSwapImages[i].altCollapsed : this.opt.aSwapImages[i].altExpanded);
			}
			else
			{
				var oImage = document.getElementById(this.opt.aSwapImages[i].sId);
				if (typeof(oImage) == 'object' && oImage != null)
				{
					// Only (re)load the image if it's changed.
					var sTargetSource = bCollapse ? this.opt.aSwapImages[i].srcCollapsed : this.opt.aSwapImages[i].srcExpanded;
					if (oImage.src != sTargetSource)
						oImage.src = sTargetSource;

					oImage.alt = oImage.title = bCollapse ? this.opt.aSwapImages[i].altCollapsed : this.opt.aSwapImages[i].altExpanded;
				}
			}
		}
	}

	// Loop through all the links that need to be toggled.
	if ('aSwapLinks' in this.opt)
	{
		for (var i = 0, n = this.opt.aSwapLinks.length; i < n; i++)
		{
			var oLink = document.getElementById(this.opt.aSwapLinks[i].sId);
			if (typeof(oLink) == 'object' && oLink != null)
				setInnerHTML(oLink, bCollapse ? this.opt.aSwapLinks[i].msgCollapsed : this.opt.aSwapLinks[i].msgExpanded);
		}
	}

	// Now go through all the sections to be collapsed.
	for (var i = 0, n = this.opt.aSwappableContainers.length; i < n; i++)
	{
		if (this.opt.aSwappableContainers[i] == null)
			continue;

		var oContainer = document.getElementById(this.opt.aSwappableContainers[i]);
		if (typeof(oContainer) == 'object' && oContainer != null)
		{
			if (!!this.opt.bNoAnimate || bInit)
			{
				$(oContainer).toggle(!bCollapse);
			}
			else
			{
				if (bCollapse)
				{
					if (this.opt.aHeader != null && this.opt.aHeader.hasClass('cat_bar'))
						$(this.opt.aHeader).addClass('collapsed');
					$(oContainer).slideUp();
				}
				else
				{
					if (this.opt.aHeader != null && this.opt.aHeader.hasClass('cat_bar'))
						$(this.opt.aHeader).removeClass('collapsed');
					$(oContainer).slideDown();
				}
			}
		}
	}

	// Update the new state.
	this.bCollapsed = bCollapse;

	// Update the cookie, if desired.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
		this.oCookie.set(this.opt.oCookieOptions.sCookieName, this.bCollapsed | 0);

	if (!bInit && 'oThemeOptions' in this.opt && this.opt.oThemeOptions.bUseThemeSettings)
		sbb_setThemeOption(this.opt.oThemeOptions.sOptionName, this.bCollapsed | 0, 'sThemeId' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sThemeId : null, sbb_session_id, sbb_session_var, 'sAdditionalVars' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sAdditionalVars : null);
}

smc_Toggle.prototype.toggle = function()
{
	// Change the state by reversing the current state.
	this.changeState(!this.bCollapsed);
}


function ajax_indicator(turn_on)
{
	if (ajax_indicator_ele == null)
	{
		ajax_indicator_ele = document.getElementById('ajax_in_progress');

		if (ajax_indicator_ele == null && typeof(ajax_notification_text) != null)
		{
			create_ajax_indicator_ele();
		}
	}

	if (ajax_indicator_ele != null)
	{
		ajax_indicator_ele.style.display = turn_on ? 'block' : 'none';
	}
}

function create_ajax_indicator_ele()
{
	// Create the div for the indicator.
	ajax_indicator_ele = document.createElement('div');

	// Set the id so it'll load the style properly.
	ajax_indicator_ele.id = 'ajax_in_progress';

	// Set the text.  (Note:  You MUST append here and not overwrite.)
	ajax_indicator_ele.innerHTML += ajax_notification_text;

	// Finally attach the element to the body.
	document.body.appendChild(ajax_indicator_ele);
}

function createEventListener(oTarget)
{
	if (!('addEventListener' in oTarget))
	{
		if (oTarget.attachEvent)
		{
			oTarget.addEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget.attachEvent('on' + sEvent, funcHandler);
			}
			oTarget.removeEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget.detachEvent('on' + sEvent, funcHandler);
			}
		}
		else
		{
			oTarget.addEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget['on' + sEvent] = funcHandler;
			}
			oTarget.removeEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget['on' + sEvent] = null;
			}
		}
	}
}

// This function will retrieve the contents needed for the jump to boxes.
function grabJumpToContent(elem)
{
	var oXMLDoc = getXMLDocument(sbb_prepareScriptUrl(sbb_scripturl) + 'action=xmlhttp;sa=jumpto;xml');
	var aBoardsAndCategories = [];

	ajax_indicator(true);

	oXMLDoc.done(function(data, textStatus, jqXHR){

		var items = $(data).find('item');
			items.each(function(i) {
			aBoardsAndCategories[i] = {
				id: parseInt($(this).attr('id')),
				isCategory: $(this).attr('type') == 'category',
				name: this.firstChild.nodeValue.removeEntities(),
				is_current: false,
				childLevel: parseInt($(this).attr('childlevel'))
			}
		});

		ajax_indicator(false);

		for (var i = 0, n = aJumpTo.length; i < n; i++)
		{
			aJumpTo[i].fillSelect(aBoardsAndCategories);
		}
	});
}

// This'll contain all JumpTo objects on the page.
var aJumpTo = new Array();

// *** JumpTo class.
function JumpTo(oJumpToOptions)
{
	this.opt = oJumpToOptions;
	this.dropdownList = null;
	this.showSelect();

	// Register a change event after the select has been created.
	$('#' + this.opt.sContainerId).one('mouseenter', function() {
		grabJumpToContent(this);
	});
}

// Show the initial select box (onload). Method of the JumpTo class.
JumpTo.prototype.showSelect = function ()
{
	var sChildLevelPrefix = '';
	for (var i = this.opt.iCurBoardChildLevel; i > 0; i--)
		sChildLevelPrefix += this.opt.sBoardChildLevelIndicator;
	setInnerHTML(document.getElementById(this.opt.sContainerId), this.opt.sJumpToTemplate.replace(/%select_id%/, this.opt.sContainerId + '_select').replace(/%dropdown_list%/, '<select ' + (this.opt.bDisabled == true ? 'disabled ' : '') + (this.opt.sClassName != undefined ? 'class="' + this.opt.sClassName + '" ' : '') + 'name="' + (this.opt.sCustomName != undefined ? this.opt.sCustomName : this.opt.sContainerId + '_select') + '" id="' + this.opt.sContainerId + '_select"><option value="' + (this.opt.bNoRedirect != undefined && this.opt.bNoRedirect == true ? this.opt.iCurBoardId : '?board=' + this.opt.iCurBoardId + '.0') + '">' + sChildLevelPrefix + this.opt.sBoardPrefix + this.opt.sCurBoardName.removeEntities() + '</option></select>&nbsp;' + (this.opt.sGoButtonLabel != undefined ? '<input type="button" class="button_submit" value="' + this.opt.sGoButtonLabel + '" onclick="window.location.href = \'' + sbb_prepareScriptUrl(sbb_scripturl) + 'board=' + this.opt.iCurBoardId + '.0\';">' : '')));
	this.dropdownList = document.getElementById(this.opt.sContainerId + '_select');
}

// Fill the jump to box with entries. Method of the JumpTo class.
JumpTo.prototype.fillSelect = function (aBoardsAndCategories)
{
	var iIndexPointer = 0;

	// Create an option that'll be above and below the category.
	var oDashOption = document.createElement('option');
	oDashOption.appendChild(document.createTextNode(this.opt.sCatSeparator));
	oDashOption.disabled = 'disabled';
	oDashOption.value = '';

	if ('onbeforeactivate' in document)
		this.dropdownList.onbeforeactivate = null;
	else
		this.dropdownList.onfocus = null;

	if (this.opt.bNoRedirect)
		this.dropdownList.options[0].disabled = 'disabled';

	// Create a document fragment that'll allowing inserting big parts at once.
	var oListFragment = document.createDocumentFragment();

	// Loop through all items to be added.
	for (var i = 0, n = aBoardsAndCategories.length; i < n; i++)
	{
		var j, sChildLevelPrefix, oOption;

		// If we've reached the currently selected board add all items so far.
		if (!aBoardsAndCategories[i].isCategory && aBoardsAndCategories[i].id == this.opt.iCurBoardId)
		{
				this.dropdownList.insertBefore(oListFragment, this.dropdownList.options[0]);
				oListFragment = document.createDocumentFragment();
				continue;
		}

		if (aBoardsAndCategories[i].isCategory)
			oListFragment.appendChild(oDashOption.cloneNode(true));
		else
			for (j = aBoardsAndCategories[i].childLevel, sChildLevelPrefix = ''; j > 0; j--)
				sChildLevelPrefix += this.opt.sBoardChildLevelIndicator;

		oOption = document.createElement('option');
		oOption.appendChild(document.createTextNode((aBoardsAndCategories[i].isCategory ? this.opt.sCatPrefix : sChildLevelPrefix + this.opt.sBoardPrefix) + aBoardsAndCategories[i].name));
		if (!this.opt.bNoRedirect)
			oOption.value = aBoardsAndCategories[i].isCategory ? '#c' + aBoardsAndCategories[i].id : '?board=' + aBoardsAndCategories[i].id + '.0';
		else
		{
			if (aBoardsAndCategories[i].isCategory)
				oOption.disabled = 'disabled';
			else
				oOption.value = aBoardsAndCategories[i].id;
		}
		oListFragment.appendChild(oOption);

		if (aBoardsAndCategories[i].isCategory)
			oListFragment.appendChild(oDashOption.cloneNode(true));
	}

	// Add the remaining items after the currently selected item.
	this.dropdownList.appendChild(oListFragment);

	// Add an onchange action
	if (!this.opt.bNoRedirect)
		this.dropdownList.onchange = function() {
			if (this.selectedIndex > 0 && this.options[this.selectedIndex].value)
				window.location.href = sbb_scripturl + this.options[this.selectedIndex].value.substr(sbb_scripturl.indexOf('?') == -1 || this.options[this.selectedIndex].value.substr(0, 1) != '?' ? 0 : 1);
		}
}

// A global array containing all IconList objects.
var aIconLists = new Array();

// *** IconList object.
function IconList(oOptions)
{
	if (!window.XMLHttpRequest)
		return;

	this.opt = oOptions;
	this.bListLoaded = false;
	this.oContainerDiv = null;
	this.funcMousedownHandler = null;
	this.funcParent = this;
	this.iCurMessageId = 0;
	this.iCurTimeout = 0;

	// Add backwards compatibility with old themes.
	if (!('sSessionVar' in this.opt))
		this.opt.sSessionVar = 'sesc';

	this.initIcons();
}

// Replace all message icons by icons with hoverable and clickable div's.
IconList.prototype.initIcons = function ()
{
	for (var i = document.images.length - 1, iPrefixLength = this.opt.sIconIdPrefix.length; i >= 0; i--)
		if (document.images[i].id.substr(0, iPrefixLength) == this.opt.sIconIdPrefix)
			setOuterHTML(document.images[i], '<div title="' + this.opt.sLabelIconList + '" onclick="' + this.opt.sBackReference + '.openPopup(this, ' + document.images[i].id.substr(iPrefixLength) + ')" onmouseover="' + this.opt.sBackReference + '.onBoxHover(this, true)" onmouseout="' + this.opt.sBackReference + '.onBoxHover(this, false)" style="background: ' + this.opt.sBoxBackground + '; cursor: pointer; padding: 3px; text-align: center;"><img src="' + document.images[i].src + '" alt="' + document.images[i].alt + '" id="' + document.images[i].id + '"></div>');
}

// Event for the mouse hovering over the original icon.
IconList.prototype.onBoxHover = function (oDiv, bMouseOver)
{
	oDiv.style.border = bMouseOver ? this.opt.iBoxBorderWidthHover + 'px solid ' + this.opt.sBoxBorderColorHover : '';
	oDiv.style.background = bMouseOver ? this.opt.sBoxBackgroundHover : this.opt.sBoxBackground;
	oDiv.style.padding = bMouseOver ? (3 - this.opt.iBoxBorderWidthHover) + 'px' : '3px'
}

// Show the list of icons after the user clicked the original icon.
IconList.prototype.openPopup = function (oDiv, iMessageId)
{
	this.iCurMessageId = iMessageId;

	if (!this.bListLoaded && this.oContainerDiv == null)
	{
		// Create a container div.
		this.oContainerDiv = document.createElement('div');
		this.oContainerDiv.id = 'iconList';
		this.oContainerDiv.style.display = 'none';
		this.oContainerDiv.style.cursor = 'pointer';
		this.oContainerDiv.style.position = 'absolute';
		this.oContainerDiv.style.background = this.opt.sContainerBackground;
		this.oContainerDiv.style.border = this.opt.sContainerBorder;
		this.oContainerDiv.style.padding = '6px 0px';
		document.body.appendChild(this.oContainerDiv);

		// Start to fetch its contents.
		ajax_indicator(true);
		sendXMLDocument.call(this, sbb_prepareScriptUrl(sbb_scripturl) + 'action=xmlhttp;sa=messageicons;board=' + this.opt.iBoardId + ';xml', '', this.onIconsReceived);

		createEventListener(document.body);
	}

	// Set the position of the container.
	var aPos = sbb_itemPos(oDiv);

	this.oContainerDiv.style.top = (aPos[1] + oDiv.offsetHeight) + 'px';
	this.oContainerDiv.style.left = (aPos[0] - 1) + 'px';
	this.oClickedIcon = oDiv;

	if (this.bListLoaded)
		this.oContainerDiv.style.display = 'block';

	document.body.addEventListener('mousedown', this.onWindowMouseDown, false);
}

// Setup the list of icons once it is received through xmlHTTP.
IconList.prototype.onIconsReceived = function (oXMLDoc)
{
	var icons = oXMLDoc.getElementsByTagName('storybb')[0].getElementsByTagName('icon');
	var sItems = '';

	for (var i = 0, n = icons.length; i < n; i++)
		sItems += '<span onmouseover="' + this.opt.sBackReference + '.onItemHover(this, true)" onmouseout="' + this.opt.sBackReference + '.onItemHover(this, false);" onmousedown="' + this.opt.sBackReference + '.onItemMouseDown(this, \'' + icons[i].getAttribute('value') + '\');" style="padding: 2px 3px; line-height: 20px; border: ' + this.opt.sItemBorder + '; background: ' + this.opt.sItemBackground + '"><img src="' + icons[i].getAttribute('url') + '" alt="' + icons[i].getAttribute('name') + '" title="' + icons[i].firstChild.nodeValue + '" style="vertical-align: middle"></span>';

	setInnerHTML(this.oContainerDiv, sItems);
	this.oContainerDiv.style.display = 'block';
	this.bListLoaded = true;

	if (is_ie)
		this.oContainerDiv.style.width = this.oContainerDiv.clientWidth + 'px';

	ajax_indicator(false);
}

// Event handler for hovering over the icons.
IconList.prototype.onItemHover = function (oDiv, bMouseOver)
{
	oDiv.style.background = bMouseOver ? this.opt.sItemBackgroundHover : this.opt.sItemBackground;
	oDiv.style.border = bMouseOver ? this.opt.sItemBorderHover : this.opt.sItemBorder;
	if (this.iCurTimeout != 0)
		window.clearTimeout(this.iCurTimeout);
	if (bMouseOver)
		this.onBoxHover(this.oClickedIcon, true);
	else
		this.iCurTimeout = window.setTimeout(this.opt.sBackReference + '.collapseList();', 500);
}

// Event handler for clicking on one of the icons.
IconList.prototype.onItemMouseDown = function (oDiv, sNewIcon)
{
	if (this.iCurMessageId != 0)
	{
		ajax_indicator(true);
		this.tmpMethod = getXMLDocument;
		var oXMLDoc = this.tmpMethod(sbb_prepareScriptUrl(sbb_scripturl) + 'action=jsmodify;topic=' + this.opt.iTopicId + ';msg=' + this.iCurMessageId + ';' + sbb_session_var + '=' + sbb_session_id + ';icon=' + sNewIcon + ';xml'),
		oThis = this;
		delete this.tmpMethod;
		ajax_indicator(false);

		oXMLDoc.done(function(data, textStatus, jqXHR){
			oMessage = $(data).find('message')
			curMessageId = oMessage.attr('id').replace( /^\D+/g, '');

			if (oMessage.find('error').length == 0)
			{
				if (oThis.opt.bShowModify && oMessage.find('modified').length != 0)
					$('#modified_' + curMessageId).html(oMessage.find('modified').text());

				oThis.oClickedIcon.getElementsByTagName('img')[0].src = oDiv.getElementsByTagName('img')[0].src;
			}
		});
	}
}

// Event handler for clicking outside the list (will make the list disappear).
IconList.prototype.onWindowMouseDown = function ()
{
	for (var i = aIconLists.length - 1; i >= 0; i--)
	{
		aIconLists[i].funcParent.tmpMethod = aIconLists[i].collapseList;
		aIconLists[i].funcParent.tmpMethod();
		delete aIconLists[i].funcParent.tmpMethod;
	}
}

// Collapse the list of icons.
IconList.prototype.collapseList = function()
{
	this.onBoxHover(this.oClickedIcon, false);
	this.oContainerDiv.style.display = 'none';
	this.iCurMessageId = 0;
	document.body.removeEventListener('mousedown', this.onWindowMouseDown, false);
}

// Handy shortcuts for getting the mouse position on the screen - only used for IE at the moment.
function sbb_mousePose(oEvent)
{
	var x = 0;
	var y = 0;

	if (oEvent.pageX)
	{
		y = oEvent.pageY;
		x = oEvent.pageX;
	}
	else if (oEvent.clientX)
	{
		x = oEvent.clientX + (document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft);
		y = oEvent.clientY + (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop);
	}

	return [x, y];
}

// Short function for finding the actual position of an item.
function sbb_itemPos(itemHandle)
{
	var itemX = 0;
	var itemY = 0;

	if ('offsetParent' in itemHandle)
	{
		itemX = itemHandle.offsetLeft;
		itemY = itemHandle.offsetTop;
		while (itemHandle.offsetParent && typeof(itemHandle.offsetParent) == 'object')
		{
			itemHandle = itemHandle.offsetParent;
			itemX += itemHandle.offsetLeft;
			itemY += itemHandle.offsetTop;
		}
	}
	else if ('x' in itemHandle)
	{
		itemX = itemHandle.x;
		itemY = itemHandle.y;
	}

	return [itemX, itemY];
}

// This function takes the script URL and prepares it to allow the query string to be appended to it.
function sbb_prepareScriptUrl(sUrl)
{
	return sUrl.indexOf('?') == -1 ? sUrl + '?' : sUrl + (sUrl.charAt(sUrl.length - 1) == '?' || sUrl.charAt(sUrl.length - 1) == '&' || sUrl.charAt(sUrl.length - 1) == ';' ? '' : ';');
}

function addLoadEvent(fNewOnload)
{
	$(document).ready(fNewOnload);
}

// Get the text in a code tag.
function sbbSelectText(oCurElement, bActOnElement)
{
	// The place we're looking for is one div up, and next door - if it's auto detect.
	if (typeof(bActOnElement) == 'boolean' && bActOnElement)
		var oCodeArea = document.getElementById(oCurElement);
	else
		var oCodeArea = oCurElement.parentNode.nextSibling;

	if (typeof(oCodeArea) != 'object' || oCodeArea == null)
		return false;

	// Start off with my favourite, internet explorer.
	if ('createTextRange' in document.body)
	{
		var oCurRange = document.body.createTextRange();
		oCurRange.moveToElementText(oCodeArea);
		oCurRange.select();
	}
	// Firefox at el.
	else if (window.getSelection)
	{
		var oCurSelection = window.getSelection();
		// Safari is special!
		if (oCurSelection.setBaseAndExtent)
		{
			var oLastChild = oCodeArea.lastChild;
			oCurSelection.setBaseAndExtent(oCodeArea, 0, oLastChild, 'innerText' in oLastChild ? oLastChild.innerText.length : oLastChild.textContent.length);
		}
		else
		{
			var curRange = document.createRange();
			curRange.selectNodeContents(oCodeArea);

			oCurSelection.removeAllRanges();
			oCurSelection.addRange(curRange);
		}
	}

	return false;
}

// A function used to clean the attachments on post page
function cleanFileInput(idElement)
{
	// Simpler solutions work in Opera, IE, Safari and Chrome.
	if (is_opera || is_ie || is_safari || is_chrome)
	{
		document.getElementById(idElement).outerHTML = document.getElementById(idElement).outerHTML;
	}
	// What else can we do? By the way, this doesn't work in Chrome and Mac's Safari.
	else
	{
		document.getElementById(idElement).type = 'input';
		document.getElementById(idElement).type = 'file';
	}
}

function applyWindowClasses(oList)
{
	var bAlternate = false;
	oListItems = oList.getElementsByTagName("LI");
	for (i = 0; i < oListItems.length; i++)
	{
		// Skip dummies.
		if (oListItems[i].id == "")
			continue;
		oListItems[i].className = "windowbg" + (bAlternate ? "2" : "");
		bAlternate = !bAlternate;
	}
}

function reActivate()
{
	document.forms.postmodify.message.readOnly = false;
}

// The actual message icon selector.
function showimage()
{
	document.images.icons.src = icon_urls[document.forms.postmodify.icon.options[document.forms.postmodify.icon.selectedIndex].value];
}

function pollOptions()
{
	var expire_time = document.getElementById('poll_expire');

	if (isEmptyText(expire_time) || expire_time.value == 0)
	{
		document.forms.postmodify.poll_hide[2].disabled = true;
		if (document.forms.postmodify.poll_hide[2].checked)
			document.forms.postmodify.poll_hide[1].checked = true;
	}
	else
		document.forms.postmodify.poll_hide[2].disabled = false;
}

function generateDays(offset)
{
	// Work around JavaScript's lack of support for default values...
	offset = typeof(offset) != 'undefined' ? offset : '';

	var days = 0, selected = 0;
	var dayElement = document.getElementById("day" + offset), yearElement = document.getElementById("year" + offset), monthElement = document.getElementById("month" + offset);

	var monthLength = [
		31, 28, 31, 30,
		31, 30, 31, 31,
		30, 31, 30, 31
	];
	if (yearElement.options[yearElement.selectedIndex].value % 4 == 0)
		monthLength[1] = 29;

	selected = dayElement.selectedIndex;
	while (dayElement.options.length)
		dayElement.options[0] = null;

	days = monthLength[monthElement.value - 1];

	for (i = 1; i <= days; i++)
		dayElement.options[dayElement.length] = new Option(i, i);

	if (selected < days)
		dayElement.selectedIndex = selected;
}

function toggleLinked(form)
{
	form.board.disabled = !form.link_to_board.checked;
}

function initSearch()
{
	if (document.forms.searchform.search.value.indexOf("%u") != -1)
		document.forms.searchform.search.value = unescape(document.forms.searchform.search.value);
}

function selectBoards(ids, aFormID)
{
	var toggle = true;
	var aForm = document.getElementById(aFormID);

	for (i = 0; i < ids.length; i++)
		toggle = toggle & aForm["brd" + ids[i]].checked;

	for (i = 0; i < ids.length; i++)
		aForm["brd" + ids[i]].checked = !toggle;
}

function updateRuleDef(optNum)
{
	if (document.getElementById("ruletype" + optNum).value == "gid")
	{
		document.getElementById("defdiv" + optNum).style.display = "none";
		document.getElementById("defseldiv" + optNum).style.display = "";
	}
	else if (document.getElementById("ruletype" + optNum).value == "bud" || document.getElementById("ruletype" + optNum).value == "")
	{
		document.getElementById("defdiv" + optNum).style.display = "none";
		document.getElementById("defseldiv" + optNum).style.display = "none";
	}
	else
	{
		document.getElementById("defdiv" + optNum).style.display = "";
		document.getElementById("defseldiv" + optNum).style.display = "none";
	}
}

function updateActionDef(optNum)
{
	if (document.getElementById("acttype" + optNum).value == "lab")
	{
		document.getElementById("labdiv" + optNum).style.display = "";
	}
	else
	{
		document.getElementById("labdiv" + optNum).style.display = "none";
	}
}

function smc_resize(selector)
{

	var allElements = [];

	$(selector).each(function(){

		$thisElement = $(this);

		// Get rid of the width and height attributes.
		$thisElement.removeAttr('width').removeAttr('height');

		// Get the default vars.
		$thisElement.basedElement = $thisElement.parent();
		$thisElement.defaultWidth = $thisElement.width();
		$thisElement.defaultHeight = $thisElement.height();
		$thisElement.aspectRatio = $thisElement.defaultHeight / $thisElement.defaultWidth;

		allElements.push($thisElement);

	});

	$(window).resize(function(){

		$(allElements).each(function(){

			_innerElement = this;

			// Get the new width and height.
			var newWidth = _innerElement.basedElement.width();
			var newHeight = (newWidth * _innerElement.aspectRatio) <= _innerElement.defaultHeight ? (newWidth * _innerElement.aspectRatio) : _innerElement.defaultHeight;

			// If the new width is lower than the "default width" then apply some resizing. No? then go back to our default sizes
			var applyResize = (newWidth <= _innerElement.defaultWidth),
				applyWidth = !applyResize ? _innerElement.defaultWidth : newWidth,
				applyHeight = !applyResize ? _innerElement.defaultHeight : newHeight;

			// Gotta check the applied width and height is actually something!
			if (applyWidth <= 0 && applyHeight <= 0) {
				applyWidth = _innerElement.defaultWidth;
				applyHeight = _innerElement.defaultHeight;
			}

			// Finally resize the element!
			_innerElement.width(applyWidth).height(applyHeight);
		});

	// Kick off one resize to fix all elements on page load.
	}).resize();
}

$(function()
{
	$('.buttonlist > .dropmenu').each(function(index, item)
	{
		$(item).prev().click(function(e)
		{
			e.stopPropagation();
			e.preventDefault();

			if ($(item).is(':visible'))
			{
				$(item).css('display', 'none');

				return true;
			}

			$(item).css('display', 'block');
			$(item).css('top', $(this).offset().top + $(this).height());
			$(item).css('left', Math.max($(this).offset().left - $(item).width() + $(this).outerWidth(), 0));
			$(item).height($(item).find('div:first').height());
		});
		$(document).click(function()
		{
			$(item).css('display', 'none');
		});
	});

	// Generic confirmation message.
	$(document).on('click', '.you_sure', function()
	{
		var custom_message = $(this).attr('data-confirm');

		return confirm(custom_message ? custom_message.replace(/-n-/g, "\n") : sbb_you_sure);
	});

	// Generic event for sbbSelectText()
	$('.sbb_select_text').on('click', function(e) {

		e.preventDefault();

		// Do you want to target yourself?
		var actOnElement = $(this).attr('data-actonelement');

		return typeof actOnElement !== "undefined" ? sbbSelectText(actOnElement, true) : sbbSelectText(this);
	});
});