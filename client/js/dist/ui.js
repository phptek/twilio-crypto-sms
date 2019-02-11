/**
 * Simple jQuery routines to pass some feedback to users as they wait for
 * Bitcoin transactions to confirm. This would likely be improved using VueJS or
 * ReactJS.
 */

(function($) {
    $(function() {
        $('#Form_TwilioSMSForm input').on('blur', function(e) {

            if (!(
                $('[name^="Body"]').val().length &&
                $('[name^="PhoneTo"]').val().length &&
                $('[name^="Address"]').val().length &&
                $('[name^="Amount"]').val().length
            )) {
                console.log('No form values');
                return;
            }

            // Repeatedly hit the endpoint until such time as we redirect.
            // This is super-hacky, but does the job to illustrate the point.
            window.setInterval(function() {
                $.ajax({
                    'type': 'POST',
                    'dataType': 'json',
                    'data': {
                        'Body': $('[name^="Body"]').val(),
                        'PhoneTo': $('[name^="PhoneTo"]').val(),
                        'Address': $('[name^="Address"]').val(),
                        'Amount': $('[name^="Amount"]').val()
                    },
                    'url': '/home/confirmation/'
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
                    var minConfirmations = $.data('num-confirmations');
                    var isConfirmed = typeof status === int && status <= minConfirmations;
                    var isNotConfirmed = json.U;

                    // 1). Show spinner, while minConfirmations or unconfirmed
                    if (isConfirmed || isNotConfirmed) {
                        var message = (typeof status === int) ? 'Confirmations: ' + status : status;
                        doSpinner(message);
                    }

                    // 2). Redirect to Thank You page
                    if (jqXHR.status === 200) {
                        // As soon as a positive result comes back from the endpoint 
                        console.log(data);
                        return window.location.href = '/thanks'; // TODO use data-attr
                    }
                });
            }, 5000);
        });
    });
})(jQuery);


/**
 * Simply attach a CSS spinner to the DOM, and render an appropriate message
 * 
 * @return {Void}
 */
function doSpinner(message) {
    // This function is called repeatedly, so clear the spinner first, and then
    // re-attach
    $('.spinner').remove();
    
    // Create and attach with message
    $spinner = '' +
    '<div class="spinner-wrapper">' +
        '<p class="message">' + message + '</p>' +
        '<div class="spinner">' +
            '<div class="rect1"></div>' +
            '<div class="rect2"></div>' +
            '<div class="rect3"></div>' +
            '<div class="rect4"></div>' +
            '<div class="rect5"></div>' +
        '</div>' +
    '</div>';
      
    $('body').attachTo($spinner);
}