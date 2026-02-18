<?php

include_once 'includes/env.php';
include_once 'includes/admin-auth.php';
include_once 'includes/connection.php';

require_admin();

function table_exists($con, $table)
{
    $safe_table = mysqli_real_escape_string($con, (string) $table);
    $result = mysqli_query($con, "SHOW TABLES LIKE '{$safe_table}'");
    if (!$result) return false;
    return $result->num_rows > 0;
}

function get_table_columns($con, $table)
{
    $columns = [];
    $result = mysqli_query($con, "SHOW COLUMNS FROM `{$table}`");
    if (!$result) return $columns;
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['Field'])) $columns[] = $row['Field'];
    }
    return $columns;
}

function pick_first_column($columns, $candidates)
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) return $candidate;
    }
    return null;
}

function format_column_label($column)
{
    $label = str_replace('_', ' ', (string) $column);
    $label = preg_replace('/\s+/', ' ', $label);
    return strtoupper(trim($label));
}

function build_search_value($row, $columns)
{
    $parts = [];
    foreach ($columns as $column) {
        if (isset($row[$column])) $parts[] = (string) $row[$column];
    }
    return strtolower(trim(implode(' ', $parts)));
}

function format_money($value, $currency = 'USD')
{
    $number = is_numeric($value) ? (float) $value : 0;
    return $currency . '$' . number_format($number, 2, '.', ',');
}

function detect_value_kind($column_name)
{
    $name = strtolower((string) $column_name);
    if (strpos($name, 'percent') !== false || strpos($name, 'percentage') !== false || strpos($name, 'rate') !== false) {
        return 'percent';
    }
    if (strpos($name, 'amount') !== false || strpos($name, 'price') !== false) {
        return 'amount';
    }
    return 'amount';
}

function resolve_vehicle_image_url($slug)
{
    $safe_slug = trim((string) $slug);
    if ($safe_slug === '') return '/assets/images/logo.avif';

    $base_dir = __DIR__ . '/assets/images/vehicles';
    $extensions = ['avif', 'webp', 'jpg', 'jpeg', 'png'];
    foreach ($extensions as $ext) {
        $file = $base_dir . '/' . $safe_slug . '.' . $ext;
        if (file_exists($file)) {
            return '/assets/images/vehicles/' . $safe_slug . '.' . $ext;
        }
    }

    return '/assets/images/logo.avif';
}

function normalize_discount_row($row, $field_map)
{
    if (!$row || !$field_map) return null;
    $normalized = [
        'price_USD' => $field_map['price_USD'] ? ($row[$field_map['price_USD']] ?? null) : null,
        'price_XCD' => $field_map['price_XCD'] ? ($row[$field_map['price_XCD']] ?? null) : null,
        'days' => $field_map['days'] ? ($row[$field_map['days']] ?? null) : null
    ];
    return $normalized;
}

function format_discount_summary($discount, $value_kind, $currency = 'USD')
{
    if (!$discount) return 'No discount';
    $value_usd = $discount['price_USD'] ?? null;
    $value_xcd = $discount['price_XCD'] ?? null;
    $value = null;
    $currency_label = $currency;

    if (is_numeric($value_usd)) {
        $value = (float) $value_usd;
        $currency_label = 'USD';
    } elseif (is_numeric($value_xcd)) {
        $value = (float) $value_xcd;
        $currency_label = 'XCD';
    }

    if ($value === null) return 'No discount';

    if (isset($discount['days']) && is_numeric($discount['days'])) {
        $days = (int) $discount['days'];
        return format_money($value, $currency_label) . " for {$days} days";
    }

    return format_money($value, $currency_label) . ' off';
}

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
foreach ($vehicles as &$vehicle) {
    $vehicle['image_url'] = resolve_vehicle_image_url($vehicle['slug'] ?? '');
}
unset($vehicle);

$vehicle_columns = get_table_columns($con, 'vehicles');

$total_count = count($vehicles);
$showing_count = 0;
$landing_count = 0;
foreach ($vehicles as $vehicle) {
    if ($vehicle['showing'] == 1) $showing_count++;
    if ($vehicle['landing_order'] !== null && $vehicle['landing_order'] !== '') $landing_count++;
}

