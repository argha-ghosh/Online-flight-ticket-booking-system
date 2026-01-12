<?php
// include("../model/db_conn.php");
include("../includes/header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GoZayan | Book Flights Easily</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="home.css">
</head>
<body>

<!-- HERO SECTION -->
<section class="hero">
  <div class="hero-overlay">
    <h1>Fly Smarter with GoZayan</h1>
    <p>Your journey begins here. Book flights faster, cheaper, and easier.</p>
    <a href="login.php" class="btn">Book Your Flight</a>

    <section class="features">
      <h2>Why Choose GoZayan?</h2>

      <div class="feature-box">
        <div class="feature">
          <h3>✈️ Easy Booking</h3>
          <p>Search, compare, and book flights in just a few clicks.</p>
        </div>

        <div class="feature">
          <h3>💳 Secure Payment</h3>
          <p>Your transactions are protected with top-level security.</p>
        </div>

        <div class="feature">
          <h3>📅 Real-Time Updates</h3>
          <p>Get instant updates on flight schedules and availability.</p>
        </div>
      </div>
    </section>
  </div>
</section>


    <!-- QUOTE SECTION -->
    <section class="quote">
        <p>“The world is a book, and those who do not travel read only one page.”</p>
        <span>— Saint Augustine</span>
    </section>

    <!-- CTA SECTION -->
    <section class="cta">
        <h2>Ready to Take Off?</h2>
        <p>Create your account and start booking today.</p>
        <a href="register.php" class="btn">Get Started</a>
    </section>

</body>
</html>

<?php
include("../includes/footer.php");  
?>
