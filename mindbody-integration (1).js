jQuery(document).ready(function($) {
    $('#mindbody-signup-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $message = $form.find('.form-message');
        
        // Clear previous messages
        $message.removeClass('error success').empty();
        
        const password = $form.find('input[name="password"]').val();
        const confirmPassword = $form.find('input[name="confirmPassword"]').val();

        if (password !== confirmPassword) {
            $message.addClass('error').text('Passwords do not match.');
            return;
        }

        // Basic UK postcode validation
        const postcode = $form.find('input[name="postalCode"]').val();
        if (postcode && !/^[A-Z]{1,2}[0-9][A-Z0-9]? ?[0-9][A-Z]{2}$/i.test(postcode)) {
            $message.addClass('error').text('Please enter a valid UK postcode');
            return;
        }

        // Basic UK phone validation
        const phone = $form.find('input[name="phone"]').val();
        if (phone && !/^(?:(?:\+44)|(?:0))(?:(?:(?:\d{10})|(?:\d{9})))$/.test(phone)) {
            $message.addClass('error').text('Please enter a valid UK mobile number (e.g., 07123456789 or +447123456789)');
            return;
        }
        
        // Disable form while submitting
        $form.find('input, button').prop('disabled', true);
        
        $.ajax({
            url: hw_mindbody_data.ajax_url,
            type: 'POST',
            data: {
                action: 'hw_mindbody_signup',
                nonce: $('#mindbody_signup_nonce').val(),
                firstName: $form.find('input[name="firstName"]').val(),
                lastName: $form.find('input[name="lastName"]').val(),
                email: $form.find('input[name="email"]').val(),
                password: password,
                address1: $form.find('input[name="address1"]').val(),
                city: $form.find('input[name="city"]').val(),
                postalCode: $form.find('input[name="postalCode"]').val(),
                country: 'UK',
                phone: $form.find('input[name="phone"]').val(),
                birthDate: $form.find('input[name="birthDate"]').val() || new Date(Date.now() - (18 * 365 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0], // Default to 18 years ago
                referredBy: $form.find('select[name="referredBy"]').val() || 'Website'
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    $message.addClass('error').text(response.data);
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred. Please try again.');
            },
            complete: function() {
                // Re-enable form
                $form.find('input, button').prop('disabled', false);
            }
        });
    });
    $('#mindbody-login-form').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $message = $form.find('.form-message');

        $message.removeClass('error success').empty();

        $form.find('input, button').prop('disabled', true);
        console.log("email", $form.find('input[name="email"]').val());
        console.log("password", $form.find('input[name="password"]').val());
        $.ajax({
            url: hw_mindbody_data.ajax_url,
            type: 'POST',
            data: {
                action: 'hw_mindbody_login',
                nonce: $('#mindbody_login_nonce').val(),
                email: $form.find('input[name="email"]').val(),
                password: $form.find('input[name="password"]').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').text('Login successful! Redirecting...');
                    setTimeout(function() {
                        window.location.reload(); // refresh after login
                    }, 1500);
                } else {
                    $message.addClass('error').text(response.data);
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred. Please try again.');
            },
            complete: function() {
                $form.find('input, button').prop('disabled', false);
            }
        });
    });

});
