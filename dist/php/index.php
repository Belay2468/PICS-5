<?php


require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';



if (isLoggedIn()) {
    header("Location: " . ($_SESSION['role'] ==='admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrfValid()) {

        $error = CSRF_ERROR;

    } elseif ($email === '' || $password === '') {

        $error = 'Please enter both your email address and password.';

    } elseif (($lockWait = loginLockRemaining($email)) > 0) {

        // Temporary lockout after too many failed attempts.
        $error = 'Too many failed sign-in attempts. Please try again in '
               . formatLockoutWait($lockWait) . '.';

        logActivity($conn, null, "Blocked sign-in attempt for \"$email\" (locked out)");

    } else {

        $stmt = $conn->prepare("
            SELECT id, full_name, email, password, role, department, status
            FROM users
            WHERE email=?
        ");

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if (!$user || !password_verify($password, $user['password'])) {

            $left = recordFailedLogin($email);

            $error = 'Invalid email or password. Please try again.';

            if ($left === 0) {
                $error = 'Too many failed sign-in attempts. This account is temporarily locked for '
                       . formatLockoutWait(LOGIN_LOCKOUT_SECS) . '.';
            } elseif ($left <= 2) {
                $error .= " $left attempt(s) remaining before a temporary lockout.";
            }

        } elseif ($user['status'] !== 'active') {

            $error = 'This account has been deactivated. Please contact the administrator.';

        } else {

            clearLoginAttempts($email);

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['department'] = $user['department'];

            if (!empty($_POST['remember'])) {
                issueRememberToken($conn, $user['id']);
            }

            logActivity($conn, $user['id'], 'Logged in');


            header(
                "Location: " . 
                ($user['role']==='admin' 
                ? 'admin/dashboard.php' 
                : 'user/dashboard.php')
            );
            

            exit;
        }
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — PhilHealth Inventory Control System</title>
    <link rel="icon" type="image/svg+xml" href="<?= h(picsLogoUrl(1)) ?>">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="../scss/main.css">
</head>


<body class="loginpage">

<div class="login-page">

    <div class="container">

        <div class="login-card">

            <div class="login-container">


                <div class="left">

                    <div class="logo-badge">
                        <img src="<?= h(picsLogoUrl(2)) ?>" alt="PICS — PhilHealth Inventory Control System logo">
                    </div>

                    <h1>
                        PHILHEALTH INVENTORY CONTROL SYSTEM
                    </h1>

                    <div class="subtitle">
                        PhilHealth Supply Tracking &amp; Inventory Management
                    </div>

                </div>



                <div class="right">

                    <?= $error ? alertBox($error, 'danger', false) : '' ?>


                    <form method="POST" action="index.php">

                        <?= csrfField() ?>

                        <div class="field">

                            <label for="email">
                                Email Address
                            </label>

                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="admin@philtrack.com"
                                required
                                value="<?= h($_POST['email'] ?? '') ?>"
                            >

                        </div>



                        <div class="field">

                            <label for="password">
                                Password
                            </label>

                            <input 
                                type="password" 
                                id="password" 
                                name="password"
                                placeholder="••••••••"
                                required
                            >

                        </div>



                        <div class="login-row">

                            <label>
                                <input type="checkbox" name="remember">
                                Remember me
                            </label>


                            <a href="#" onclick="alert('Please contact your system administrator to reset your password.'); return false;">
                                Forgot Password?
                            </a>

                        </div>



                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            Sign In
                        </button>


                    </form>


                </div>


            </div>



            <div class="login-foot">
                PICS v1.0.0 &middot; Inspired by PhilHealth
            </div>


        </div>

    </div>


</div>


</body>
</html>