console.log('Add User Validation script loaded');
const form = document.getElementById('addUserForm');
if (form) {
    console.log('Form found');
    form.addEventListener('submit', function(event) {
        console.log('Add User Validation triggered');
        try {
            const errors = [];
            const errorDiv = document.getElementById('errorMessages');
            errorDiv.innerHTML = '';

            // Get form values
            const name = document.getElementById('name').value.trim();
            const age = document.getElementById('age').value;
            const dob = document.getElementById('dob').value;
            const phone = document.getElementById('phone').value.trim();
            const gender = document.getElementById('gender').value;
            const city = document.getElementById('city').value;
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('pass').value;
            const role = document.getElementById('role').value;
            const imageFile = document.getElementById('image').files[0];

            // Validate Name
            if (!name) {
                errors.push('Name is required.');
            } else if (!/^[a-zA-Z\s]+$/.test(name)) {
                errors.push('Name should contain only letters and spaces.');
            } else if (name.length < 2 || name.length > 50) {
                errors.push('Name should be between 2 and 50 characters.');
            }

            // Validate Age
            if (!age || age < 1 || age > 120) {
                errors.push('Please enter a valid age between 1 and 120.');
            }

            // Validate Date of Birth
            if (!dob) {
                errors.push('Date of birth is required.');
            } else {
                const birthDate = new Date(dob);
                const today = new Date();
                const ageFromDob = today.getFullYear() - birthDate.getFullYear();
                if (birthDate > today) {
                    errors.push('Date of birth cannot be in the future.');
                } else if (ageFromDob < 1 || ageFromDob > 120) {
                    errors.push('Please enter a valid date of birth.');
                }
            }

            // Validate Phone
            if (!phone) {
                errors.push('Phone number is required.');
            } else if (!/^[0-9]{11}$/.test(phone)) {
                errors.push('Phone number should be exactly 11 digits.');
            }

            // Validate Gender
            if (!gender) {
                errors.push('Please select a gender.');
            }

            // Validate City
            if (!city) {
                errors.push('Please select a city.');
            }

            // Validate Email
            if (!email) {
                errors.push('Email is required.');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('Please enter a valid email address.');
            }

            // Validate Password
            if (!password) {
                errors.push('Password is required.');
            } else if (password.length < 6) {
                errors.push('Password should be at least 6 characters long.');
            }

            // Validate Role
            if (!role) {
                errors.push('Please select a role.');
            }

            // Validate Image
            if (!imageFile) {
                errors.push('Please select a profile image.');
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
                errorDiv.innerHTML = '<ul><li>' + errors.join('</li><li>') + '</li></ul>';
                return false;
            }

            // If all good, allow submission
            return true;

        } catch (error) {
            event.preventDefault();
            console.error('Validation error:', error);
            document.getElementById('errorMessages').innerHTML = 'An unexpected error occurred during validation. Please try again.';
            return false;
        }
    });
}