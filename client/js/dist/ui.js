/**
 * Simple jQuery routines to pass some feedback to users as they wait for
 * Bitcoin transactions to confirm. This would be improved using Vue or React.
 */

// The frequency at which we will hit our own endpoint
var interval = 10000;

(function($) {
    $(function() {
        $('#Form_TwilioSMSForm input, #Form_TwilioSMSForm textarea').blur(function(e) {
            var body = $('[name="Body"]').val();
            var phone = $('[name="PhoneTo"]').val();
            var address = $('[name="Address"]').val();
            var amount = $('[name="Amount"]').val();
            var endpointConf = $(this).closest('form').data('uri-confirmation');
            var endpointThanks = $(this).closest('form').data('uri-thanks');
            
            if (!(body.length && phone.length && address.length && amount.length)) {
                return;
            }

            // Repeatedly hit the endpoint until such time as we redirect.
            // This is super-hacky, we should use websockets, but it does the job.
            setInterval(function() {
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
                // Exceptions from API clients can generate errors
                // fail() === error()
                .fail(function (jqXHR, textStatus, errorThrown) {
                    console.error(errorThrown, textStatus);
                })
                // done() === success()
                .done(function(data, textStatus, jqXHR) {
                    var json = JSON.stringify(data);
                    var status = json.U ? json.U : json.C;
                    var minConfirmations = $('[name="MinConfs"]').val();
                    var isConfirmed = (typeof status === 'number') && status <= minConfirmations;
                    var isNotConfirmed = json.U;
                    var isDone = (typeof status === 'number') && status > minConfirmations;

                    // Redifrect as soon as a positive result comes back
                    if (isDone) {
                        return location.href = endpointThanks;
                    }

                    // Show animation while minConfirmations or unconfirmed
                    if (isConfirmed || isNotConfirmed) {
                        var message = ((typeof status === 'number') ? 'Confirmations: ' + status : status) + '...';
                        doSpinner(message);
                    }
                });
            }, interval);
        });
    });
})(jQuery);


/**
 * Simply attach a CSS animation to the DOM and render an appropriate message.
 * 
 * @param  {String} message
 * @return {Void}
 */
function doSpinner(message) {
    // This function is called repeatedly, so clear the spinner first
    $('.spinner-wrapper').remove();
    
    // Create and re-attach with message
    $spinner = $('' +
    '<div class="spinner-wrapper">' +
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