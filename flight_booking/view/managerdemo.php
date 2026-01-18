<?php
include("../model/db_conn.php");

// Handle AJAX request for flight details - MUST be before any output
if (isset($_GET['action']) && $_GET['action'] == 'get_flight') {
    header('Content-Type: application/json');
    $flight_code = isset($_GET['code']) ? $_GET['code'] : '';
    
    if (!empty($flight_code)) {
        $query = "SELECT flight_name, airline_name FROM flights WHERE flight_code = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $flight_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $flight = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'flight_name' => $flight['flight_name'],
                'airline_name' => $flight['airline_name']
            ]);
        } else {
            echo json_encode(['success' => false]);
        }
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

include("../includes/managerheader.php");

// Helper function to extract time from database value
function extractTime($timeValue) {
    if (empty($timeValue)) {
        return '00:00';
    }
    
    // If it's already in HH:MM format, return as is
    if (strlen($timeValue) == 5 && strpos($timeValue, ':') !== false) {
        return $timeValue;
    }
    
    // If it contains a space (datetime format), extract only the time portion
    if (strpos($timeValue, ' ') !== false) {
        $timePart = explode(' ', $timeValue)[1];
        return substr($timePart, 0, 5); // Get HH:MM
    }
    
    // Extract first 5 characters for HH:MM format
    return substr($timeValue, 0, 5);
}

// Handle search
$search_code = isset($_POST['search_code']) ? $_POST['search_code'] : '';
$selected_flight = null;

// Fetch flights - with or without search filter
if (!empty($search_code)) {
    $flights_query = "SELECT * FROM flights WHERE flight_code LIKE '%$search_code%'";
    $stmt = $conn->prepare("SELECT flight_name, airline_name FROM flights WHERE flight_code = ?");
    $stmt->bind_param("s", $search_code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $selected_flight = $result->fetch_assoc();
    }
} else {
    $flights_query = "SELECT * FROM flights";
}
$flights_result = $conn->query($flights_query);

// Handle delete schedule
if (isset($_GET['delete_schedule']) && !empty($_GET['delete_schedule'])) {
    $delete_code = $_GET['delete_schedule'];
    $delete_query = "DELETE FROM schedule WHERE flight_code = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("s", $delete_code);
    if ($delete_stmt->execute()) {
        header("Location: managerdemo.php");
        exit;
    }
}

// Handle schedule save
$schedule_message = "";
if (isset($_POST['save_schedule'])) {
    $flight_name = $_POST['flight_name'] ?? '';
    $airline_name = $_POST['airline_name'] ?? '';
    $flight_code = $_POST['flight_code'] ?? '';
    $departure_from = $_POST['departure_from'] ?? '';
    $departure_time = $_POST['departure_time'] ?? '';
    $arrival_to = $_POST['arrival_to'] ?? '';
    $arrival_time = $_POST['arrival_time'] ?? '';
    
    // Validate required fields including times
    if (empty($flight_code) || empty($flight_name) || empty($airline_name) || empty($departure_time) || empty($arrival_time)) {
        $schedule_message = "❌ Please fill in all required fields (flight code, name, airline, and times)!";
    } else if (empty($departure_from) || empty($arrival_to)) {
        $schedule_message = "❌ Please select departure and arrival days!";
    } else {
        // Check if flight code already exists
        $check_query = "SELECT * FROM schedule WHERE flight_code = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $flight_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing schedule
            $update_query = "UPDATE schedule SET flight_name = ?, airline_name = ?, departure_day = ?, departure_time = ?, arrival_day = ?, arrival_time = ? WHERE flight_code = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssssss", $flight_name, $airline_name, $departure_from, $departure_time, $arrival_to, $arrival_time, $flight_code);
            
            if ($update_stmt->execute()) {
                $schedule_message = "✓ Schedule updated successfully!";
            } else {
                $schedule_message = "❌ Error updating schedule: " . $update_stmt->error;
            }
        } else {
            // Insert new schedule
            $insert_query = "INSERT INTO schedule (flight_name, airline_name, flight_code, departure_day, departure_time, arrival_day, arrival_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sssssss", $flight_name, $airline_name, $flight_code, $departure_from, $departure_time, $arrival_to, $arrival_time);
            
            if ($insert_stmt->execute()) {
                $schedule_message = "✓ Schedule saved successfully!";
            } else {
                $schedule_message = "❌ Error saving schedule: " . $insert_stmt->error;
            }
        }
    }
}

// Fetch all schedules
$schedules_query = "SELECT * FROM schedule";
$schedules_result = $conn->query($schedules_query);

?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
 
    <link rel ="stylesheet" href= "component.css">
    <link rel ="stylesheet" href= "flight_schedule.css">
    <link rel ="stylesheet" href= "managerdemo.css">
