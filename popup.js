document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Select the correct elements based on your theme files
    const authPopup = document.querySelector('.auth-popup');
    const dimmer = document.querySelector('.dimmer');
    // Select both possible close button types to be safe
    const closeBtn = document.querySelector('.auth-popup-close') || document.querySelector('.popup-close');

    // 2. Function to Open Popup
    function openAuthPopup(e) {
        if (e) e.preventDefault();
        
        if (authPopup) {
            authPopup.classList.add('active');
            if (dimmer) dimmer.classList.add('active');
        }
    }

    // 3. Function to Close Popup
    function closeAuthPopup() {
        if (authPopup) authPopup.classList.remove('active');
        if (dimmer) dimmer.classList.remove('active');
    }

    // 4. Attach to Header Login Buttons ONLY
    // We do NOT attach to 'Book Now' here because mindbody-shortcodes.php handles that
    const loginBtns = document.querySelectorAll('.login-btn, .menu-item-login a');
    
    loginBtns.forEach(btn => {
        btn.addEventListener('click', openAuthPopup);
    });

    // 5. Attach Close Listeners
    if (closeBtn) closeBtn.addEventListener('click', closeAuthPopup);
    if (dimmer) dimmer.addEventListener('click', closeAuthPopup);
    
    // Close when clicking the background of the popup itself
    if (authPopup) {
        authPopup.addEventListener('click', function(e) {
            if (e.target === authPopup) closeAuthPopup();
        });
    }
    
    // 6. Support switching between Login and Signup forms (if your popup has them)
    const switchToSignup = document.querySelector('.create-account');
    const switchToLogin = document.querySelector('.sign-in-link'); // Adjust class if needed
    const loginForm = document.querySelector('#login-form'); // Adjust ID if needed
    const signupForm = document.querySelector('#signup-form'); // Adjust ID if needed

    if (switchToSignup && loginForm && signupForm) {
        switchToSignup.addEventListener('click', function(e) {
            e.preventDefault();
            loginForm.style.display = 'none';
            signupForm.style.display = 'block';
        });
    }
    
    if (switchToLogin && loginForm && signupForm) {
        switchToLogin.addEventListener('click', function(e) {
            e.preventDefault();
            signupForm.style.display = 'none';
            loginForm.style.display = 'block';
        });
    }
});