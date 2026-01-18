// Fetch flight details via AJAX
function fetchFlightDetails() {
    const flightCode = document.getElementById('flight_code').value.trim();
    
    if (flightCode === '') {
        document.getElementById('flight_name').value = '';
        document.getElementById('airline_name').value = '';
        return;
    }
    
    const url = window.location.pathname + '?action=get_flight&code=' + encodeURIComponent(flightCode);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('flight_name').value = data.flight_name;
                document.getElementById('airline_name').value = data.airline_name;
            } else {
                document.getElementById('flight_name').value = '';
                document.getElementById('airline_name').value = '';
                alert('Flight code not found!');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching flight details!');
        });
}

// Populate form with schedule data for editing
function editSchedule(flightCode, flightName, airlineName, departureDay, departureTime, arrivalDay, arrivalTime) {
    // Populate form fields
    document.getElementById('flight_code').value = flightCode;
    document.getElementById('flight_name').value = flightName;
    document.getElementById('airline_name').value = airlineName;
    document.querySelector('select[name="departure_from"]').value = departureDay;
    document.querySelector('input[name="departure_time"]').value = departureTime;
    document.querySelector('select[name="arrival_to"]').value = arrivalDay;
    document.querySelector('input[name="arrival_time"]').value = arrivalTime;
    
    // Scroll to the form
    document.querySelector('.schedule-container').scrollIntoView({ behavior: 'smooth' });
}

// Validate schedule form before submission
function validateScheduleForm() {
    const flightCode = document.getElementById('flight_code').value.trim();
    const flightName = document.getElementById('flight_name').value.trim();
    const airlineName = document.getElementById('airline_name').value.trim();
    const departureDay = document.getElementById('departure_from').value.trim();
    const departureTime = document.getElementById('departure_time').value.trim();
    const arrivalDay = document.getElementById('arrival_to').value.trim();
    const arrivalTime = document.getElementById('arrival_time').value.trim();
    
    if (!flightCode || !flightName || !airlineName) {
        alert('❌ Please fill in flight code, name, and airline!');
        return false;
    }
    
    if (!departureDay || !departureTime) {
        alert('❌ Please select departure day and time!');
        return false;
    }
    
    if (!arrivalDay || !arrivalTime) {
        alert('❌ Please select arrival day and time!');
        return false;
    }
    
    // Validate time format (HH:MM)
    if (!/^\d{2}:\d{2}$/.test(departureTime)) {
        alert('❌ Invalid departure time format! Use HH:MM');
        return false;
    }
    
    if (!/^\d{2}:\d{2}$/.test(arrivalTime)) {
        alert('❌ Invalid arrival time format! Use HH:MM');
        return false;
    }
    
    console.log('Form Data:', { flightCode, flightName, airlineName, departureDay, departureTime, arrivalDay, arrivalTime });
    return true;
}