</head>
<body>
    <div class="flights-container">
        <h2>Available Flights</h2>
        
        <!-- Search Bar -->
        <div class="search-section">
            <form method="POST">
                <input type="text" name="search_code"  value="<?= htmlspecialchars($search_code); ?>">
                <button type="submit">Search</button>
                <?php if (!empty($search_code)) { ?>
                    <a href="managerdemo.php" style="text-decoration: none;">
                        <button type="button" class="clear-btn">Clear</button>
                    </a>
                <?php } ?>
            </form>
        </div>
        <?php if ($flights_result && $flights_result->num_rows > 0) { ?>
            <table class="flights-table">
                <thead>
                    <tr>
                        <th>Flight Name</th>
                        <th>Airline</th>
                        <th>Flight Code</th>
                        <th>Departure</th>
                        <th>Arrival</th>
                        <th>Duration</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($flight = $flights_result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $flight['flight_name']; ?></td>
                            <td><?= $flight['airline_name']; ?></td>
                            <td><?= $flight['flight_code']; ?></td>
                            <td><?= $flight['departure']; ?></td>
                            <td><?= $flight['arrival']; ?></td>
                            <td><?= $flight['duration']; ?></td>
                            <td>$<?= number_format($flight['price'], 2); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <div class="error-message">
                <p>❌ No flights found matching flight code: <strong><?= htmlspecialchars($search_code); ?></strong></p>
                <p>Please check the flight code and try again.</p>
            </div>
        <?php } ?>
    </div>

    <!-- Schedule Management Form -->
    <div class="schedule-container">
        <h3>Add/Update Flight Schedule</h3>

        <?php if (!empty($schedule_message)) { ?>
            <div class="schedule-message"><?php echo $schedule_message; ?></div>
        <?php } ?>

        <form method="POST" action="">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Flight Name</th>
                        <th>Airline Name</th>
                        <th>Flight Code</th>
                        <th>Departure Day</th>
                        <th>Departure Time</th>
                        <th>Arrival Day</th>
                        <th>Arrival Time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" name="flight_name" id="flight_name" value="<?= $selected_flight ? $selected_flight['flight_name'] : ''; ?>" readonly required>
                        </td>
                        <td>
                            <input type="text" name="airline_name" id="airline_name" value="<?= $selected_flight ? $selected_flight['airline_name'] : ''; ?>" readonly required>
                        </td>
                        <td>
                            <input type="text" name="flight_code" id="flight_code" value="<?= htmlspecialchars($search_code); ?>" required onchange="fetchFlightDetails()">
                        </td>
                        <td>
                            <select name="departure_from" id="departure_from" required>
                                <option value="">Select Departure Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </td>
                        <td>
                            <input type="time" name="departure_time" id="departure_time" required>
                        </td>
                        <td>
                            <select name="arrival_to" id="arrival_to" required>
                                <option value="">Select Arrival Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </td>
                        <td>
                            <input type="time" name="arrival_time" id="arrival_time" required>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="schedule-btn-group">
                <button type="submit" class="schedule-btn btn-save-schedule" name="save_schedule" onclick="return validateScheduleForm()">Save Schedule</button>
                <button type="reset" class="schedule-btn btn-reset-schedule">Reset</button>
            </div>
        </form>
    </div>

    <!-- Schedule Listing -->
    <div class="schedule-container">
        <h3>Flight Schedules List</h3>

        <?php if ($schedules_result && $schedules_result->num_rows > 0) { ?>
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Flight Name</th>
                        <th>Airline Name</th>
                        <th>Flight Code</th>
                        <th>Departure Day</th>
                        <th>Departure Time</th>
                        <th>Arrival Day</th>
                        <th>Arrival Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($schedule = $schedules_result->fetch_assoc()) { 
                        // Extract only time portion (HH:MM) if stored as datetime
                        $dept_time = extractTime($schedule['departure_time']);
                        $arrv_time = extractTime($schedule['arrival_time']);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($schedule['flight_name']); ?></td>
                            <td><?= htmlspecialchars($schedule['airline_name']); ?></td>
                            <td><?= htmlspecialchars($schedule['flight_code']); ?></td>
                            <td><?= htmlspecialchars($schedule['departure_day']); ?></td>
                            <td><?= htmlspecialchars($dept_time); ?></td>
                            <td><?= htmlspecialchars($schedule['arrival_day']); ?></td>
                            <td><?= htmlspecialchars($arrv_time); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit-btn" onclick="editSchedule('<?= htmlspecialchars($schedule['flight_code']); ?>', '<?= htmlspecialchars($schedule['flight_name']); ?>', '<?= htmlspecialchars($schedule['airline_name']); ?>', '<?= htmlspecialchars($schedule['departure_day']); ?>', '<?= htmlspecialchars($dept_time); ?>', '<?= htmlspecialchars($schedule['arrival_day']); ?>', '<?= htmlspecialchars($arrv_time); ?>')">Edit</button>
                                    <a href="managerdemo.php?delete_schedule=<?= urlencode($schedule['flight_code']); ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this schedule?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <div class="error-message">
                <p>No schedules found. Create a new schedule using the form above.</p>
            </div>
        <?php } ?>
    </div>
 
</body>
</html>

<script src="../controller/managerdemo.js"></script>
 