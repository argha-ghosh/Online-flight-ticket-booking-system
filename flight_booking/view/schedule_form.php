<?php
include("../includes/header.php");
include("../model/db_conn.php");

$manager_id = 16; // Default manager ID
$days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
$schedule_data = array(); // Array to store fetched schedule data

// Fetch existing schedule data from database
$fetch_query = "SELECT day_name, start_time, end_time, is_available FROM manager_schedule WHERE manager_id = ?";
$fetch_stmt = $conn->prepare($fetch_query);
$fetch_stmt->bind_param("i", $manager_id);
$fetch_stmt->execute();
$fetch_result = $fetch_stmt->get_result();

while ($row = $fetch_result->fetch_assoc()) {
    $schedule_data[$row['day_name']] = $row;
}

// Handle form submission - Save schedule to database
$message = "";
if (isset($_POST['save_schedule'])) {
    $save_count = 0;
    
    foreach ($days as $day) {
        $start_time = isset($_POST[$day . '_start']) ? $_POST[$day . '_start'] : '';
        $end_time = isset($_POST[$day . '_end']) ? $_POST[$day . '_end'] : '';
        $is_available = isset($_POST[$day . '_available']) ? 1 : 0;
        
        // Check if record exists for this day
        $check_query = "SELECT id FROM manager_schedule WHERE manager_id = ? AND day_name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("is", $manager_id, $day);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing record
            $update_query = "UPDATE manager_schedule SET start_time = ?, end_time = ?, is_available = ? WHERE manager_id = ? AND day_name = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssii", $start_time, $end_time, $is_available, $manager_id, $day);
            if ($update_stmt->execute()) {
                $save_count++;
            }
        } else {
            // Insert new record
            $insert_query = "INSERT INTO manager_schedule (manager_id, day_name, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("isssi", $manager_id, $day, $start_time, $end_time, $is_available);
            if ($insert_stmt->execute()) {
                $save_count++;
            }
        }
    }
    
    if ($save_count == count($days)) {
        $message = "✓ Schedule saved successfully!";
        // Refresh the data
        $schedule_data = array();
        $fetch_stmt = $conn->prepare($fetch_query);
        $fetch_stmt->bind_param("i", $manager_id);
        $fetch_stmt->execute();
        $fetch_result = $fetch_stmt->get_result();
        while ($row = $fetch_result->fetch_assoc()) {
            $schedule_data[$row['day_name']] = $row;
        }
    } else {
        $message = "❌ Error saving schedule!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Schedule</title>
    <link rel="stylesheet" href="component.css">
    <style>
        .schedule-container {
            width: 80%;
            max-width: 1000px;
            margin: 50px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .schedule-container h2 {
            text-align: center;
            color: #0b72e6;
            margin-bottom: 30px;
        }

        .message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .schedule-table thead {
            background-color: #0b72e6;
            color: white;
        }

        .schedule-table th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }

        .schedule-table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
        }

        .schedule-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .schedule-table tbody tr:hover {
            background-color: #f0f8ff;
        }

        .schedule-table input[type="time"],
        .schedule-table input[type="checkbox"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .schedule-table input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .schedule-table input[type="time"] {
            width: 100%;
            padding: 8px;
            font-size: 14px;
        }

        .checkbox-cell {
            text-align: center;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-save {
            background-color: #28a745;
            color: white;
        }

        .btn-save:hover {
            background-color: #218838;
        }

        .btn-reset {
            background-color: #6c757d;
            color: white;
        }

        .btn-reset:hover {
            background-color: #5a6268;
        }

        .disabled-time {
            background-color: #e9ecef;
            color: #999;
        }
    </style>
</head>
<body>

<div class="schedule-container">
    <h2>Weekly Schedule Management</h2>

    <?php if (!empty($message)) { ?>
        <div class="message"><?php echo $message; ?></div>
    <?php } ?>

    <form method="POST" action="">
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Available</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($days as $day) {
                    $is_weekend = in_array($day, array('Saturday', 'Sunday'));
                    
                    // Get saved values or use defaults
                    $start_time = isset($schedule_data[$day]) ? $schedule_data[$day]['start_time'] : '09:00';
                    $end_time = isset($schedule_data[$day]) ? $schedule_data[$day]['end_time'] : '17:00';
                    $is_available = isset($schedule_data[$day]) ? $schedule_data[$day]['is_available'] : (!$is_weekend ? 1 : 0);
                    
                    // Extract only HH:MM if stored as full timestamp
                    if (strpos($start_time, ':') !== false) {
                        $start_time = substr($start_time, 0, 5); // Get HH:MM
                    }
                    if (strpos($end_time, ':') !== false) {
                        $end_time = substr($end_time, 0, 5); // Get HH:MM
                    }
                    ?>
                    <tr>
                        <td><strong><?= $day; ?></strong></td>
                        <td class="checkbox-cell">
                            <input type="checkbox" name="<?= $day; ?>_available" value="1" 
                                   <?= $is_available ? 'checked' : ''; ?> 
                                   onchange="toggleTimeInputs(this)">
                        </td>
                        <td>
                            <input type="time" name="<?= $day; ?>_start" 
                                   class="time-input" 
                                   value="<?= htmlspecialchars($start_time); ?>"
                                   <?= !$is_available ? 'disabled' : ''; ?>>
                        </td>
                        <td>
                            <input type="time" name="<?= $day; ?>_end" 
                                   class="time-input" 
                                   value="<?= htmlspecialchars($end_time); ?>"
                                   <?= !$is_available ? 'disabled' : ''; ?>>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>

        <div class="btn-group">
            <button type="submit" class="btn btn-save" name="save_schedule">Save Schedule</button>
            <button type="reset" class="btn btn-reset">Reset</button>
        </div>
    </form>
</div>

<script>
    function toggleTimeInputs(checkbox) {
        const row = checkbox.closest('tr');
        const startInput = row.querySelector('input[type="time"]:first-of-type');
        const endInput = row.querySelector('input[type="time"]:last-of-type');
        
        if (checkbox.checked) {
            startInput.disabled = false;
            endInput.disabled = false;
            startInput.classList.remove('disabled-time');
            endInput.classList.remove('disabled-time');
        } else {
            startInput.disabled = true;
            endInput.disabled = true;
            startInput.classList.add('disabled-time');
            endInput.classList.add('disabled-time');
        }
    }
</script>

</body>
</html>

<?php include("../includes/footer.php"); ?>
