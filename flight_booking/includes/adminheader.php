<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <link rel ="stylesheet" href= "component.css">
    <script src="controller/hamburger.js"></script>
</head>
<body>
    <header>
        <div class="header-container">
           <h1>Welcome Admin</h1>
            <nav>
                <a href="/flight_booking/view/home.php">Home</a>
                <a href="/flight_booking/view/addAirline.php">Add Airlines</a>
                <a href="/flight_booking/view/addFlight.php">Add Flight</a>
                <a href="/flight_booking/view/adduser.php">Add User</a> 
                <a href="/flight_booking/view/login.php">Logout</a>
                <a href="/flight_booking/view/viewadminprofile.php">View Profile</a>

                <!-- Profile Dropdown -->
                <div class="dropdown">
                     <div class="hamburger">
                         <span></span>
                         <span></span>
                         <span></span>
                     </div>
                     <div class="dropdown-content">
                         <p>Hello, User!</p>
                         <a href="#">Profile</a>
                         <a href="#">View Profile</a>
                         <a href="#">Edit Profile</a>
                         <a href="/flight_booking/view/login.php">Log Out</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>
</body>
</html>
