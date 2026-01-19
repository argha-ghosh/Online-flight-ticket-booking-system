console.log('Register Validation script loaded');

const form = document.getElementById('registerForm');

if (form) {
    console.log('Register form found');

    form.addEventListener('submit', function (event) {
        console.log('Register Validation triggered');

        try {
            const errors = [];
            const errorDiv = document.getElementById('errorMessages');
            if (errorDiv) errorDiv.innerHTML = '';

            const nameEl = document.getElementById('name');
            const emailEl = document.getElementById('email');
            const passEl = document.getElementById('pass');
            const cpassEl = document.getElementById('cpass');
            const imageEl = document.getElementById('image');

            const name = (nameEl?.value || '').trim();
            const email = (emailEl?.value || '').trim();
            const password = passEl?.value || '';
            const confirmPassword = cpassEl?.value || '';
            const imageFile = imageEl?.files?.[0];

            // Validate Name
            if (!name) {
                errors.push('Name is required.');
            } else if (!/^[a-zA-Z\s]+$/.test(name)) {
                errors.push('Name should contain only letters and spaces.');
            } else if (name.length < 2 || name.length > 50) {
                errors.push('Name should be between 2 and 50 characters.');
            }

            // Validate Email
            if (!email) {
                errors.push('Email is required.');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('Please enter a valid email address.');
            } else if (email.length > 254) {
                errors.push('Email is too long.');
            }

            // Validate Password strength
            if (!password) {
                errors.push('Password is required.');
            } else {
                if (password.length < 4) errors.push('Password should be at least 6 characters long.');
                if (password.length > 12) errors.push('Password should not exceed 128 characters.');
                if (!/[A-Z]/.test(password)) errors.push('Password should contain at least 1 uppercase letter.');
                if (!/[a-z]/.test(password)) errors.push('Password should contain at least 1 lowercase letter.');
                if (!/[0-9]/.test(password)) errors.push('Password should contain at least 1 number.');
                if (!/[!@#$%^&*()_\-+={[}\]|\\:;"'<,>.?/`~]/.test(password)) {
                    errors.push('Password should contain at least 1 special character.');
                }
            }

            // Validate Confirm Password
            if (!confirmPassword) {
                errors.push('Confirm password is required.');
            } else if (password && confirmPassword !== password) {
                errors.push('Password and confirm password do not match.');
            }

            // Validate Image
            if (!imageFile) {
                errors.push('Please select an image file.');
            } else {
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(imageFile.type)) {
                    errors.push('Please select a valid image file (JPEG or PNG).');
                }
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (imageFile.size > maxSize) {
                    errors.push('Image file size should not exceed 5MB.');
                }
            }

            // If errors, prevent submission and display them
            if (errors.length > 0) {
                event.preventDefault();
                if (errorDiv) {
                    errorDiv.innerHTML = '<ul><li>' + errors.join('</li><li>') + '</li></ul>';
                }
                return false;
            }

            return true;
        } catch (error) {
            event.preventDefault();
            console.error('Validation error:', error);
            const errorDiv = document.getElementById('errorMessages');
            if (errorDiv) {
                errorDiv.innerHTML = 'An unexpected error occurred during validation. Please try again.';
            }
            return false;
        }
    });
}

