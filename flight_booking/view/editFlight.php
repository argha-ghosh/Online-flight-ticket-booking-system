<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../config/base_url.php";
require_once __DIR__ . "/../model/db_conn.php";

// Guard — must have a valid id
if (empty($_GET['id'])) {
    header("Location: " . BASE_URL . "/view/addFlight.php"); exit;
}

$flight_id = (int)$_GET['id'];

// ── Handle update (PRG) — before any output ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . "/upload/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
    } else {
        $imageName = $_POST['old_image'];
    }

    $id             = !empty($_POST['id']) ? (int)$_POST['id'] : $flight_id;
    $flight_name    = trim($_POST['flight_name']);
    $airline_name   = trim($_POST['airline_name']);
    $flight_code    = trim($_POST['flight_code']);
    $departure      = trim($_POST['departure']);
    $arrival        = trim($_POST['arrival']);
    $departure_time = $_POST['departure_time'] ?? '00:00';
    $arrival_time   = $_POST['arrival_time']   ?? '00:00';
    $duration       = trim($_POST['duration']);
    $price          = (float)$_POST['price'];
    $flight_class   = in_array($_POST['flight_class'] ?? '', ['Economy','Business','First Class']) ? $_POST['flight_class'] : 'Economy';
    $seat_class     = $flight_class; // keep in sync
    $total_seats    = (int)($_POST['total_seats'] ?? 180);
    $seat           = (int)($_POST['seat']        ?? $total_seats);
    $discount_pct   = (float)($_POST['discount_pct'] ?? 0);
    $status         = in_array($_POST['status'] ?? '', ['active','inactive','cancelled']) ? $_POST['status'] : 'active';

    $sql = "UPDATE flights SET
                flight_name=?, airline_name=?, flight_code=?,
                departure=?, arrival=?, departure_time=?, arrival_time=?,
                duration=?, price=?, flight_class=?, seat_class=?,
                total_seats=?, seat=?, discount_pct=?, status=?, image=?
            WHERE id=?";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ssssssssdssiidssi",
            $flight_name, $airline_name, $flight_code,
            $departure, $arrival, $departure_time, $arrival_time,
            $duration, $price, $flight_class, $seat_class,
            $total_seats, $seat, $discount_pct, $status, $imageName,
            $id
        );
        try {
            if ($stmt->execute()) {
                $_SESSION['flight_msg']      = 'Flight updated successfully!';
                $_SESSION['flight_msg_type'] = 'success';
            } else {
                $_SESSION['flight_msg']      = 'DB Execute error: ' . $stmt->error . ' (errno:' . $stmt->errno . ')';
                $_SESSION['flight_msg_type'] = 'error';
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $_SESSION['flight_msg']      = "Error: Flight code '" . htmlspecialchars($flight_code) . "' already exists.";
            } else {
                $_SESSION['flight_msg']      = 'DB Execute error: ' . $e->getMessage();
            }
            $_SESSION['flight_msg_type'] = 'error';
            // If a new image was uploaded but db update failed, clean it up
            if (!empty($_FILES['image']['name'])) {
                $p = $uploadDir . $imageName;
                if (file_exists($p)) unlink($p);
            }
        }
        $stmt->close();
    } else {
        $_SESSION['flight_msg']      = 'DB Prepare error: ' . $conn->error . ' (errno:' . $conn->errno . ')';
        $_SESSION['flight_msg_type'] = 'error';
    }

    header("Location: " . BASE_URL . "/view/addFlight.php"); exit;
}

// ── Fetch flight ─────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM flights WHERE id = ?");
$stmt->bind_param("i", $flight_id);
$stmt->execute();
$flight = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$flight) {
    header("Location: " . BASE_URL . "/view/addFlight.php"); exit;
}

include("../includes/adminheader.php");
?>
<style>
/* ── Page wrapper ── */
.ef-page {
    flex: 1;
    padding: 32px 32px 60px;
    max-width: 900px;
    width: 100%;
    margin: 0 auto;
}