$orders = [];
$orders_error = '';
$order_columns = [];
$order_display_columns = [];
$order_search_columns = [];
$order_total_column = null;
$order_stats = [
    'total' => 0,
    'pending' => 0,
    'revenue' => 0
];
$order_addons = [];
$order_has_status = false;
$order_has_history = false;

if (table_exists($con, 'order_requests')) {
    $order_columns = get_table_columns($con, 'order_requests');
    $order_has_status = in_array('status', $order_columns, true);
    $order_has_history = table_exists($con, 'order_request_history');
    $order_status_select = $order_has_status ? ", o.status" : "";
    $orders_query = "
        SELECT
            o.id,
            o.created_at,
            o.pick_up,
            o.drop_off,
            o.pick_up_location,
            o.drop_off_location,
            o.confirmed,
            o.sub_total,
            o.days,
            o.car_id,
            o.contact_info_id
            {$order_status_select},
            v.name AS vehicle_name,
            v.type AS vehicle_type,
            v.slug AS vehicle_slug,
            v.base_price_USD AS vehicle_base_price,
            v.people AS vehicle_people,
            v.bags AS vehicle_bags,
            v.doors AS vehicle_doors,
            v.manual AS vehicle_manual,
            v.ac AS vehicle_ac,
            v.`4wd` AS vehicle_4wd,
            c.first_name,
            c.last_name,
            c.driver_license,
            c.hotel,
            c.country_or_region,
            c.street,
            c.town_or_city,
            c.state_or_county,
            c.email,
            c.phone
        FROM order_requests o
        LEFT JOIN vehicles v ON v.id = o.car_id
        LEFT JOIN contact_info c ON c.id = o.contact_info_id
        ORDER BY o.created_at DESC
        LIMIT 250
    ";
    $orders_result = mysqli_query($con, $orders_query);
    if ($orders_result) {
        while ($row = mysqli_fetch_assoc($orders_result)) {
            $row['customer_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if (!$order_has_status) {
                $row['status'] = ((int) ($row['confirmed'] ?? 0) === 1) ? 'confirmed' : 'pending';
            } else {
                $row['status'] = $row['status'] ?? 'pending';
            }
            $orders[] = $row;
        }
    }

    if (table_exists($con, 'order_request_add_ons') && table_exists($con, 'add_ons')) {
        $addons_query = "
            SELECT
                ora.order_request_id,
                ora.quantity,
                ao.name,
                ao.cost,
                ao.fixed_price
            FROM order_request_add_ons ora
            INNER JOIN add_ons ao ON ao.id = ora.add_on_id
            ORDER BY ora.order_request_id ASC
        ";
        $addons_result = mysqli_query($con, $addons_query);
        if ($addons_result) {
            while ($addon_row = mysqli_fetch_assoc($addons_result)) {
                $order_id = (int) ($addon_row['order_request_id'] ?? 0);
                if ($order_id <= 0) continue;
                if (!isset($order_addons[$order_id])) $order_addons[$order_id] = [];
                $order_addons[$order_id][] = $addon_row;
            }
        }
    }

    foreach ($orders as &$order) {
        $order_id = (int) ($order['id'] ?? 0);
        $order['add_ons'] = $order_id > 0 ? ($order_addons[$order_id] ?? []) : [];
    }
    unset($order);

    $order_display_columns = [
        'id',
        'created_at',
        'customer_name',
        'email',
        'phone',
        'vehicle_name',
        'pick_up',
        'drop_off',
        'days',
        'sub_total',
        'status'
    ];

    $order_search_columns = [
        'id',
        'customer_name',
        'email',
        'phone',
        'vehicle_name',
        'pick_up_location',
        'drop_off_location'
    ];

    $order_total_column = 'sub_total';

    $order_stats['total'] = count($orders);
    foreach ($orders as $order) {
        $status_value = strtolower(trim((string) ($order['status'] ?? 'pending')));
        if (in_array($status_value, ['pending', 'new', 'open'], true)) {
            $order_stats['pending']++;
        }
        if (is_numeric($order['sub_total'] ?? null)) {
            $order_stats['revenue'] += (float) $order['sub_total'];
        }
    }
} else {
    $orders_error = 'Order requests table not found.';
}

