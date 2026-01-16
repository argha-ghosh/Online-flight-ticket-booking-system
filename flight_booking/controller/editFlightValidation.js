console.log('Edit Flight Validation script loaded');
const form = document.getElementById('editFlightForm');
if (form) {
    console.log('Form found');
    form.addEventListener('submit', function(event) {
        console.log('Edit Flight Validation triggered');
        try {
            const errors = [];
            const errorDiv = document.getElementById('errorMessages');
            errorDiv.innerHTML = '';

            // Get form values
            const flightName = document.getElementById('flight_name').value.trim();
            const airlineName = document.getElementById('airline_name').value.trim();
            const flightCode = document.getElementById('flight_code').value.trim();
            const departure = document.getElementById('departure').value.trim();
            const arrival = document.getElementById('arrival').value.trim();
            const duration = document.getElementById('duration').value.trim();
            const price = document.getElementById('price').value;
            const imageFile = document.getElementById('image').files[0];

            // Validate Flight Name
            if (!flightName) {
                errors.push('Flight name is required.');
            } else if (!/^[a-zA-Z0-9\s\-]+$/.test(flightName)) {
                errors.push('Flight name should contain only letters, numbers, spaces, and hyphens.');
            } else if (flightName.length < 2 || flightName.length > 50) {
                errors.push('Flight name should be between 2 and 50 characters.');
            }

            // Validate Airline Name
            if (!airlineName) {
                errors.push('Airline name is required.');
            } else if (!/^[a-zA-Z\s]+$/.test(airlineName)) {
                errors.push('Airline name should contain only letters and spaces.');
            } else if (airlineName.length < 2 || airlineName.length > 50) {
                errors.push('Airline name should be between 2 and 50 characters.');
            }

            // Validate Flight Code
            if (!flightCode) {
                errors.push('Flight code is required.');
            } else if (!/^[A-Z0-9]{2,6}$/.test(flightCode)) {
                errors.push('Flight code should be 2-6 uppercase letters and/or numbers (e.g., AA123, BA456).');
            }

            // Validate Departure
            if (!departure) {
                errors.push('Departure location is required.');
            } else if (!/^[a-zA-Z\s]+$/.test(departure)) {
                errors.push('Departure should contain only letters and spaces.');
            } else if (departure.length < 2 || departure.length > 50) {
                errors.push('Departure should be between 2 and 50 characters.');
            }

            // Validate Arrival
            if (!arrival) {
                errors.push('Arrival location is required.');
            } else if (!/^[a-zA-Z\s]+$/.test(arrival)) {
                errors.push('Arrival should contain only letters and spaces.');
            } else if (arrival.length < 2 || arrival.length > 50) {
                errors.push('Arrival should be between 2 and 50 characters.');
            }

            // Validate Duration
            if (!duration) {
                errors.push('Duration is required.');
            } else if (!/^(\d{1,2}h\s?\d{1,2}m|\d{1,2}m|\d{1,2}h)$/.test(duration)) {
                errors.push('Duration should be in format like "2h 30m", "1h", or "45m".');
            }

            // Validate Price
            if (!price || price < 10000) {
                errors.push('Price is required and must be at least 10,000.');
            } else if (price > 1000000) {
                errors.push('Price should not exceed 1,000,000.');
            }

            // Validate Image (optional, but if selected, check type and size)
            if (imageFile) {
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
}