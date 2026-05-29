const form = document.getElementById('editFlightForm');
if (form) {
    form.addEventListener('submit', function(event) {
        const errors = [];
        const errorDiv = document.getElementById('efErrorMessages') || document.getElementById('errorMessages');
        if (errorDiv) {
            errorDiv.innerHTML = '';
            errorDiv.style.display = 'none';
        }

        const flightNameEl = document.getElementById('flight_name');
        const airlineNameEl = document.getElementById('airline_name');
        const flightCodeEl = document.getElementById('flight_code');
        const departureEl = document.getElementById('departure');
        const arrivalEl = document.getElementById('arrival');
        const durationEl = document.getElementById('duration');
        const priceEl = document.getElementById('price');
        const imageEl = document.getElementById('image');

        const flightName = flightNameEl ? flightNameEl.value.trim() : '';
        const airlineName = airlineNameEl ? airlineNameEl.value.trim() : '';
        const flightCode = flightCodeEl ? flightCodeEl.value.trim() : '';
        const departure = departureEl ? departureEl.value.trim() : '';
        const arrival = arrivalEl ? arrivalEl.value.trim() : '';
        const duration = durationEl ? durationEl.value.trim() : '';
        const price = priceEl ? parseFloat(priceEl.value) : 0;
        const imageFile = (imageEl && imageEl.files) ? imageEl.files[0] : null;

        if (!flightName)  errors.push('Flight name is required.');
        if (!airlineName) errors.push('Airline name is required.');
        if (!flightCode)  errors.push('Flight code is required.');
        if (!departure)   errors.push('Departure city is required.');
        if (!arrival)     errors.push('Arrival city is required.');
        if (!duration)    errors.push('Duration is required.');
        if (isNaN(price) || price <= 0) errors.push('A valid price is required.');

        if (imageFile) {
            const allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
            if (!allowed.includes(imageFile.type))
                errors.push('Please select a valid image (JPEG, PNG, GIF, WEBP).');
            if (imageFile.size > 5 * 1024 * 1024)
                errors.push('Image must be under 5MB.');
        }

        if (errors.length > 0) {
            event.preventDefault();
            if (errorDiv) {
                errorDiv.innerHTML = errors.map(e => `<div>⚠️ ${e}</div>`).join('');
                errorDiv.style.display = 'block';
            }
            return false;
        }
    });
}
