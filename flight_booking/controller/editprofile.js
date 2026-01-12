document.getElementById("saveBtn").addEventListener("click", function() {
   
    const formData = {
        name: document.getElementById("name").value,
        age: document.getElementById("age").value,
        dob: document.getElementById("dob").value,
        address: document.getElementById("address").value,
        phone: document.getElementById("phone").value,
        gender: document.getElementById("gender").value,
        email: document.getElementById("email").value,
        password: document.getElementById("password").value
    };


    $.ajax({
        url: "updateProfile.php",
        type: "POST",
        data: formData,
        success: function(response) {
            if (response == "success") {
                alert("Profile updated successfully!");
                location.reload(); 
            } else {
                alert("Failed to update profile.");
            }
        }
    });
});