/* ── Back link ── */
.ef-back {
    display: inline-flex; align-items: center; gap: 7px;
    color: #64748b; text-decoration: none;
    font-size: 0.85rem; font-weight: 600;
    margin-bottom: 22px;
    transition: color 0.15s;
}
.ef-back:hover { color: #0b72e6; }

/* ── Title bar ── */
.ef-titlebar {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 28px;
}
.ef-titlebar-icon {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, #0b72e6, #6c3de8);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 14px rgba(11,114,230,0.3); flex-shrink: 0;
}
.ef-titlebar h1 { font-size: 1.4rem; font-weight: 800; color: #0f172a; letter-spacing: -0.4px; }
.ef-titlebar p  { font-size: 0.82rem; color: #64748b; margin-top: 2px; }

/* ── Card ── */
.ef-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(11,114,230,0.1);
    border: 1px solid #e8f0fb;
    overflow: hidden;
}
.ef-card-header {
    background: linear-gradient(135deg, #0b72e6, #6c3de8);
    padding: 20px 28px;
    display: flex; align-items: center; gap: 14px;
}
.ef-card-header-icon {
    width: 44px; height: 44px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.ef-card-header h2 { color: #fff; font-size: 1.05rem; font-weight: 700; margin: 0; }
.ef-card-header span { color: rgba(255,255,255,0.72); font-size: 0.78rem; display: block; margin-top: 2px; }

/* Current image preview */
.ef-preview-bar {
    display: flex; align-items: center; gap: 16px;
    padding: 18px 28px;
    background: #fafcff;
    border-bottom: 1px solid #f1f5f9;
}
.ef-preview-bar img {
    width: 80px; height: 80px; object-fit: cover;
    border-radius: 12px; border: 2px solid #e8f0fb;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.ef-preview-bar .ef-preview-info h4 { font-size: 0.9rem; font-weight: 700; color: #0f172a; }
.ef-preview-bar .ef-preview-info p  { font-size: 0.78rem; color: #64748b; margin-top: 3px; }

.ef-card-body { padding: 28px; }

/* Section label */
.ef-section {
    font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1px; color: #94a3b8;
    margin: 20px 0 14px; display: flex; align-items: center; gap: 8px;
}
.ef-section:first-child { margin-top: 0; }
.ef-section::after { content:''; flex:1; height:1px; background:#f1f5f9; }

/* Fields */
.ef-field { margin-bottom: 16px; }
.ef-field label {
    display: block; font-size: 0.74rem; font-weight: 700;
    color: #475569; text-transform: uppercase;
    letter-spacing: 0.6px; margin-bottom: 6px;
}
.ef-field label .req { color: #e53e3e; margin-left: 2px; }
.ef-wrap { position: relative; }
.ef-wrap .ef-ico {
    position: absolute; left: 13px; top: 50%;
    transform: translateY(-50%);
    font-size: 0.9rem; pointer-events: none; line-height: 1; z-index: 1;
}
.ef-wrap.ta-wrap .ef-ico { top: 12px; transform: none; }
.ef-wrap input,
.ef-wrap textarea,
.ef-wrap select {
    width: 100%; padding: 11px 13px 11px 40px;
    border: 1.5px solid #e2e8f0; border-radius: 10px;
    font-size: 0.9rem; color: #1e293b; background: #f8fafc;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline: none; font-family: inherit; appearance: none;
}
.ef-wrap input:focus,
.ef-wrap textarea:focus,
.ef-wrap select:focus {
    border-color: #0b72e6; background: #fff;
    box-shadow: 0 0 0 3px rgba(11,114,230,0.1);
}
.ef-wrap input::placeholder { color: #b0bec5; }
/* Select arrow */
.ef-sel-wrap { position: relative; }
.ef-sel-wrap::after {
    content: '▾'; position: absolute; right: 12px; top: 50%;
    transform: translateY(-50%); color: #94a3b8;
    pointer-events: none; font-size: 0.8rem;
}
.ef-sel-wrap select { padding-right: 30px; cursor: pointer; }
/* Two-col row */
.ef-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* File upload zone */
.ef-file-zone {
    border: 2px dashed #c7d8f0; border-radius: 10px;
    padding: 18px 14px; text-align: center; background: #f8fafc;
    cursor: pointer; transition: border-color 0.2s, background 0.2s; position: relative;
}
.ef-file-zone:hover { border-color: #0b72e6; background: #f0f7ff; }
.ef-file-zone input[type="file"] {
    position: absolute; inset: 0; opacity: 0;
    cursor: pointer; width: 100%; height: 100%;
}
.ef-file-zone .fz-icon { font-size: 1.6rem; display: block; margin-bottom: 4px; }
.ef-file-zone .fz-text { font-size: 0.78rem; color: #64748b; line-height: 1.5; }
.ef-file-zone .fz-text b { color: #0b72e6; }

/* Action buttons */
.ef-actions {
    display: flex; gap: 12px; margin-top: 28px;
    padding-top: 22px; border-top: 1px solid #f1f5f9;
}
.ef-btn-update {
    flex: 1; padding: 13px;
    background: linear-gradient(135deg, #0b72e6, #6c3de8);
    color: #fff; border: none; border-radius: 11px;
    font-size: 0.95rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow: 0 4px 16px rgba(11,114,230,0.3); font-family: inherit;
}
.ef-btn-update:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 8px 22px rgba(11,114,230,0.38); }
.ef-btn-update:active { transform: translateY(0); }
.ef-btn-cancel {
    padding: 13px 24px;
    background: #f8fafc; color: #64748b;
    border: 1.5px solid #e2e8f0; border-radius: 11px;
    font-size: 0.95rem; font-weight: 600; cursor: pointer;
    text-decoration: none; display: flex; align-items: center; gap: 7px;
    transition: background 0.15s, color 0.15s; font-family: inherit;
}
.ef-btn-cancel:hover { background: #f1f5f9; color: #334155; }

/* Error box */
#efErrorMessages {
    background: #fff5f5; border: 1px solid #fecaca; border-radius: 10px;
    padding: 10px 14px; font-size: 0.84rem; color: #dc2626;
    margin-bottom: 16px; display: none;
}
#efErrorMessages:not(:empty) { display: block; }

/* Responsive */
@media (max-width: 640px) {
    .ef-page { padding: 16px 14px 40px; }
    .ef-row { grid-template-columns: 1fr; }
    .ef-actions { flex-direction: column; }
}
</style>

<div class="ef-page">

    <!-- Back -->
    <a href="<?= BASE_URL ?>/view/addFlight.php" class="ef-back">← Back to Flights</a>

    <!-- Title -->
    <div class="ef-titlebar">
        <div class="ef-titlebar-icon">✏️</div>
        <div>
            <h1>Edit Flight</h1>
            <p>Update the details for <strong><?= htmlspecialchars($flight['flight_name']) ?></strong></p>
        </div>
    </div>

    <!-- Card -->
    <div class="ef-card">
        <div class="ef-card-header">
            <div class="ef-card-header-icon">🛫</div>
            <div>
                <h2><?= htmlspecialchars($flight['flight_name']) ?></h2>
                <span><?= htmlspecialchars($flight['departure']) ?> → <?= htmlspecialchars($flight['arrival']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($flight['flight_code']) ?></span>
            </div>
        </div>

        <!-- Current image preview -->
        <div class="ef-preview-bar">
            <img src="upload/<?= htmlspecialchars($flight['image']) ?>"
                 alt="<?= htmlspecialchars($flight['flight_name']) ?>" id="efImgPreview">
            <div class="ef-preview-info">
                <h4>Current Image</h4>
                <p>Upload a new file below to replace it</p>
            </div>
        </div>

        <div class="ef-card-body">
            <form action="<?= BASE_URL ?>/view/editFlight.php?id=<?= (int)$flight['id'] ?>" method="POST"
                  enctype="multipart/form-data" id="editFlightForm">

                <input type="hidden" name="id"           value="<?= (int)$flight['id'] ?>">
                <input type="hidden" name="old_image"    value="<?= htmlspecialchars($flight['image']) ?>">
                <input type="hidden" name="seat"         value="<?= (int)($flight['seat'] ?? $flight['total_seats'] ?? 0) ?>">
                <input type="hidden" name="discount_pct" value="<?= (float)($flight['discount_pct'] ?? 0) ?>">

                <div id="efErrorMessages"></div>

                <!-- Flight Identity -->
                <div class="ef-section">Flight Identity</div>

                <div class="ef-field">
                    <label>Flight Name <span class="req">*</span></label>
                    <div class="ef-wrap">
                        <span class="ef-ico">✈️</span>
                        <input type="text" name="flight_name" id="flight_name"
                               value="<?= htmlspecialchars($flight['flight_name']) ?>" required>
                    </div>
                </div>

                <div class="ef-row">
                    <div class="ef-field">
                        <label>Airline Name <span class="req">*</span></label>
                        <div class="ef-wrap">
                            <span class="ef-ico">🏢</span>
                            <input type="text" name="airline_name" id="airline_name"
                                   value="<?= htmlspecialchars($flight['airline_name']) ?>" required>
                        </div>
                    </div>
                    <div class="ef-field">
                        <label>Flight Code <span class="req">*</span></label>
                        <div class="ef-wrap">
                            <span class="ef-ico">🔤</span>
                            <input type="text" name="flight_code" id="flight_code"
                                   value="<?= htmlspecialchars($flight['flight_code']) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Route & Schedule -->
                <div class="ef-section">Route &amp; Schedule</div>

                <div class="ef-row">
                    <div class="ef-field">
                        <label>Departure City <span class="req">*</span></label>
                        <div class="ef-wrap">
                            <span class="ef-ico">🛫</span>
                            <input type="text" name="departure" id="departure"
                                   value="<?= htmlspecialchars($flight['departure']) ?>" required>
                        </div>
                    </div>
                    <div class="ef-field">
                        <label>Arrival City <span class="req">*</span></label>
                        <div class="ef-wrap">
                            <span class="ef-ico">🛬</span>
                            <input type="text" name="arrival" id="arrival"
                                   value="<?= htmlspecialchars($flight['arrival']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="ef-row">
                    <div class="ef-field">
                        <label>Departure Time <span class="req">*</span></label>
                        <div class="ef-wrap">
                            <span class="ef-ico">🕐</span>
                            <input type="time" name="departure_time" id="departure_time"
                                   value="<?= !empty($flight['departure_time']) ? date('H:i', strtotime($flight['departure_time'])) : '' ?>" required>
                        </div>
                    </div>
                    <div class="ef-field">
                        <label>Arrival Time <span class="req">*</span></label>
                        <div class="ef-wrap">
                            <span class="ef-ico">🕔</span>
                            <input type="time" name="arrival_time" id="arrival_time"
                                   value="<?= !empty($flight['arrival_time']) ? date('H:i', strtotime($flight['arrival_time'])) : '' ?>" required>
                        </div>
                    </div>
                </div>

                <div class="ef-field">
                    <label>Duration <span class="req">*</span></label>
                    <div class="ef-wrap">
                        <span class="ef-ico">⏱️</span>
                        <input type="text" name="duration" id="duration"
                               value="<?= htmlspecialchars($flight['duration']) ?>" required>
                    </div>
                </div>

                <!-- Pricing & Class -->
                <div class="ef-section">Pricing &amp; Class</div>

                <div class="ef-row">
                    <div class="ef-field">
                        <label>Price (BDT) <span class="req">*</span></label>
                        <div class="ef-wrap">
                            <span class="ef-ico">💵</span>
                            <input type="number" step="0.01" min="0" name="price" id="price"
                                   value="<?= htmlspecialchars($flight['price']) ?>" required>
                        </div>
                    </div>
                    <div class="ef-field">
                        <label>Total Seats <span class="req">*</span></label>
                        <div class="ef-wrap">
                            <span class="ef-ico">💺</span>
                            <input type="number" min="1" name="total_seats" id="total_seats"
                                   value="<?= htmlspecialchars($flight['total_seats'] ?? 180) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="ef-row">
                    <div class="ef-field">
                        <label>Class <span class="req">*</span></label>
                        <div class="ef-wrap ef-sel-wrap">
                            <span class="ef-ico">🎫</span>
                            <select name="flight_class" id="flight_class">
                                <?php foreach (['Economy','Business','First Class'] as $cls): ?>
                                    <option value="<?= $cls ?>"
                                        <?= ($flight['flight_class'] ?? 'Economy') === $cls ? 'selected' : '' ?>>
                                        <?= $cls ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="ef-field">
                        <label>Status</label>
                        <div class="ef-wrap ef-sel-wrap">
                            <span class="ef-ico">🔘</span>
                            <select name="status" id="status">
                                <?php foreach (['active' => '✅ Active', 'inactive' => '❌ Inactive', 'cancelled' => '⚠️ Cancelled'] as $val => $label): ?>
                                    <option value="<?= $val ?>"
                                        <?= ($flight['status'] ?? 'active') === $val ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Image -->
                <div class="ef-section">Replace Image <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#b0bec5">(optional)</span></div>

                <div class="ef-field">
                    <div class="ef-file-zone" id="efFileZone">
                        <input type="file" name="image" id="image" accept="image/*"
                               onchange="previewEfImage(this)">
                        <span class="fz-icon">🖼️</span>
                        <p class="fz-text" id="efFileLabel">
                            <b>Click to upload</b> a new image<br>Leave empty to keep current
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="ef-actions">
                    <a href="<?= BASE_URL ?>/view/addFlight.php" class="ef-btn-cancel">✕ Cancel</a>
                    <button type="submit" name="update" class="ef-btn-update">
                        💾 Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<script>
function previewEfImage(input) {
    const label   = document.getElementById('efFileLabel');
    const zone    = document.getElementById('efFileZone');
    const preview = document.getElementById('efImgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
        label.innerHTML = '✅ <b>' + input.files[0].name + '</b>';
        zone.style.borderColor = '#16a34a';
        zone.style.background  = '#f0fdf4';
    }
}
</script>
<script src="../controller/editFlightValidation.js?v=<?= time() ?>"></script>

</body>
</html>
<?php include("../includes/footer.php"); ?>
