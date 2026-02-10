<?php

// Basic defaults
$company_name = $company_name ?? '';
$www_domain = $www_domain ?? '';
$page = $page ?? 'admin';
$prod = $prod ?? false;

$description = $description ?? ($company_name !== '' ? "Admin portal for {$company_name}." : "Admin portal.");
$base_title = $company_name !== '' ? $company_name : 'Admin';

if (!empty($title_override)) {
    $title = $title_override;
} elseif (!empty($title_suffix)) {
    $title = $base_title . ' | ' . $title_suffix;
} else {
    $title = $base_title . ' Admin';
}

$root_path = dirname(__DIR__);

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
    <meta name="description" content="<?php echo $description; ?>">

    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon/favicon-16x16.png">
    <link rel="manifest" href="/assets/images/favicon/site.webmanifest">

    <title><?php echo $title; ?></title>
    <link type="text/css" rel="stylesheet" href="/styles/min/main.min.css">
    <?php if (isset($extra_css) && file_exists($root_path . "/styles/min/{$extra_css}.min.css")) { ?>
        <link type="text/css" rel="stylesheet" href="/styles/min/<?php echo $extra_css; ?>.min.css">
    <?php } ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <script src="/plugins/jquery/jquery-3.7.1.min.js" defer></script>
    <?php if (isset($extra_js) && file_exists($root_path . "/js/min/{$extra_js}.min.js")) { ?>
        <script src="/js/min/<?php echo $extra_js; ?>.min.js" defer></script>
    <?php } ?>
</head>

<body id="<?php echo $page; ?>-page" class="admin-body">
    <header class="admin-site-header">
        <div class="inner">
            <div class="admin-brand">
                <img src="/assets/images/logo.avif" alt="Keys Car Rental Logo">
                <div>
                    <span class="admin-brand-title"><?php echo $company_name !== '' ? $company_name : 'Keys Car Rental'; ?></span>
                    <span class="admin-brand-subtitle">Admin Portal</span>
                </div>
            </div>
        </div>
    </header>
