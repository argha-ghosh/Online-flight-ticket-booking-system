<?php
// Controller MUST run first — it may call header() to redirect.
include("../controller/AirlineController.php");
$airlines = $airlines ?? [];

// Consume flash message before any output
$flash_msg  = $_SESSION['airline_msg']      ?? '';
$flash_type = $_SESSION['airline_msg_type'] ?? '';
unset($_SESSION['airline_msg'], $_SESSION['airline_msg_type']);

include("../includes/adminheader.php");
?>

<style>
/* ── Page wrapper ── */
.al-page {
    flex: 1;
    padding: 32px 32px 60px;
    max-width: 1400px;
    width: 100%;
    margin: 0 auto;
}

/* ── Title bar ── */
.al-titlebar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.al-titlebar-left { display: flex; align-items: center; gap: 14px; }
.al-titlebar-icon {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, #0b72e6, #6c3de8);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 14px rgba(11,114,230,0.3);
    flex-shrink: 0;
}
.al-titlebar h1 { font-size: 1.4rem; font-weight: 800; color: #0f172a; letter-spacing: -0.4px; }
.al-titlebar p  { font-size: 0.82rem; color: #64748b; margin-top: 2px; }
.al-count-pill {
    background: linear-gradient(135deg, #0b72e6, #6c3de8);
    color: #fff; font-size: 0.8rem; font-weight: 700;
    padding: 6px 16px; border-radius: 20px;
    box-shadow: 0 2px 10px rgba(11,114,230,0.25);
    white-space: nowrap;
}

/* ── Flash ── */
.al-flash {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 18px; border-radius: 12px;
    font-size: 0.88rem; font-weight: 600;
    margin-bottom: 20px;
    animation: fadeSlideIn 0.3s ease;
}
@keyframes fadeSlideIn {
    from { opacity:0; transform:translateY(-8px); }
    to   { opacity:1; transform:translateY(0); }
}
.al-flash.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.al-flash.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
.al-flash .flash-close {
    margin-left: auto; cursor: pointer; opacity: 0.5;
    font-size: 1rem; background: none; border: none;
    color: inherit; padding: 0; font-family: inherit;
}
.al-flash .flash-close:hover { opacity: 1; }

/* ── Layout ── */
.al-layout {
    display: grid;
    grid-template-columns: 390px 1fr;
    gap: 28px;
    align-items: start;
}

/* ══ FORM PANEL ══ */
.al-form-panel {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(11,114,230,0.1);
    border: 1px solid #e8f0fb;
    overflow: hidden;
    position: sticky;
    top: 76px;
}
.al-form-header {
    background: linear-gradient(135deg, #0b72e6, #6c3de8);
    padding: 20px 24px;
    display: flex; align-items: center; gap: 12px;
}
.al-form-header-icon {
    width: 40px; height: 40px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}
.al-form-header h2 { color:#fff; font-size:1rem; font-weight:700; margin:0; }
.al-form-header span { color:rgba(255,255,255,0.7); font-size:0.76rem; display:block; margin-top:2px; }
.al-form-body { padding: 22px 24px 24px; }

/* Field divider label */
.al-section-label {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #94a3b8;
    margin: 18px 0 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.al-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #f1f5f9;
}

/* Input groups */
.al-field { margin-bottom: 14px; }
.al-field label {
    display: block; font-size: 0.74rem; font-weight: 700;
    color: #475569; text-transform: uppercase;
    letter-spacing: 0.6px; margin-bottom: 5px;
}
.al-field label .req { color: #e53e3e; margin-left: 2px; }

.al-input-wrap { position: relative; }
.al-input-wrap .al-ico {
    position: absolute; left: 13px; top: 50%;
    transform: translateY(-50%);
    font-size: 0.9rem; pointer-events: none; line-height: 1; z-index: 1;
}
.al-input-wrap.textarea-wrap .al-ico { top: 12px; transform: none; }

.al-input-wrap input,
.al-input-wrap textarea,
.al-input-wrap select {
    width: 100%;
    padding: 10px 12px 10px 38px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.88rem;
    color: #1e293b;
    background: #f8fafc;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline: none;
    font-family: inherit;
    appearance: none;
}
.al-input-wrap input:focus,
.al-input-wrap textarea:focus,
.al-input-wrap select:focus {
    border-color: #0b72e6;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(11,114,230,0.1);
}
.al-input-wrap input::placeholder,
.al-input-wrap textarea::placeholder { color: #b0bec5; }
.al-input-wrap textarea { resize: vertical; min-height: 80px; padding-top: 10px; }

/* Two-col row inside form */
.al-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* Status select arrow */
.al-select-wrap { position: relative; }
.al-select-wrap::after {
    content: '▾';
    position: absolute; right: 12px; top: 50%;
    transform: translateY(-50%);
    color: #94a3b8; pointer-events: none; font-size: 0.8rem;
}
.al-select-wrap select { padding-right: 30px; cursor: pointer; }

/* Status badge colors in select */
select option[value="active"]   { color: #15803d; }
select option[value="inactive"] { color: #dc2626; }

/* File upload zone */
.al-file-zone {
    border: 2px dashed #c7d8f0; border-radius: 10px;
    padding: 16px 14px; text-align: center;
    background: #f8fafc; cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    position: relative;
}
.al-file-zone:hover { border-color: #0b72e6; background: #f0f7ff; }
.al-file-zone input[type="file"] {
    position: absolute; inset: 0; opacity: 0;
    cursor: pointer; width: 100%; height: 100%;
}
.al-file-zone .fz-icon { font-size: 1.6rem; display: block; margin-bottom: 4px; }
.al-file-zone .fz-text { font-size: 0.78rem; color: #64748b; line-height: 1.5; }
.al-file-zone .fz-text b { color: #0b72e6; }

/* Error box */
#errorMessages {
    background: #fff5f5; border: 1px solid #fecaca;
    border-radius: 10px; padding: 10px 14px;
    font-size: 0.84rem; color: #dc2626;
    margin-bottom: 14px; display: none;
}
#errorMessages:not(:empty) { display: block; }

/* Submit */
.al-submit {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, #0b72e6, #6c3de8);
    color: #fff; border: none; border-radius: 11px;
    font-size: 0.95rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow: 0 4px 16px rgba(11,114,230,0.3);
    margin-top: 10px; font-family: inherit;
}
.al-submit:hover { opacity:0.9; transform:translateY(-2px); box-shadow:0 8px 22px rgba(11,114,230,0.38); }
.al-submit:active { transform:translateY(0); }

/* ══ LIST PANEL ══ */
.al-list-panel {
    background: #fff; border-radius: 20px;
    box-shadow: 0 4px 24px rgba(11,114,230,0.1);
    border: 1px solid #e8f0fb; overflow: hidden;
}
.al-list-header {
    padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
    background: #fafcff;
}
.al-list-header h2 {
    font-size: 1rem; font-weight: 700; color: #0f172a;
    display: flex; align-items: center; gap: 8px;
}
.al-list-header h2::before {
    content: ''; display: inline-block;
    width: 3px; height: 1.1em;
    background: linear-gradient(180deg, #0b72e6, #6c3de8);
    border-radius: 3px;
}
.al-list-body { padding: 20px 24px 24px; }

/* Search bar */
.al-search-wrap {
    position: relative; margin-bottom: 18px;
}
.al-search-wrap input {
    width: 100%; padding: 10px 14px 10px 38px;
    border: 1.5px solid #e2e8f0; border-radius: 10px;
    font-size: 0.88rem; background: #f8fafc;
    outline: none; font-family: inherit; color: #1e293b;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.al-search-wrap input:focus {
    border-color: #0b72e6; background: #fff;
    box-shadow: 0 0 0 3px rgba(11,114,230,0.1);
}
.al-search-wrap .search-ico {
    position: absolute; left: 12px; top: 50%;
    transform: translateY(-50%); font-size: 0.9rem;
    pointer-events: none;
}

/* Grid */
.al-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 18px;
}

/* Card */
.al-card {
    border: 1px solid #e8f0fb; border-radius: 16px;
    overflow: hidden; background: #fff;
    transition: transform 0.22s, box-shadow 0.22s;
    display: flex; flex-direction: column;
}
.al-card:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(11,114,230,0.14); }

.al-card-img {
    height: 100px;
    background: linear-gradient(135deg, #eef4ff 0%, #f3eeff 100%);
    display: flex; align-items: center; justify-content: center;
    padding: 12px; position: relative;
}
.al-card-img img {
    max-width: 72px; max-height: 72px;
    object-fit: contain; border-radius: 10px;
    background: #fff; padding: 6px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}
/* Status badge on card */
.al-status-badge {
    position: absolute; top: 8px; right: 8px;
    font-size: 0.65rem; font-weight: 700;
    padding: 3px 8px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.al-status-badge.active   { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.al-status-badge.inactive { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

.al-card-content {
    padding: 13px 15px 10px; flex: 1;
    display: flex; flex-direction: column; gap: 6px;
}
.al-card-content h3 {
    font-size: 0.93rem; font-weight: 700; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.al-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.al-tag {
    font-size: 0.7rem; font-weight: 600;
    padding: 2px 8px; border-radius: 20px;
    display: inline-flex; align-items: center; gap: 3px;
}
.al-tag.blue  { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
.al-tag.green { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
.al-tag.purple{ background:#f5f3ff; color:#7c3aed; border:1px solid #ddd6fe; }
.al-tag.amber { background:#fffbeb; color:#b45309; border:1px solid #fde68a; }

.al-card-desc {
    font-size: 0.77rem; color: #64748b; line-height: 1.5;
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden; flex: 1;
}
.al-card-website {
    font-size: 0.75rem; color: #0b72e6;
    text-decoration: none; display: flex; align-items: center; gap: 4px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.al-card-website:hover { text-decoration: underline; }

.al-card-actions {
    padding: 10px 14px 13px; display: flex; gap: 8px;
}
.al-btn {
    flex: 1; padding: 8px 0; border-radius: 9px;
    font-size: 0.77rem; font-weight: 600;
    text-decoration: none; text-align: center;
    cursor: pointer; border: 1.5px solid transparent;
    transition: all 0.18s;
    display: flex; align-items: center; justify-content: center; gap: 4px;
    font-family: inherit;
}
.al-btn.edit  { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }
.al-btn.edit:hover  { background:#2563eb; color:#fff; border-color:#2563eb; }
.al-btn.del   { background:#fff5f5; color:#dc2626; border-color:#fecaca; }
.al-btn.del:hover   { background:#dc2626; color:#fff; border-color:#dc2626; }

/* Empty state */
.al-empty {
    grid-column: 1/-1; text-align: center;
    padding: 50px 20px; color: #94a3b8;
}
.al-empty .al-empty-icon { font-size:3rem; display:block; margin-bottom:10px; opacity:0.4; }
.al-empty p { font-size: 0.9rem; }

/* No results (search) */
.al-no-results {
    display: none; grid-column: 1/-1;
    text-align: center; padding: 30px 20px; color: #94a3b8;
    font-size: 0.88rem;
}

/* ── Responsive ── */
@media (max-width: 1000px) {
    .al-layout { grid-template-columns: 1fr; }
    .al-form-panel { position: static; }
}
@media (max-width: 600px) {
    .al-page { padding: 16px 14px 40px; }
    .al-titlebar h1 { font-size: 1.15rem; }
    .al-row { grid-template-columns: 1fr; }
    .al-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
}
</style>

<div class="al-page">

    <!-- Title bar -->
    <div class="al-titlebar">
        <div class="al-titlebar-left">
            <div class="al-titlebar-icon">✈️</div>
            <div>
                <h1>Airline Management</h1>
                <p>Add, edit and manage airlines on the GoZayan platform</p>
            </div>
        </div>
        <span class="al-count-pill">✈ <?= count($airlines) ?> Airline<?= count($airlines) !== 1 ? 's' : '' ?></span>
    </div>

    <!-- Flash message -->
    <?php if ($flash_msg): ?>
        <div class="al-flash <?= htmlspecialchars($flash_type) ?>" id="flashMsg">
            <span><?= $flash_type === 'success' ? '✅' : '❌' ?></span>
            <?= htmlspecialchars($flash_msg) ?>
            <button class="flash-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div class="al-layout">

        <!-- ══ LEFT: Form Panel ══ -->
        <div class="al-form-panel">
            <div class="al-form-header">
                <div class="al-form-header-icon">➕</div>
                <div>
                    <h2>Add New Airline</h2>
                    <span>Fill in the details below</span>
                </div>
            </div>
            <div class="al-form-body">
                <form action="addAirline.php" method="POST" enctype="multipart/form-data" id="airlineForm">
                    <div id="errorMessages"></div>

                    <!-- Basic Info -->
                    <div class="al-section-label">Basic Information</div>

                    <div class="al-field">
                        <label>Airline Name <span class="req">*</span></label>
                        <div class="al-input-wrap">
                            <span class="al-ico">✈️</span>
                            <input type="text" name="airline_name" id="airline_name"
                                   placeholder="Enter airline name" required>
                        </div>
                    </div>

                    <div class="al-row">
                        <div class="al-field">
                            <label>Country <span class="req">*</span></label>
                            <div class="al-input-wrap">
                                <span class="al-ico">🌍</span>
                                <input type="text" name="country_name" id="country_name"
                                       placeholder="Enter country name" required>
                            </div>
                        </div>
                        <div class="al-field">
                            <label>IATA Code <span class="req">*</span></label>
                            <div class="al-input-wrap">
                                <span class="al-ico">🔤</span>
                                <input type="text" name="airline_code" id="airline_code"
                                       placeholder="Enter code" maxlength="3" required>
                            </div>
                        </div>
                    </div>

                    <div class="al-field">
                        <label>Description <span class="req">*</span></label>
                        <div class="al-input-wrap textarea-wrap">
                            <span class="al-ico">📝</span>
                            <textarea name="airline_details" id="airline_details"
                                      placeholder="Brief description of the airline…" required></textarea>
                        </div>
                    </div>

                    <!-- Extra Details -->
                    <div class="al-section-label">Additional Details</div>

                    <div class="al-field">
                        <label>Website</label>
                        <div class="al-input-wrap">
                            <span class="al-ico">🌐</span>
                            <input type="url" name="website" id="website"
                                   placeholder="https://www.airline.com">
                        </div>
                    </div>

                    <div class="al-row">
                        <div class="al-field">
                            <label>Founded Year</label>
                            <div class="al-input-wrap">
                                <span class="al-ico">📅</span>
                                <input type="number" name="founded_year" id="founded_year"
                                       placeholder="Enter founded year" min="1900" max="<?= date('Y') ?>">
                            </div>
                        </div>
                        <div class="al-field">
                            <label>Fleet Size</label>
                            <div class="al-input-wrap">
                                <span class="al-ico">🛩️</span>
                                <input type="number" name="fleet_size" id="fleet_size"
                                       placeholder="85" min="1">
                            </div>
                        </div>
                    </div>

                    <div class="al-field">
                        <label>Status</label>
                        <div class="al-input-wrap al-select-wrap">
                            <span class="al-ico">🔘</span>
                            <select name="status" id="status">
                                <option value="active">✅ Active</option>
                                <option value="inactive">❌ Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Logo -->
                    <div class="al-section-label">Airline Logo</div>

                    <div class="al-field">
                        <div class="al-file-zone" id="fileZone">
                            <input type="file" name="image" id="image" required accept="image/*"
                                   onchange="updateFileLabel(this)">
                            <span class="fz-icon">🖼️</span>
                            <p class="fz-text" id="fileLabel">
                                <b>Click to upload</b> or drag & drop<br>PNG, JPG, WEBP
                            </p>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="al-submit">
                        ➕ Add Airline
                    </button>
                </form>
            </div>
        </div>

        <!-- ══ RIGHT: Airlines List Panel ══ -->
        <div class="al-list-panel">
            <div class="al-list-header">
                <h2>Existing Airlines</h2>
                <span class="al-count-pill"><?= count($airlines) ?> total</span>
            </div>
            <div class="al-list-body">

                <!-- Live search -->
                <div class="al-search-wrap">
                    <span class="search-ico">🔍</span>
                    <input type="text" id="airlineSearch" placeholder="Search airlines by name, country or code…"
                           oninput="filterAirlines(this.value)">
                </div>

                <div class="al-grid" id="airlineGrid">
                    <?php if (empty($airlines)): ?>
                        <div class="al-empty">
                            <span class="al-empty-icon">🛫</span>
                            <p>No airlines yet. Add your first one!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($airlines as $a): ?>
                            <div class="al-card"
                                 data-name="<?= strtolower(htmlspecialchars($a['airline_name'])) ?>"
                                 data-country="<?= strtolower(htmlspecialchars($a['country_name'])) ?>"
                                 data-code="<?= strtolower(htmlspecialchars($a['airline_code'])) ?>">

                                <div class="al-card-img">
                                    <img src="onload/<?= htmlspecialchars(basename($a['image'])) ?>"
                                         alt="<?= htmlspecialchars($a['airline_name']) ?>">
                                    <span class="al-status-badge <?= htmlspecialchars($a['status'] ?? 'active') ?>">
                                        <?= ($a['status'] ?? 'active') === 'active' ? '● Active' : '● Inactive' ?>
                                    </span>
                                </div>

                                <div class="al-card-content">
                                    <h3><?= htmlspecialchars($a['airline_name']) ?></h3>
                                    <div class="al-tags">
                                        <span class="al-tag green">🌍 <?= htmlspecialchars($a['country_name']) ?></span>
                                        <span class="al-tag blue">🔤 <?= htmlspecialchars($a['airline_code']) ?></span>
                                        <?php if (!empty($a['founded_year'])): ?>
                                            <span class="al-tag amber">📅 <?= (int)$a['founded_year'] ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($a['fleet_size'])): ?>
                                            <span class="al-tag purple">🛩️ <?= (int)$a['fleet_size'] ?> aircraft</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="al-card-desc"><?= htmlspecialchars($a['airline_details']) ?></p>
                                    <?php if (!empty($a['website'])): ?>
                                        <a href="<?= htmlspecialchars($a['website']) ?>" target="_blank"
                                           class="al-card-website">🌐 <?= htmlspecialchars($a['website']) ?></a>
                                    <?php endif; ?>
                                </div>

                                <div class="al-card-actions">
                                    <a href="editAirline.php?id=<?= $a['id'] ?>" class="al-btn edit">✏️ Edit</a>
                                    <a href="?delete_id=<?= $a['id'] ?>" class="al-btn del"
                                       onclick="return confirm('Delete <?= htmlspecialchars(addslashes($a['airline_name'])) ?>?')">🗑️ Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="al-no-results" id="noResults">No airlines match your search.</div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
function updateFileLabel(input) {
    const label = document.getElementById('fileLabel');
    const zone  = document.getElementById('fileZone');
    if (input.files && input.files[0]) {
        label.innerHTML = '✅ <b>' + input.files[0].name + '</b>';
        zone.style.borderColor = '#16a34a';
        zone.style.background  = '#f0fdf4';
    }
}

function filterAirlines(query) {
    const q     = query.toLowerCase().trim();
    const cards = document.querySelectorAll('#airlineGrid .al-card');
    const noRes = document.getElementById('noResults');
    let visible = 0;
    cards.forEach(card => {
        const match = !q
            || card.dataset.name.includes(q)
            || card.dataset.country.includes(q)
            || card.dataset.code.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    if (noRes) noRes.style.display = (visible === 0 && q) ? 'block' : 'none';
}

// Auto-dismiss flash after 4s
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.remove(), 4000);
</script>
<script src="../controller/airlineValidation.js"></script>

</body>
</html>

<?php include("../includes/footer.php"); ?>
