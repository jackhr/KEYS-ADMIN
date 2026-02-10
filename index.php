<?php

include_once 'includes/env.php';
include_once 'includes/admin-auth.php';
include_once 'includes/connection.php';

require_admin();

$admin_user = get_admin_user();
$admin_name = htmlspecialchars($admin_user['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

$title_override = "Vehicle Admin Portal";
$page = "admin";
$description = "Manage vehicle details, pricing, and visibility for $company_name.";
$extra_css = "admin";
$extra_js = "admin";

include_once 'includes/header.php';

$vehicles = [];
$vehicles_query = "SELECT * FROM vehicles ORDER BY name ASC";
$vehicles_result = mysqli_query($con, $vehicles_query);
while ($row = mysqli_fetch_assoc($vehicles_result)) $vehicles[] = $row;

$total_count = count($vehicles);
$showing_count = 0;
$landing_count = 0;
foreach ($vehicles as $vehicle) {
    if ($vehicle['showing'] == 1) $showing_count++;
    if ($vehicle['landing_order'] !== null && $vehicle['landing_order'] !== '') $landing_count++;
}

?>

<section class="general-header admin-header">
    <h1>Vehicle Admin</h1>
    <p>Edit your fleet details, pricing, and visibility in one place.</p>
</section>

<section id="admin-portal">
    <div class="inner">
        <div class="admin-user-bar">
            <span>Signed in as <strong><?php echo $admin_name; ?></strong></span>
            <a class="admin-logout" href="/logout.php">Log out</a>
        </div>
        <div class="admin-toolbar">
            <div class="admin-card admin-search">
                <h6>Search Vehicles</h6>
                <div class="admin-search-input">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M21 20.3 16.7 16a7.5 7.5 0 1 0-1 1l4.3 4.3a.7.7 0 0 0 1-1ZM4.5 10.5a6 6 0 1 1 12 0 6 6 0 0 1-12 0Z" />
                    </svg>
                    <input id="vehicle-search" type="text" placeholder="Search by name, type, or slug">
                </div>
            </div>
            <div class="admin-card admin-stats">
                <div class="admin-stat">
                    <span>Total</span>
                    <strong><?php echo $total_count; ?></strong>
                </div>
                <div class="admin-stat">
                    <span>Showing</span>
                    <strong><?php echo $showing_count; ?></strong>
                </div>
                <div class="admin-stat">
                    <span>Landing</span>
                    <strong><?php echo $landing_count; ?></strong>
                </div>
            </div>
        </div>

        <div class="admin-layout">
            <aside class="admin-card vehicle-list">
                <div class="vehicle-list-header">
                    <h2>Vehicles</h2>
                    <span class="vehicle-count"><?php echo $total_count; ?></span>
                </div>
                <div class="vehicle-items">
                    <?php foreach ($vehicles as $vehicle) {
                        $raw_name = $vehicle['name'] ?? '';
                        $raw_type = $vehicle['type'] ?? '';
                        $raw_slug = $vehicle['slug'] ?? '';
                        $vehicle_name = htmlspecialchars($raw_name, ENT_QUOTES, 'UTF-8');
                        $vehicle_type = htmlspecialchars($raw_type, ENT_QUOTES, 'UTF-8');
                        $vehicle_slug = htmlspecialchars($raw_slug, ENT_QUOTES, 'UTF-8');
                        $vehicle_price = htmlspecialchars($vehicle['base_price_USD'] ?? '', ENT_QUOTES, 'UTF-8');
                        $vehicle_people = htmlspecialchars($vehicle['people'] ?? '0', ENT_QUOTES, 'UTF-8');
                        $vehicle_transmission = $vehicle['manual'] == "1" ? "Manual" : "Automatic";
                        $search_key = strtolower(trim("{$raw_name} {$raw_type} {$raw_slug}"));
                        $showing_class = $vehicle['showing'] == "1" ? "tag-on" : "tag-off";
                        $landing_class = ($vehicle['landing_order'] !== null && $vehicle['landing_order'] !== '') ? "tag-on" : "tag-off";
                        $landing_label = ($vehicle['landing_order'] !== null && $vehicle['landing_order'] !== '') ? "Landing #{$vehicle['landing_order']}" : "Landing Hidden";
                    ?>
                        <button class="vehicle-item" type="button" data-vehicle-id="<?php echo (int) $vehicle['id']; ?>" data-search="<?php echo htmlspecialchars($search_key, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="vehicle-item-top">
                                <div>
                                    <span class="vehicle-name"><?php echo $vehicle_name; ?></span>
                                    <span class="vehicle-type"><?php echo $vehicle_type; ?></span>
                                </div>
                                <span class="vehicle-price">USD$<?php echo $vehicle_price; ?>/day</span>
                            </div>
                            <div class="vehicle-item-details">
                                <span class="vehicle-seats"><?php echo $vehicle_people; ?> seats</span>
                                <span class="vehicle-transmission"><?php echo $vehicle_transmission; ?></span>
                            </div>
                            <div class="vehicle-item-tags">
                                <span class="vehicle-tag showing-tag <?php echo $showing_class; ?>">Showing</span>
                                <span class="vehicle-tag landing-tag <?php echo $landing_class; ?>"><?php echo htmlspecialchars($landing_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </button>
                    <?php } ?>
                    <div class="vehicle-empty" hidden>No vehicles match your search.</div>
                </div>
            </aside>

            <div class="admin-card vehicle-editor">
                <div class="editor-empty">
                    <h2>Select a vehicle</h2>
                    <p>Choose a vehicle from the list to edit its details.</p>
                </div>

                <form id="vehicle-form" hidden>
                    <input type="hidden" name="id">

                    <div class="vehicle-preview">
                        <div class="preview-image">
                            <img src="" alt="">
                        </div>
                        <div class="preview-info">
                            <h2 class="preview-title">Vehicle</h2>
                            <p class="preview-subtitle">Type</p>
                            <div class="preview-meta">
                                <span class="preview-price">USD$0/day</span>
                                <span class="vehicle-tag showing-preview tag-off">Showing</span>
                                <span class="vehicle-tag landing-preview tag-off">Landing Hidden</span>
                            </div>
                        </div>
                    </div>

                    <div class="admin-group">
                        <h3>Basics</h3>
                        <div class="admin-grid">
                            <label class="input-container">
                                <h6>Name <sup>*</sup></h6>
                                <input type="text" name="name" required>
                            </label>
                            <label class="input-container">
                                <h6>Type <sup>*</sup></h6>
                                <input type="text" name="type" required>
                            </label>
                            <label class="input-container">
                                <h6>Slug <sup>*</sup></h6>
                                <input type="text" name="slug" required>
                            </label>
                        </div>
                    </div>

                    <div class="admin-group">
                        <h3>Pricing</h3>
                        <div class="admin-grid">
                            <label class="input-container">
                                <h6>Base Price (USD)</h6>
                                <input type="number" name="base_price_USD" min="0" step="1">
                            </label>
                            <label class="input-container">
                                <h6>Insurance / Day (USD)</h6>
                                <input type="number" name="insurance" min="0" step="1">
                            </label>
                        </div>
                    </div>

                    <div class="admin-group">
                        <h3>Capacity</h3>
                        <div class="admin-grid">
                            <label class="input-container">
                                <h6>Seats</h6>
                                <input type="number" name="people" min="0" step="1">
                            </label>
                            <label class="input-container">
                                <h6>Bags</h6>
                                <input type="number" name="bags" min="0" step="1">
                            </label>
                            <label class="input-container">
                                <h6>Doors</h6>
                                <input type="number" name="doors" min="0" step="1">
                            </label>
                        </div>
                    </div>

                    <div class="admin-group">
                        <h3>Features</h3>
                        <div class="admin-grid">
                            <label class="admin-toggle">
                                <input type="checkbox" name="manual">
                                <span>Manual Transmission</span>
                            </label>
                            <label class="admin-toggle">
                                <input type="checkbox" name="ac">
                                <span>Air Conditioning</span>
                            </label>
                            <label class="admin-toggle">
                                <input type="checkbox" name="4wd">
                                <span>4WD</span>
                            </label>
                        </div>
                    </div>

                    <div class="admin-group">
                        <h3>Visibility</h3>
                        <div class="admin-grid">
                            <label class="admin-toggle">
                                <input type="checkbox" name="showing">
                                <span>Visible in Reservations</span>
                            </label>
                            <label class="input-container">
                                <h6>Landing Order</h6>
                                <input type="number" name="landing_order" min="0" step="1" placeholder="Leave blank to hide">
                            </label>
                        </div>
                        <p class="field-hint">Landing order controls the homepage vehicle lineup.</p>
                    </div>

                    <div class="admin-actions">
                        <button type="button" class="continue-btn admin-save" disabled>Save Changes</button>
                        <button type="button" class="admin-reset">Reset</button>
                        <span id="vehicle-status" aria-live="polite"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script id="vehicle-data" type="application/json"><?php echo json_encode($vehicles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>

<?php include_once 'includes/footer.php'; ?>
