<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Session check - redirect to login if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?= isset($page_title) ? $page_title . ' | ' . APP_NAME : APP_NAME ?></title>

  <!-- Google Fonts: Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- FontAwesome 5 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">

  <!-- Bootstrap 4.6.2 CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">

  <!-- Flatpickr Date Picker -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <!-- Custom Theme CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">

  <!-- jQuery (loaded early for inline scripts) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body id="page-top">

<!-- Sidebar Overlay (mobile drawer backdrop) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div id="wrapper">

<?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- ===== CONTENT WRAPPER ===== -->
  <div id="content-wrapper">

    <!-- Topbar -->
    <header class="topbar">
      <button class="sidebar-toggle-btn" id="sidebarMobileToggle">
        <i class="fas fa-bars"></i>
      </button>
      <?php if (empty($hide_topbar_title)): ?>
      <div class="page-title"><?= $page_title ?? 'Dashboard' ?></div>
      <?php endif; ?>
      <div class="user-area">
        <span class="badge <?= isAdmin() ? 'badge-success' : 'badge-info' ?> d-none d-md-inline"><?= htmlspecialchars(roleLabel($_SESSION['user_role'] ?? 'Admin')) ?></span>
        <div class="dropdown">
          <button class="btn btn-link text-muted dropdown-toggle p-0" data-toggle="dropdown">
            <i class="fas fa-user-circle fa-lg"></i>
            <span class="ml-1 d-none d-sm-inline"><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Admin') ?></span>
          </button>
          <div class="dropdown-menu dropdown-menu-right shadow-sm">
            <div class="dropdown-item-text px-3 py-2">
              <div class="font-weight-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Admin') ?></div>
              <small class="text-muted"><?= htmlspecialchars(roleLabel($_SESSION['user_role'] ?? 'Admin')) ?></small>
            </div>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="<?= BASE_URL ?>logout.php">
              <i class="fas fa-sign-out-alt fa-sm mr-2 text-danger"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </header>
    <!-- End Topbar -->

    <!-- Page Content -->
    <div class="content">

      <!-- Page Heading -->
      <?php if (empty($compact_page_heading)): ?>
      <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 font-weight-bold" style="color:#0f172a;"><?= $page_title ?? 'Dashboard' ?></h1>
      </div>
      <?php endif; ?>

      <!-- Flash Messages -->
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php endif; ?>
