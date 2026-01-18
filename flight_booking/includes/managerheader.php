<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoZayan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <link rel="stylesheet" href="component.css">
    <style>
        nav {
            display: flex;
            align-items: center;  
            gap: 10px;  
        }
        .hamburger {
            display: flex;
            flex-direction: column;
            gap: 4px;
            cursor: pointer;
            padding: 10px;
        }
 
        .hamburger span {
            display: block;
            width: 30px;
            height: 3px;
            background-color: white;
            border-radius: 5px;
        }
 
        .dropdown-content {
            display: none;
            position: absolute;
            top: 50px;
            right: 0;
            background-color: #0b72e6;
            width: 200px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            padding: 10px;
        }
 
        .dropdown-content.show {
            display: block;
        }
 
        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
 
        .dropdown-content a:hover {
            background-color: #ddd;
        }
 
        @media screen and (max-width: 768px) {
            nav a {
                display: none;
            }
            .hamburger {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <h1>Welcome Manager</h1>
            <nav>
                <a href="/flight_booking/view/home.php">Home</a>
                <a href="/flight_booking/view/managerdemo.php">Manage Flights</a>
                <a href="#">Manage Seats</a>
                <a href="#">Update Price</a>
                <!-- <a href="/flight_booking/view/adduser.php">Update Price</a>  -->
 
                <!-- Dropdown -->
                <div class="dropdown">
                    <div class="hamburger" onclick="toggleDropdown()">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="dropdown-content">
                        <p>Hello, Manager!</p>
                        <a href="/flight_booking/view/viewmanagerprofile.php">View Profile</a>
                        <a href="/flight_booking/view/login.php">Log Out</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>
 
    <script>
        function toggleDropdown() {
            document.querySelector('.dropdown-content').classList.toggle('show');
        }
 
        window.onclick = function(event) {
            if (!event.target.matches('.hamburger') && !event.target.matches('.dropdown-content') && !event.target.closest('.dropdown')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>
</body>
</html>