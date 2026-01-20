session_start();
require_once "../model/FlightModel.php";
require_once "../includes/managerheader.php";

$flightModel = new FlightModel();
$flights = $flightModel->getAllFlights();
?>
<?php
// manageSeat.php removed — undo placeholder
// The detailed view created previously has been reverted.
echo "This page has been removed.";
exit;
