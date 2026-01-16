document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('airlineForm').addEventListener('submit', function(event) {
        console.log('Validation triggered');
        try {
        const errors = [];
        const errorDiv = document.getElementById('errorMessages');
        errorDiv.innerHTML = '';

        // Get form values
        const airlineName = document.getElementById('airline_name').value.trim();
        const countryName = document.getElementById('country_name').value.trim();
        const airlineCode = document.getElementById('airline_code').value.trim();
        const airlineDetails = document.getElementById('airline_details').value.trim();
        const imageFile = document.getElementById('image').files[0];

        // Validate Airline Name
        if (!airlineName) {
            errors.push('Airline name is required.');
        } else if (!/^[a-zA-Z\s]+$/.test(airlineName)) {
            errors.push('Airline name should contain only letters and spaces.');
        } else if (airlineName.length < 2 || airlineName.length > 50) {
            errors.push('Airline name should be between 2 and 50 characters.');
        }

        // Validate Country Name
        if (!countryName) {
            errors.push('Country name is required.');
        } else if (!/^[a-zA-Z\s]+$/.test(countryName)) {
            errors.push('Country name should contain only letters and spaces.');
        } else if (countryName.length < 2 || countryName.length > 50) {
            errors.push('Country name should be between 2 and 50 characters.');
        }

        // Validate Airline Code
        if (!airlineCode) {
            errors.push('Airline code is required.');
        } else if (!/^[a-zA-Z0-9]{2,15}$/.test(airlineCode)) {
            errors.push('Airline code should be 2-15 letters and/or numbers.');
        }

        // Validate Airline Details
        if (!airlineDetails) {
            errors.push('Airline details are required.');
        } else if (airlineDetails.length < 10 || airlineDetails.length > 500) {
            errors.push('Airline details should be between 10 and 500 characters.');
        }

        // Validate Image
        if (!imageFile) {
            errors.push('Please select an image file.');
        } else {
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(imageFile.type)) {
                errors.push('Please select a valid image file (JPEG, PNG, GIF).');
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
});