$addons = [];
$addons_error = '';
$addon_columns = [];
$addon_id_field = null;
$addon_field_map = [
    'name' => null,
    'price' => null,
    'description' => null,
    'active' => null,
    'per_day' => null,
    'sort_order' => null
];
$addons_total = 0;
$addons_active = 0;

if (table_exists($con, 'add_ons')) {
    $addon_columns = get_table_columns($con, 'add_ons');
    $addon_id_field = pick_first_column($addon_columns, ['id', 'addon_id']);
    $addon_field_map['name'] = pick_first_column($addon_columns, ['name', 'title']);
    $addon_field_map['price'] = pick_first_column($addon_columns, ['cost', 'price_USD', 'price', 'amount', 'daily_price']);
    $addon_field_map['description'] = pick_first_column($addon_columns, ['description', 'details', 'notes']);
    $addon_field_map['active'] = null;
    $addon_field_map['per_day'] = pick_first_column($addon_columns, ['fixed_price']);
    $addon_field_map['sort_order'] = null;

    if (!$addon_id_field) {
        $addons_error = 'Add-ons table is missing an id field.';
    } elseif (!$addon_field_map['name']) {
        $addons_error = 'Add-ons table is missing a name field.';
    } else {
        $addons_query = "SELECT * FROM add_ons ORDER BY `{$addon_field_map['name']}` ASC";
        $addons_result = mysqli_query($con, $addons_query);
        if ($addons_result) {
            while ($row = mysqli_fetch_assoc($addons_result)) $addons[] = $row;
        }

        $addons_total = count($addons);
        if ($addon_field_map['active']) {
            foreach ($addons as $addon) {
                if (($addon[$addon_field_map['active']] ?? 0) == 1) $addons_active++;
            }
        }
    }
} else {
    $addons_error = 'Add-ons table not found.';
}

$discounts = [];
$discounts_error = '';
$discount_mode = 'none';
$discount_field_map = [
    'id' => null,
    'vehicle_id' => null,
    'price_USD' => null,
    'price_XCD' => null,
    'days' => null
];
$discount_value_label = 'Discount Value';
$discount_value_kind = 'amount';
$discounts_payload = [];
$discount_stats = [
    'total' => count($vehicles),
    'discounted' => 0,
    'active' => 0
];
$discount_currency = 'USD';

if (table_exists($con, 'vehicle_discounts')) {
    $discount_columns = get_table_columns($con, 'vehicle_discounts');
    $discount_field_map['id'] = pick_first_column($discount_columns, ['id']);
    $discount_field_map['vehicle_id'] = pick_first_column($discount_columns, ['vehicle_id', 'vehicle']);
    $discount_field_map['price_USD'] = pick_first_column($discount_columns, ['price_USD']);
    $discount_field_map['price_XCD'] = pick_first_column($discount_columns, ['price_XCD']);
    $discount_field_map['days'] = pick_first_column($discount_columns, ['days']);

    if ($discount_field_map['vehicle_id'] && ($discount_field_map['price_USD'] || $discount_field_map['price_XCD'])) {
        $discount_mode = 'table';
    } else {
        $discounts_error = 'Vehicle discounts table is missing required fields.';
    }
}

if ($discount_mode !== 'none') {
    $discount_value_kind = 'amount';
    if ($discount_field_map['price_USD'] && $discount_field_map['price_XCD']) {
        $discount_value_label = 'Discount Prices';
        $discount_currency = 'USD';
    } elseif ($discount_field_map['price_XCD']) {
        $discount_currency = 'XCD';
        $discount_value_label = 'Discount Price (XCD)';
    } else {
        $discount_currency = 'USD';
        $discount_value_label = 'Discount Price (USD)';
    }

    if ($discount_mode === 'table') {
        $discount_map = [];
        $discounts_query = "SELECT * FROM vehicle_discounts ORDER BY id DESC";
        $discounts_result = mysqli_query($con, $discounts_query);
        if ($discounts_result) {
            while ($row = mysqli_fetch_assoc($discounts_result)) {
                $vehicle_id = (int) ($row[$discount_field_map['vehicle_id']] ?? 0);
                if ($vehicle_id <= 0) continue;
                if (!isset($discount_map[$vehicle_id])) {
                    $discount_map[$vehicle_id] = $row;
                }
            }
        }

        foreach ($vehicles as $vehicle) {
            $vehicle_id = (int) ($vehicle['id'] ?? 0);
            $discount_row = $discount_map[$vehicle_id] ?? null;
            $normalized = normalize_discount_row($discount_row, $discount_field_map);
            $discount_value_usd = $normalized['price_USD'] ?? null;
            $discount_value_xcd = $normalized['price_XCD'] ?? null;
            $has_discount = is_numeric($discount_value_usd) || is_numeric($discount_value_xcd);

            if ($normalized && $has_discount) {
                $discount_stats['discounted']++;
                $discount_stats['active']++;
            }

            $discounts[] = [
                'vehicle' => $vehicle,
                'vehicle_id' => $vehicle_id,
                'discount' => $normalized
            ];
        }
    }
}

