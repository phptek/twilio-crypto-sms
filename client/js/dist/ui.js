/**
 * Simple jQuery routines to pass some feedback to users as they wait for
 * Bitcoin transactions to confirm. This would be improved using Vue or React.
 */

// The frequency at which we will hit our own endpoint
var interval = 10000;
var isStopped = false;

(function($) {
    $(function() {
        $('#Form_TwilioSMSForm input, #Form_TwilioSMSForm textarea').blur(function(e) {
            var body = $('[name="Body"]').val();
            var phone = $('[name="PhoneTo"]').val();
            var address = $('[name="Address"]').val();
            var amount = $('[name="Amount"]').val();
            var endpointConf = $(this).closest('form').data('uri-confirmation');
            var endpointThanks = $(this).closest('form').data('uri-thanks');
            
            if (!(body.length && phone.length)) {
                return;
            }
            
            // Repeatedly hit the endpoint until such time as we redirect.
            // This is pretty messy but it works.
            setInterval(function() {
                // Prevent repeated non-200 responses from controller endpoint
                if (isStopped) {
                    uiError('Upstream error. Stopping');
                    return;
                }
                
                $.ajax({
                    'type': 'POST',
                    'dataType': 'json',
                    'data': {
                        'Body': body,
                        'PhoneTo': phone,
                        'Address': address,
                        'Amount': amount
                    },
                    'url': endpointConf
                })
                // Exceptions from API clients result in non 200 HTTP codes
                .fail(function (jqXHR, textStatus, errorThrown) {
                    console.log('Request failure (' + textStatus + ')');
                    isStopped = true;
                })
                .done(function(data, textStatus, jqXHR) {
                    var isUnconfirmed = (data === 0);
                    var isConfirmed = (data === 1);
                    var message = (isConfirmed ? 'Confirmed' : 'Unconfirmed');

                    // Redirect as soon as a positive result comes back
                    if (isConfirmed) {
                        return location.href = endpointThanks;
                    }

                    // Show animation while unconfirmed or unconfirmed
                    if (isConfirmed || isUnconfirmed) {
                        uiSpinner(message);
                    }
                });
            }, interval);
        });
    });
})(jQuery);


/**
 * Attach an "In Progress" CSS animation to the DOM and render an appropriate message.
 * 
 * @param  {String} message
 * @return {Void}
 */
function uiSpinner(message) {
    // If it already exists in the DOM, no need to do it again
    if ($('.spinner-wrapper').length) {
        return;
    }
    
    // Create and re-attach with message
    $spinner = $('' +
    '<div class="spinner-wrapper hide">' +
        '<p class="message">' + message + '</p>' +
        '<div class="spinner">' +
            '<div class="rect1"></div>' +
            '<div class="rect2"></div>' +
            '<div class="rect3"></div>' +
            '<div class="rect4"></div>' +
            '<div class="rect5"></div>' +
        '</div>' +
    '</div>');
      
    $spinner.appendTo('body');
}

/**
 * Attach an "Error" CSS animation to the DOM and render an appropriate message.
 * 
 * @param  {String} message
 * @return {Void}
 */
function uiError(message) {
    // Create and re-attach with message
    $error = $('' +
    '<div class="error-wrapper hide">' +
        '<p class="message">' + message + '</p>' +
    '</div>');
      
    $error.appendTo('body');
}
