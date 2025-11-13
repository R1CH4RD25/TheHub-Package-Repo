<?php
/**
 * Accept Invitation Page
 * Validates invitation token and redirects to Google OAuth login
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Invitation;
use Hub\Auth;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    http_response_code(400);
    include 'error.php';
    exit('Invalid invitation link');
}

$token = $_GET['token'];

try {
    // Validate invitation token
    $invitationService = new Invitation($db);
    $invitation = $invitationService->getByToken($token);

    if (!$invitation) {
        throw new \Exception('Invalid or expired invitation');
    }

    // Check if already accepted
    if ($invitation['accepted_at']) {
        throw new \Exception('This invitation has already been used');
    }

    // Check if expired
    if (strtotime($invitation['expires_at']) < time()) {
        throw new \Exception('This invitation has expired');
    }

    // Store invitation token in session for use after OAuth
    $_SESSION['invitation_token'] = $token;
    $_SESSION['invitation_email'] = $invitation['email'];
    $_SESSION['invitation_role'] = $invitation['role'];

    // Redirect to Google OAuth login
    header('Location: /google_login.php?invitation=1');
    exit;

} catch (\Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Invitation - Woodson ISD's The Hub</title>
    <link rel="icon" type="image/png" href="/assets/images/Cowboy_SM_favicon.png">
    <?php
    // Load production CSS if available, otherwise fallback to style.css
    $productionCss = __DIR__ . '/assets/css/production.min.css';
    if (file_exists($productionCss)) {
        echo '<link rel="stylesheet" href="/assets/css/production.min.css?v=' . filemtime($productionCss) . '">';
    } else {
        echo '<link rel="stylesheet" href="/assets/css/style.css">';
    }
    ?>
    <style>
        .error-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 2rem;
            text-align: center;
        }
        .error-icon {
            font-size: 4rem;
            color: var(--danger-color);
        }
        .error-title {
            font-size: 1.5rem;
            margin: 1rem 0;
        }
        .error-message {
            color: #666;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1 class="error-title">Invitation Error</h1>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <a href="/login.php" class="btn btn-primary">Return to Login</a>
    </div>
</body>
</html>
