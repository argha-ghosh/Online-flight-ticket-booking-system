<?php
session_start();
include("../model/db_conn.php");
include("../includes/managerheader.php");

// Show success message if update was successful
if (isset($_GET['success']) && $_GET['success'] == 1) {
    echo "<script>alert('✓ Flight updated successfully!');</script>";
}

// Handle update
if (isset($_POST['update_flight'])) {
    $flight_id = (int)$_POST['flight_id'];
    $price = (float)$_POST['price'];
    $seat = (int)$_POST['seat'];
    
    $update_query = "UPDATE flights SET price = ?, seat = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $update_stmt->bind_param("dii", $price, $seat, $flight_id);
    
    if ($update_stmt->execute()) {
        header("Location: manageSeatupdatePrice.php?success=1");
        exit();
    } else {
        echo "<script>alert('❌ Error: " . $update_stmt->error . "');</script>";
    }
}

// Fetch all flights
$flights_query = "SELECT * FROM flights";
$flights_result = $conn->query($flights_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Seats & Update Prices</title>
    <link rel="stylesheet" href="component.css">
    <style>
        body {
            background: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }

        h1 {
            color: #0b72e6;
            text-align: center;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table thead {
            background: #0b72e6;
            color: white;
        }

        table th {
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }

        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table tbody tr:hover {
            background: #f9f9f9;
        }

        .edit-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .edit-form input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            width: 80px;
        }

        .edit-form button {
            padding: 6px 15px;
            background: #0b72e6;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }

        .edit-form button:hover {
            background: #0956b8;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            table {
                font-size: 13px;
            }

            table th, table td {
                padding: 8px;
            }

            .edit-form {
                flex-direction: column;
                gap: 5px;
            }

            .edit-form input {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>✈️ Manage Seats & Update Prices</h1>

    <?php if ($flights_result && $flights_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Flight Name</th>
                    <th>Airline</th>
                    <th>Code</th>
                    <th>Route</th>
                    <th>Current Price</th>
                    <th>Seats</th>
                    <th>Update Price  Assign Seats</th>
                    

                </tr>
            </thead>
            <tbody>
                <?php while ($flight = $flights_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($flight['flight_name']); ?></td>
                        <td><?php echo htmlspecialchars($flight['airline_name']); ?></td>
                        <td><?php echo htmlspecialchars($flight['flight_code']); ?></td>
                        <td><?php echo htmlspecialchars($flight['departure'] . ' → ' . $flight['arrival']); ?></td>
                        <td>৳ <?php echo number_format((float)$flight['price'], 2); ?></td>
                        <td><?php echo (int)($flight['seat'] ?? 0); ?></td>
                        <td>
                            <form method="POST" class="edit-form" style="margin: 0;">
                                <input type="hidden" name="flight_id" value="<?php echo $flight['id']; ?>">
                                <input type="number" step="0.01" name="price" value="<?php echo (float)$flight['price']; ?>" placeholder="Price" required>
                                <input type="number" name="seat" value="<?php echo (int)($flight['seat'] ?? 0); ?>" placeholder="Seats" required>
                                <button type="submit" name="update_flight">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">
            <p>No flights found. Please add flights first.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

<?php include("../includes/footer.php"); ?>
