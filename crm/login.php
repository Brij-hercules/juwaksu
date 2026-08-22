<?php
// crm/login.php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.username = ? AND u.status = 'active'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];

                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (\PDOException $e) {
            $error = 'Error querying users: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Portal Login | Prime Properties</title>
    <!-- Tailwind CSS (Play CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f5f7ff',
                            100: '#ebf0ff',
                            500: '#1a365d',
                            600: '#102a43',
                            gold: '#d4af37',
                            goldDark: '#aa7c11',
                        }
                    },
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;850&display=swap" rel="stylesheet">
    <style>
        @keyframes subtle-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .float-graphic {
            animation: subtle-bounce 4s ease-in-out infinite;
        }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 bg-gradient-to-br from-slate-950 via-brand-600 to-indigo-950 overflow-hidden font-sans relative">
    
    <!-- Background lights -->
    <div class="absolute w-96 h-96 bg-brand-gold/10 rounded-full blur-3xl -top-12 -left-12"></div>
    <div class="absolute w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl -bottom-12 -right-12"></div>

    <div class="max-w-5xl w-full bg-slate-900/40 backdrop-blur-md rounded-3xl overflow-hidden shadow-2xl border border-slate-800/80 flex flex-col md:flex-row relative z-10">
        <!-- Visual Panel -->
        <div class="md:w-1/2 bg-gradient-to-br from-brand-600 to-indigo-950 p-12 text-white flex flex-col justify-between border-b md:border-b-0 md:border-r border-slate-800/60">
            <div class="flex items-center gap-2 font-extrabold text-xl tracking-tight text-white">
                <span class="text-brand-gold">WAVE</span>PROPERTIES
            </div>
            
            <div class="my-12 text-center md:text-left">
                <div class="inline-block bg-slate-950/20 text-brand-gold px-3.5 py-1.5 rounded-full border border-brand-gold/20 text-xs font-bold uppercase tracking-widest mb-6 float-graphic">
                    Enterprise Portal
                </div>
                <h2 class="text-3xl md:text-4xl font-black leading-tight tracking-tight mb-4">
                    Manage leads & <br>
                    Campaign data.
                </h2>
                <p class="text-slate-400 font-light text-sm leading-relaxed max-w-sm">
                    Access roles, track dynamic property sales pipeline, review inquiries, and monitor Meta marketing ads ROI.
                </p>
            </div>
            
            <div class="text-slate-500 text-xs">
                &copy; <?php echo date('Y'); ?> Prime Properties System.
            </div>
        </div>
        
        <!-- Form Panel -->
        <div class="md:w-1/2 p-8 md:p-16 flex flex-col justify-center bg-slate-950/40">
            <h1 class="text-2xl font-extrabold text-white mb-2">Agent Login</h1>
            <p class="text-slate-500 text-xs mb-8">Enter your registered workspace credentials to proceed.</p>
            
            <?php if (!empty($error)): ?>
                <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-4 rounded-xl text-xs mb-6 flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" class="space-y-5">
                <div>
                    <label for="username" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Username</label>
                    <input type="text" name="username" id="username" required class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-slate-800 text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:border-transparent transition-all placeholder-slate-700" placeholder="e.g. admin">
                </div>
                
                <div>
                    <div class="flex justify-between items-baseline mb-2">
                        <label for="password" class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest">Password</label>
                        <span class="text-[10px] text-slate-600">admin123 / sales123</span>
                    </div>
                    <input type="password" name="password" id="password" required class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-slate-800 text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold focus:border-transparent transition-all placeholder-slate-700" placeholder="••••••••">
                </div>
                
                <button type="submit" class="w-full py-3.5 bg-brand-gold hover:bg-brand-goldDark text-slate-950 font-extrabold text-sm rounded-xl transition duration-300 shadow-lg transform hover:-translate-y-0.5">
                    Proceed to CRM
                </button>
            </form>
            
            <div class="mt-8 text-center text-xs">
                <a href="../index.php" class="text-slate-500 hover:text-brand-gold transition duration-200 flex items-center justify-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Public Site
                </a>
            </div>
        </div>
    </div>
</body>
</html>
