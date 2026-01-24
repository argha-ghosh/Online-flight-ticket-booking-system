<?php 
include("../model/db_conn.php");
include("../includes/managerheader.php");

$flights = [];
$search_performed = false;
$trip_type = '';
$from = '';
$to = '';
$depart_date = '';
$adults = 1;
$children = 0;
$class = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $trip_type = $_POST['trip_type'] ?? '';
    $from = $_POST['from'] ?? '';
    $to = $_POST['to'] ?? '';
    $depart_date = $_POST['depart_date'] ?? '';
    $adults = $_POST['adults'] ?? 1;
    $children = $_POST['children'] ?? 0;
    $class = $_POST['class'] ?? '';
    
    $search_performed = true;
    
    // Search flights based on from and to locations
    if (!empty($from) && !empty($to)) {
        $search_query = "SELECT * FROM flights WHERE departure LIKE ? AND arrival LIKE ?";
        $stmt = $conn->prepare($search_query);
        $from_pattern = "%" . $from . "%";
        $to_pattern = "%" . $to . "%";
        $stmt->bind_param("ss", $from_pattern, $to_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $flights[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="passengerHome.css">
</head>
<body>

<div class="search-container">
    <form action="" method="POST" class="search-bar">
        <div class="trip-type">
            <label for="trip_type">Trip Type</label>
            <select name="trip_type" id="trip_type">
                <option value="one-way" <?= ($trip_type == 'one-way') ? 'selected' : '' ?>>One way</option>
                <option value="return" <?= ($trip_type == 'return') ? 'selected' : '' ?>>Return</option>
            </select>
        </div>

        <div class="input-group">
            <label>From</label>
            <input type="text" name="from" placeholder="Country, city " value="<?= htmlspecialchars($from) ?>" required>
        </div>
        
        <div class="input-group">
            <label>To</label>
            <input type="text" name="to" placeholder="Country, city " value="<?= htmlspecialchars($to) ?>" required>
        </div>

        <div class="input-group">
            <label>Depart</label>
            <input type="text" name="depart_date" placeholder="Add date" value="<?= htmlspecialchars($depart_date) ?>" onfocus="(this.type='date')" required>
        </div>

       
        <div class="input-group">
    <label>Travellers and cabin class</label>

    <!-- Adults (18+) -->
    <input type="number" name="adults" placeholder="Adults (18+)" min="0" max="20" value="<?= htmlspecialchars($adults) ?>" required>

    <!-- Children (under 18) -->
    <input type="number" name="children" placeholder="Children (under 18)" min="0" max="20" value="<?= htmlspecialchars($children) ?>" required>

    <!-- Cabin Class -->
    <select name="class" required>
        <option value="">Cabin Class</option>
        <option value="Economy" <?= ($class == 'Economy') ? 'selected' : '' ?>>Economy</option>
        <option value="Business" <?= ($class == 'Business') ? 'selected' : '' ?>>Business</option>
    </select>
</div>
        <button type="submit" class="search-btn">Search</button>
    </form>
</div>

<!-- Flight Results Section -->
<?php if ($search_performed): ?>
<div class="results-container">
    <h2 class="results-title">Available Flights</h2>
    
    <?php if (count($flights) > 0): ?>
        <div class="flights-list">
            <?php foreach ($flights as $flight): ?>
                <div class="flight-card">
                    <div class="flight-image">
                        <?php if (!empty($flight['image'])): ?>
                            <img src="upload/<?= htmlspecialchars($flight['image']) ?>" alt="<?= htmlspecialchars($flight['flight_name']) ?>">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flight-details">
                        <div class="flight-header">
                            <h3><?= htmlspecialchars($flight['flight_name']) ?></h3>
                            <span class="flight-code"><?= htmlspecialchars($flight['flight_code']) ?></span>
                        </div>
                        
                        <div class="flight-info">
                            <div class="info-item">
                                <span class="label">Airline:</span>
                                <span class="value"><?= htmlspecialchars($flight['airline_name']) ?></span>
                            </div>
                            
                            <div class="route-info">
                                <div class="route-item">
                                    <span class="route-label">From</span>
                                    <span class="route-city"><?= htmlspecialchars($flight['departure']) ?></span>
                                </div>
                                <div class="route-arrow">→</div>
                                <div class="route-item">
                                    <span class="route-label">To</span>
                                    <span class="route-city"><?= htmlspecialchars($flight['arrival']) ?></span>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <span class="label">Duration:</span>
                                <span class="value"><?= htmlspecialchars($flight['duration']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flight-pricing">
                        <div class="price-section">
                            <span class="price-label">Price</span>
                            <span class="price-amount">₹<?= number_format($flight['price'], 2) ?></span>
                        </div>
                        
                        <form action="booked.php" method="POST" class="book-form">
                            <input type="hidden" name="flight_id" value="<?= $flight['id'] ?>">
                            <input type="hidden" name="trip_type" value="<?= htmlspecialchars($trip_type) ?>">
                            <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                            <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                            <input type="hidden" name="depart_date" value="<?= htmlspecialchars($depart_date) ?>">
                            <input type="hidden" name="adults" value="<?= htmlspecialchars($adults) ?>">
                            <input type="hidden" name="children" value="<?= htmlspecialchars($children) ?>">
                            <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">
                            <button type="submit" class="book-btn">Book Ticket</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-flights">
            <p>No flights found matching your search criteria.</p>
            <p>Please try different locations or dates.</p>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>

