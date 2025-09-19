<?php
ob_start();
require_once 'includes/session_config.php';
// Only start a session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
session_start();
}
require_once 'includes/db.php';
require_once 'includes/language.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: admin/dashboard.php");
    exit();
}

// Set default language if not set
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

// Handle language change
if (isset($_GET['lang']) && in_array($_GET['lang'], array_keys($available_languages))) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getTranslation('login.title'); ?> - <?php echo getTranslation('site.title'); ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icon-css@6.11.0/css/flag-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card login-card">
                        <div class="text-center mb-4">
                            <i class="bi bi-box-seam login-icon"></i>
                            <h2 class="mt-2"><?php echo getTranslation('login.title'); ?></h2>
                            <p class="text-muted"><?php echo getTranslation('login.subtitle'); ?></p>
                        </div>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <form action="includes/login_process.php" method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label"><?php echo getTranslation('login.username'); ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label"><?php echo getTranslation('login.password'); ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary"><?php echo getTranslation('login.submit'); ?></button>
                            </div>
                        </form>
                        
                        <div class="mt-4 text-center">
                            <div class="language-switcher">
                                <a href="?lang=en" class="lang-flag <?php echo $_SESSION['lang'] === 'en' ? 'active' : ''; ?>">
                                    <span class="flag-icon flag-icon-us"></span>
                                </a>
                                <a href="?lang=fr" class="lang-flag <?php echo $_SESSION['lang'] === 'fr' ? 'active' : ''; ?>">
                                    <span class="flag-icon flag-icon-fr"></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 