/**
 * Plumrocket Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the End-user License Agreement
 * that is available through the world-wide-web at this URL:
 * http://wiki.plumrocket.net/wiki/EULA
 * If you are unable to obtain it through the world-wide-web, please
 * send an email to support@plumrocket.com so we can send you a copy immediately.
 *
 * @package     Plumrocket_SocialLogin
 * @copyright   Copyright (c) 2018 Plumrocket Inc. (http://www.plumrocket.com)
 * @license     http://wiki.plumrocket.net/wiki/EULA  End-user License Agreement
 */

var psloginLoginPath = null;
var customerLogoutPath = null;
var showFullButtonsAfterViewMore = false;
var personaCurrentEmail = false;

pjQuery_1_12_4(document).ready(function() {

	// Show/Hide button.
	pjQuery_1_12_4('.pslogin-showmore-button').on('click', function() {
		var $buttons = pjQuery_1_12_4(this).parents('div.pslogin-buttons');
		$buttons.find('.pslogin-hidden').fadeToggle(275);
		pjQuery_1_12_4(this).parents('div.pslogin-showmore').hide();

		if(showFullButtonsAfterViewMore != true) {
			$buttons.removeClass('pslogin-buttons-showfull');
			$buttons.parents('div.pslogin-block.pslogin-login').find('.pslogin-title').show();
		}
	});

	// Share.
	pjQuery_1_12_4('.prpop-close-btn').on('click', function() {
		pjQuery_1_12_4('.pslogin-addedoverlay, .pslogin-pop-up-form').hide();
		pjQuery_1_12_4('html').css('overflow', 'auto');
		return false;
	});

	if(pjQuery_1_12_4('.pslogin-pop-up-form').is(':visible')) {
		pjQuery_1_12_4('html').css('overflow', 'hidden');
	}

	// Fake email message.
	pjQuery_1_12_4('.pslogin-fake-email-message .close-message').on('click', function() {
		pjQuery_1_12_4(this).parent().hide();
	});

});


function psLogin(href,width,height)
{
	var win = null;
	if (!width) {
		width = 650;
	}

	if(!height) {
		height = 350;
	}

    var left = parseInt((pjQuery_1_12_4(window).width() - width) / 2);
    var top = parseInt((pjQuery_1_12_4(window).height() - height) / 2);

    var params = [
    	'resizable=yes',
		'scrollbars=no',
		'toolbar=no',
		'menubar=no',
		'location=no',
		'directories=no',
		'status=yes',
		'width='+ width,
		'height='+ height,
		'left='+ left,
		'top='+ top
	];

	if(win) {
		win.close();
	}
	// win = window.open('#', 'pslogin_popup', params.join(','));
	if(href) {
		win = window.open(href, 'pslogin_popup', params.join(','));
		win.focus();

		pjQuery_1_12_4(win.document).ready(function() {

			var loaderText = 'Loading...';
			var html = '<!DOCTYPE html><html style="height: 100%;"><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>'+ loaderText +'</title></head>';
			html += '<body style="height: 100%; margin: 0; padding: 0;">';
			//html += '<div style="text-align: center; height: 100%;"><img src="'+ $link.data('loader') +'" alt="Please Wait" class="loader" style="top: 50%; position: relative; margin-top: -64px; display: none;" /></div>';
			html += '<div style="text-align: center; height: 100%;"><div id="loader" style="top: 50%; position: relative; margin-top: -50px; color: #646464; height:25px; font-size: 25px; text-align: center; font-family: Verdana, Geneva, Arial, Helvetica, sans-serif;">'+ loaderText +'</div></div>';
			html += '</body></html>';

			pjQuery_1_12_4(win.document).contents().html(html);
		});

	}else{
		alert('The Login Application was not configured correctly. If your are the admin of store: Please activate “Enable Logging” in Magento Login Extension and try again to see error details.","The Login Application was not configured correctly. If your are the admin of store: Please activate “Enable Logging” in Magento Login Extension and try again to see error details.');
	}
	return false;
}

function customerEditFakeEmail() {
	pjQuery_1_12_4("#email").removeClass("validation-passed").addClass("validation-failed").after('<div class="validation-advice pslogin-fake-email" id="advice-required-entry-email">Please enter valid email address.</div>');
	pjQuery_1_12_4("#current_password").parents('li:first').hide();
	pjQuery_1_12_4("#change_password").trigger('click change').parents('li:first').hide();
	pjQuery_1_12_4('#password').parents('.fieldset').show();

    var reset = true;
    pjQuery_1_12_4("#email").on('click focus', function() {
    	if(reset) {
    		pjQuery_1_12_4(this).removeClass("validation-failed").addClass("validation-passed").val('');
    		pjQuery_1_12_4('#advice-required-entry-email.pslogin-fake-email').remove();
    	}
    	reset = false;
    });
}
