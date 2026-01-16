document.getElementById('editBtn').addEventListener('click', function() {
    const inputs = document.querySelectorAll('form input, form select');
    inputs.forEach(input => {
        input.removeAttribute('readonly'); 
    });
    document.getElementById('saveBtn').style.display = 'inline-block'; 
});

document.getElementById('saveBtn').addEventListener('click', function() {
    const formData = {
        name: document.getElementById('name').value,
        age: document.getElementById('age').value,
        dob: document.getElementById('dob').value,
        address: document.getElementById('address').value,
        phone: document.getElementById('phone').value,
        gender: document.getElementById('gender').value,
        email: document.getElementById('email').value
    };

    const data = new URLSearchParams(formData);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../controller/profileController.php', true);  
    xhr.setRequestHeader('X-HTTP-Method-Override', 'PUT'); 

    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            alert('Profile updated successfully!');
            location.reload();  
        } else {
            alert('Error: Unable to save profile.');
        }
    };

    xhr.send(data);  
});
