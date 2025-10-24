<?php
/**
 * Vehicle Maintenance Section - Coming Soon
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use Hub\Auth;

// Require section access
Auth::requireSectionAccess('vehicle-maintenance', 'staff');

$user = Auth::getCurrentUser();
$sectionRole = Auth::getUserSectionRole('vehicle-maintenance');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Maintenance - Woodson ISD</title>
    <link rel="icon" type="image/png" href="/assets/images/Cowboy_SM_favicon.png">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .coming-soon-container {
            max-width: 800px;
            margin: 100px auto;
            padding: 3rem 2rem;
            text-align: center;
        }

        .coming-soon-icon {
            font-size: 8rem;
            margin-bottom: 2rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .coming-soon-title {
            font-size: 2.5rem;
            color: var(--text-color);
            margin-bottom: 1rem;
        }

        .coming-soon-subtitle {
            font-size: 1.25rem;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 2rem;
        }

        .coming-soon-description {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 3rem;
        }

        .back-button {
            display: inline-block;
            background: var(--primary-color);
            color: black;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .back-button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .user-role-badge {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .coming-soon-container {
                margin: 50px auto;
                padding: 2rem 1rem;
            }

            .coming-soon-icon {
                font-size: 5rem;
            }

            .coming-soon-title {
                font-size: 1.75rem;
            }

            .coming-soon-subtitle {
                font-size: 1rem;
            }

            .coming-soon-description {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-content">
            <a href="/" class="nav-brand" style="text-decoration: none; color: var(--primary-color);">
                <img src="/assets/images/branding/Branding_NoBG.png" alt="Woodson ISD" style="height: 40px; vertical-align: middle;">
            </a>
            <button class="nav-toggle" id="navToggle">☰</button>
            <div class="nav-links" id="navLinks">
                <span class="nav-user">Hello, <?php echo e($user['name']); ?></span>
                <a href="/" class="nav-link btn-nav">Hub</a>
            </div>
        </div>
    </nav>

    <div class="coming-soon-container">
        <div class="coming-soon-icon">🔧</div>
        <h1 class="coming-soon-title">Vehicle Maintenance</h1>
        <p class="coming-soon-subtitle">Coming Soon</p>
        <p class="coming-soon-description">
            This section will allow you to schedule and track vehicle maintenance, repairs, and inspections for all district vehicles. 
            Features will include maintenance schedules, work order management, parts inventory, and service history tracking.
        </p>
        <a href="/" class="back-button">← Back to Hub</a>
        
        <div class="user-role-badge">
            Your Access: <?php echo e(ucwords(str_replace('_', ' ', $sectionRole))); ?>
        </div>
    </div>

    <script src="/assets/js/nav.js?v=<?php echo time(); ?>"></script>
</body>
</html>