$orders_config = [
    'display_columns' => $order_display_columns,
    'search_columns' => $order_search_columns,
    'total_column' => $order_total_column,
    'has_status' => $order_has_status,
    'has_history' => $order_has_history,
    'error' => $orders_error
];

$addons_config = [
    'field_map' => $addon_field_map,
    'id_field' => $addon_id_field,
    'error' => $addons_error
];

$discounts_payload = $discounts;
$discounts_config = [
    'mode' => $discount_mode,
    'field_map' => $discount_field_map,
    'value_label' => $discount_value_label,
    'value_kind' => $discount_value_kind,
    'currency' => $discount_currency,
    'error' => $discounts_error
];

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
        <div class="admin-tabs">
            <button class="admin-tab active" type="button" data-section="vehicles">Vehicles</button>
            <button class="admin-tab" type="button" data-section="orders">Orders</button>
            <button class="admin-tab" type="button" data-section="addons">Add-ons</button>
            <button class="admin-tab" type="button" data-section="discounts">Discounts</button>
        </div>
        <div class="admin-section" data-section="vehicles">
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
                    <div class="vehicle-list-actions">
                        <span class="vehicle-count" id="vehicle-count"><?php echo $total_count; ?></span>
                        <button type="button" class="admin-add-btn" id="vehicle-add">Add Vehicle</button>
                    </div>
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
                        <h3>Image</h3>
                        <div class="admin-grid">
                            <label class="input-container">
                                <h6>Upload Vehicle Image</h6>
                                <input type="file" name="image_file" accept=".avif,.webp,.jpg,.jpeg,.png,image/avif,image/webp,image/jpeg,image/png">
                            </label>
                        </div>
                        <p class="field-hint">Image file is saved using the current slug when you click Save Changes.</p>
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
                        <button type="button" class="admin-delete">Delete Vehicle</button>
                        <span id="vehicle-status" aria-live="polite"></span>
                    </div>
                </form>
            </div>
        </div>
        </div>

        <div class="admin-section" data-section="orders" hidden>
            <div class="admin-toolbar">
                <div class="admin-card admin-search">
                    <h6>Search Orders</h6>
                    <div class="admin-search-input">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M21 20.3 16.7 16a7.5 7.5 0 1 0-1 1l4.3 4.3a.7.7 0 0 0 1-1ZM4.5 10.5a6 6 0 1 1 12 0 6 6 0 0 1-12 0Z" />
                        </svg>
                        <input id="order-search" type="text" placeholder="Search by name, email, status, or vehicle">
                    </div>
                </div>
                <div class="admin-card admin-stats">
                    <div class="admin-stat">
                        <span>Total</span>
                        <strong><?php echo $order_stats['total']; ?></strong>
                    </div>
                    <div class="admin-stat">
                        <span>Pending</span>
                        <strong><?php echo $order_stats['pending']; ?></strong>
                    </div>
                    <div class="admin-stat">
                        <span>Revenue</span>
                        <strong><?php echo format_money($order_stats['revenue']); ?></strong>
                    </div>
                </div>
            </div>

            <div class="admin-card admin-table-card">
                <?php if ($orders_error !== '') { ?>
                    <div class="admin-empty-state">
                        <h3>Orders unavailable</h3>
                        <p><?php echo htmlspecialchars($orders_error, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php } elseif (count($orders) === 0) { ?>
                    <div class="admin-empty-state">
                        <h3>No orders yet</h3>
                        <p>Orders will appear here once customers submit reservations.</p>
                    </div>
                <?php } else { ?>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <?php foreach ($order_display_columns as $column) { ?>
                                        <th><?php echo htmlspecialchars(format_column_label($column), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order) {
                                    $search_value = build_search_value($order, $order_search_columns);
                                ?>
                                    <tr class="order-row" data-order-id="<?php echo (int) ($order['id'] ?? 0); ?>" data-search="<?php echo htmlspecialchars($search_value, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php foreach ($order_display_columns as $column) {
                                            $value = $order[$column] ?? '';
                                            if ($order_total_column && $column === $order_total_column && is_numeric($value)) {
                                                $value = format_money($value);
                                            }
                                            if ($column === 'status') {
                                                $value = ucfirst((string) $value);
                                            }
                                        ?>
                                            <td><?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="admin-table-controls">
                        <div class="admin-page-size">
                            <label for="order-page-size">Rows</label>
                            <select id="order-page-size">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="admin-pagination">
                            <button type="button" id="order-prev">Prev</button>
                            <span id="order-page-info">Page 1</span>
                            <button type="button" id="order-next">Next</button>
                        </div>
                    </div>
                    <div class="table-empty" hidden>No orders match your search.</div>
                <?php } ?>
            </div>

            <div class="admin-modal" id="order-modal" hidden>
                <div class="admin-modal-overlay" data-modal-close></div>
                    <div class="admin-modal-content">
                        <button type="button" class="admin-modal-close" data-modal-close>Close</button>
                    <div class="order-details" id="order-details">
                        <h3>Order Details</h3>
                        <div class="order-vehicle-preview">
                            <div class="order-vehicle-image">
                                <img id="order-detail-image" src="/assets/images/logo.avif" alt="Vehicle image">
                            </div>
                            <div class="order-vehicle-info">
                                <div>
                                    <h4 id="order-detail-vehicle-name">Vehicle</h4>
                                    <p id="order-detail-vehicle-type">Type</p>
                                </div>
                                <div class="order-vehicle-meta">
                                    <span id="order-detail-vehicle-price">USD$0/day</span>
                                    <span id="order-detail-vehicle-capacity">— seats</span>
                                    <span id="order-detail-vehicle-features">—</span>
                                </div>
                            </div>
                        </div>
                        <form id="order-form">
                            <input type="hidden" name="id">

                            <div class="admin-group">
                                <h3>Order Status</h3>
                                <div class="admin-grid">
                                    <label class="input-container">
                                        <h6>Status</h6>
                                        <select name="status" id="order-status-select">
                                            <option value="pending">Pending</option>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="cancelled">Cancelled</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </label>
                                </div>
                            </div>

                            <div class="admin-group">
                                <h3>Order Summary</h3>
                                <div class="admin-grid">
                                    <div class="input-container">
                                        <h6>Vehicle</h6>
                                        <p class="static-value" id="order-detail-vehicle">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Days</h6>
                                        <p class="static-value" id="order-detail-days">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Subtotal (USD)</h6>
                                        <p class="static-value" id="order-detail-subtotal">—</p>
                                    </div>
                                </div>
                            </div>

                            <div class="admin-group">
                                <h3>Schedule</h3>
                                <div class="admin-grid">
                                    <div class="input-container">
                                        <h6>Pick Up</h6>
                                        <p class="static-value" id="order-detail-pickup">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Drop Off</h6>
                                        <p class="static-value" id="order-detail-dropoff">—</p>
                                    </div>
                                </div>
                            </div>

                            <div class="admin-group">
                                <h3>Locations</h3>
                                <div class="admin-grid">
                                    <div class="input-container">
                                        <h6>Pick Up Location</h6>
                                        <p class="static-value" id="order-detail-pickup-location">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Drop Off Location</h6>
                                        <p class="static-value" id="order-detail-dropoff-location">—</p>
                                    </div>
                                </div>
                            </div>

                            <div class="admin-group">
                                <h3>Customer Info</h3>
                                <div class="admin-grid">
                                    <div class="input-container">
                                        <h6>Customer</h6>
                                        <p class="static-value" id="order-detail-customer">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Email</h6>
                                        <p class="static-value" id="order-detail-email">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Phone</h6>
                                        <p class="static-value" id="order-detail-phone">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Driver License</h6>
                                        <p class="static-value" id="order-detail-driver-license">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Hotel</h6>
                                        <p class="static-value" id="order-detail-hotel">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Country / Region</h6>
                                        <p class="static-value" id="order-detail-country">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>Street</h6>
                                        <p class="static-value" id="order-detail-street">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>City / Town</h6>
                                        <p class="static-value" id="order-detail-town">—</p>
                                    </div>
                                    <div class="input-container">
                                        <h6>State / County</h6>
                                        <p class="static-value" id="order-detail-state">—</p>
                                    </div>
                                </div>
                            </div>

                            <div class="admin-actions">
                                <button type="button" class="continue-btn order-save" disabled>Save Order</button>
                                <button type="button" class="admin-reset order-reset">Reset</button>
                                <span id="order-status" aria-live="polite"></span>
                            </div>
                        </form>

                        <div class="order-addons">
                            <h4>Add-ons</h4>
                            <div id="order-detail-addons">No add-ons.</div>
                        </div>

                        <div class="order-history" id="order-history" hidden>
                            <h4>Order History</h4>
                            <div id="order-history-list">No history yet.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-section" data-section="addons" hidden>
            <div class="admin-toolbar">
                <div class="admin-card admin-search">
                    <h6>Search Add-ons</h6>
                    <div class="admin-search-input">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M21 20.3 16.7 16a7.5 7.5 0 1 0-1 1l4.3 4.3a.7.7 0 0 0 1-1ZM4.5 10.5a6 6 0 1 1 12 0 6 6 0 0 1-12 0Z" />
                        </svg>
                        <input id="addon-search" type="text" placeholder="Search add-ons">
                    </div>
                </div>
                <div class="admin-card admin-stats">
                    <div class="admin-stat">
                        <span>Total</span>
                        <strong><?php echo $addons_total; ?></strong>
                    </div>
                    <div class="admin-stat">
                        <span>Active</span>
                        <strong><?php echo $addon_field_map['active'] ? $addons_active : '—'; ?></strong>
                    </div>
                </div>
            </div>

            <div class="admin-layout">
                <aside class="admin-card addon-list">
                    <div class="admin-list-header">
                        <h2>Add-ons</h2>
                        <span class="admin-list-count"><?php echo $addons_total; ?></span>
                    </div>
                    <div class="admin-list-items addon-items">
                        <?php if ($addons_error !== '') { ?>
                            <div class="admin-empty-list"><?php echo htmlspecialchars($addons_error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php } else { ?>
                            <?php
                            $addon_search_columns = array_values(array_filter([$addon_field_map['name'], $addon_field_map['description'], $addon_field_map['price']]));
                            foreach ($addons as $addon) {
                                $addon_id = $addon_id_field ? (int) ($addon[$addon_id_field] ?? 0) : 0;
                                $addon_name_raw = $addon_field_map['name'] ? ($addon[$addon_field_map['name']] ?? '') : '';
                                $addon_name = htmlspecialchars((string) $addon_name_raw, ENT_QUOTES, 'UTF-8');
                                $addon_price_raw = $addon_field_map['price'] ? ($addon[$addon_field_map['price']] ?? null) : null;
                                $addon_price = is_numeric($addon_price_raw) ? 'USD$' . rtrim(rtrim(number_format((float) $addon_price_raw, 2, '.', ','), '0'), '.') : '—';
                                $addon_per_day = $addon_field_map['per_day'] ? ((int) ($addon[$addon_field_map['per_day']] ?? 0) === 1) : false;
                                $search_key = build_search_value($addon, $addon_search_columns);
                                $perday_class = $addon_per_day ? 'tag-on' : 'tag-off';
                            ?>
                                <button class="admin-list-item addon-item" type="button" data-addon-id="<?php echo $addon_id; ?>" data-search="<?php echo htmlspecialchars($search_key, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="admin-list-item-top">
                                        <div>
                                            <span class="admin-list-title addon-name"><?php echo $addon_name; ?></span>
                                            <span class="admin-list-subtitle">Add-on</span>
                                        </div>
                                        <span class="admin-list-price addon-price"><?php echo htmlspecialchars($addon_price, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="admin-list-tags">
                                        <span class="vehicle-tag <?php echo $perday_class; ?>">Per Day</span>
                                    </div>
                                </button>
                            <?php } ?>
                            <div class="admin-empty-list addon-empty" hidden>No add-ons match your search.</div>
                        <?php } ?>
                    </div>
                </aside>

                <div class="admin-card addon-editor">
                    <?php if ($addons_error !== '') { ?>
                        <div class="admin-empty-state">
                            <h3>Add-ons unavailable</h3>
                            <p><?php echo htmlspecialchars($addons_error, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php } else { ?>
                        <div class="editor-empty addon-empty-state">
                            <h2>Select an add-on</h2>
                            <p>Choose an add-on from the list to edit its details.</p>
                        </div>

                        <form id="addon-form" hidden>
                            <input type="hidden" name="id">

                            <div class="admin-group">
                                <h3>Details</h3>
                                <div class="admin-grid">
                                    <label class="input-container" data-addon-field="name">
                                        <h6>Name <sup>*</sup></h6>
                                        <input type="text" name="name" required>
                                    </label>
                                    <label class="input-container" data-addon-field="price">
                                        <h6>Price (USD)</h6>
                                        <input type="number" name="price" min="0" step="0.01">
                                    </label>
                                    <label class="input-container" data-addon-field="sort_order">
                                        <h6>Sort Order</h6>
                                        <input type="number" name="sort_order" min="0" step="1">
                                    </label>
                                </div>
                                <label class="input-container" data-addon-field="description">
                                    <h6>Description</h6>
                                    <textarea name="description" rows="4"></textarea>
                                </label>
                            </div>

                            <div class="admin-group">
                                <h3>Visibility</h3>
                                <div class="admin-grid">
                                    <label class="admin-toggle" data-addon-field="per_day">
                                        <input type="checkbox" name="per_day">
                                        <span>Charged Per Day</span>
                                    </label>
                                </div>
                            </div>

                            <div class="admin-actions">
                                <button type="button" class="continue-btn addon-save" disabled>Save Add-on</button>
                                <button type="button" class="admin-reset addon-reset">Reset</button>
                                <span id="addon-status" aria-live="polite"></span>
                            </div>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="admin-section" data-section="discounts" hidden>
            <div class="admin-toolbar">
                <div class="admin-card admin-search">
                    <h6>Search Discounts</h6>
                    <div class="admin-search-input">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M21 20.3 16.7 16a7.5 7.5 0 1 0-1 1l4.3 4.3a.7.7 0 0 0 1-1ZM4.5 10.5a6 6 0 1 1 12 0 6 6 0 0 1-12 0Z" />
                        </svg>
                        <input id="discount-search" type="text" placeholder="Search vehicles or discounts">
                    </div>
                </div>
                <div class="admin-card admin-stats">
                    <div class="admin-stat">
                        <span>Total</span>
                        <strong><?php echo $discount_stats['total']; ?></strong>
                    </div>
                    <div class="admin-stat">
                        <span>Discounted</span>
                        <strong><?php echo $discount_stats['discounted']; ?></strong>
                    </div>
                    <div class="admin-stat">
                        <span>Active</span>
                        <strong><?php echo $discount_stats['active']; ?></strong>
                    </div>
                </div>
            </div>

            <div class="admin-layout">
                <aside class="admin-card discount-list">
                    <div class="admin-list-header">
                        <h2>Vehicle Discounts</h2>
                        <span class="admin-list-count"><?php echo $discount_stats['total']; ?></span>
                    </div>
                    <div class="admin-list-items discount-items">
                        <?php if ($discounts_error !== '') { ?>
                            <div class="admin-empty-list"><?php echo htmlspecialchars($discounts_error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php } else { ?>
                            <?php foreach ($discounts as $entry) {
                                $vehicle = $entry['vehicle'] ?? [];
                                $discount = $entry['discount'] ?? null;
                                $vehicle_id = (int) ($vehicle['id'] ?? 0);
                                $vehicle_name = htmlspecialchars((string) ($vehicle['name'] ?? 'Vehicle'), ENT_QUOTES, 'UTF-8');
                                $vehicle_type = htmlspecialchars((string) ($vehicle['type'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $vehicle_price_raw = $vehicle['base_price_USD'] ?? null;
                                $vehicle_price = is_numeric($vehicle_price_raw) ? 'USD$' . rtrim(rtrim(number_format((float) $vehicle_price_raw, 2, '.', ','), '0'), '.') . '/day' : '—';
                                $search_key = build_search_value($vehicle, ['name', 'type', 'slug']);
                                if ($discount && !empty($discount['label'])) {
                                    $search_key .= ' ' . strtolower((string) $discount['label']);
                                }
                                $summary = format_discount_summary($discount, $discount_value_kind, $discount_currency);
                                $has_discount = $discount && (is_numeric($discount['price_USD'] ?? null) || is_numeric($discount['price_XCD'] ?? null));
                                $is_active = false;
                                if ($has_discount) {
                                    $is_active = true;
                                }
                                $active_class = $is_active ? 'tag-on' : 'tag-off';
                                $active_label = $is_active ? 'Active' : 'Inactive';
                            ?>
                                <button class="admin-list-item discount-item" type="button" data-vehicle-id="<?php echo $vehicle_id; ?>" data-search="<?php echo htmlspecialchars($search_key, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="admin-list-item-top">
                                        <div>
                                            <span class="admin-list-title discount-vehicle-name"><?php echo $vehicle_name; ?></span>
                                            <span class="admin-list-subtitle"><?php echo $vehicle_type; ?></span>
                                        </div>
                                        <span class="admin-list-price discount-summary"><?php echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="admin-list-meta">
                                        <span class="admin-list-meta-item"><?php echo htmlspecialchars($vehicle_price, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="admin-list-tags">
                                        <span class="vehicle-tag <?php echo $active_class; ?>"><?php echo $active_label; ?></span>
                                    </div>
                                </button>
                            <?php } ?>
                            <div class="admin-empty-list discount-empty" hidden>No vehicles match your search.</div>
                        <?php } ?>
                    </div>
                </aside>

                <div class="admin-card discount-editor">
                    <?php if ($discounts_error !== '') { ?>
                        <div class="admin-empty-state">
                            <h3>Discounts unavailable</h3>
                            <p><?php echo htmlspecialchars($discounts_error, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php } else { ?>
                        <div class="editor-empty discount-empty-state">
                            <h2>Select a vehicle</h2>
                            <p>Choose a vehicle to update its discount settings.</p>
                        </div>

                        <form id="discount-form" hidden>
                            <input type="hidden" name="vehicle_id">

                            <div class="discount-preview">
                                <div>
                                    <h2 class="discount-vehicle-title">Vehicle</h2>
                                    <p class="discount-vehicle-subtitle">Type</p>
                                </div>
                                <div class="discount-vehicle-price">Base USD$0/day</div>
                            </div>

                            <div class="admin-group">
                                <h3>Discount Details</h3>
                                <div class="admin-grid">
                                    <label class="input-container" data-discount-field="price_USD">
                                        <h6>Discount Price (USD)</h6>
                                        <input type="number" name="price_USD" min="0" step="0.01">
                                    </label>
                                    <label class="input-container" data-discount-field="price_XCD">
                                        <h6>Discount Price (XCD)</h6>
                                        <input type="number" name="price_XCD" min="0" step="0.01">
                                    </label>
                                    <label class="input-container" data-discount-field="days">
                                        <h6>Days</h6>
                                        <input type="number" name="days" min="1" step="1">
                                    </label>
                                </div>
                                <p class="field-hint">Set a discounted price for a specific number of days. Clear the price to remove discounts.</p>
                            </div>

                            <div class="admin-actions">
                                <button type="button" class="continue-btn discount-save" disabled>Save Discount</button>
                                <button type="button" class="admin-reset discount-reset">Reset</button>
                                <span id="discount-status" aria-live="polite"></span>
                            </div>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script id="vehicle-data" type="application/json"><?php echo json_encode($vehicles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<script id="orders-data" type="application/json"><?php echo json_encode($orders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<script id="orders-config" type="application/json"><?php echo json_encode($orders_config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<script id="addons-data" type="application/json"><?php echo json_encode($addons, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<script id="addons-config" type="application/json"><?php echo json_encode($addons_config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<script id="discounts-data" type="application/json"><?php echo json_encode($discounts_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<script id="discounts-config" type="application/json"><?php echo json_encode($discounts_config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>

<?php include_once 'includes/footer.php'; ?>
