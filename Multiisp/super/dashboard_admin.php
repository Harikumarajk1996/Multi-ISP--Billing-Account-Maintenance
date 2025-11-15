<?php
session_start();
require_once('../config/db.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$user = $_SESSION['user'];
$pdo = getPDO();

// Get statistics
    try {
        // subscribers live in `subscriber` table
        $userCount = $pdo->query("SELECT COUNT(*) FROM subscriber")->fetchColumn();
        // isps/operators live in `isps` table
        $ispCount = $pdo->query("SELECT COUNT(*) FROM isps")->fetchColumn();
        $planCount = $pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
        // Total invoiced amount and total paid amount (from invoices table)
        $totalInvoiced = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM invoices")->fetchColumn();
        $totalPaid = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status = 'paid'")->fetchColumn();
        
        // Get plan distribution data
        $planData = $pdo->query("SELECT name, COUNT(*) as count FROM plans GROUP BY name")->fetchAll();
        
        // Get monthly subscriber growth from subscriber table
        $userGrowth = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
            FROM subscriber 
            GROUP BY month 
            ORDER BY month DESC 
            LIMIT 6
        ")->fetchAll();
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
    
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            background: #4723D9;
            padding: 20px;
            color: white;
            z-index: 1000;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.1);
        }
        .sidebar i {
            margin-right: 10px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-card {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            margin: auto;
            height: 300px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="#" class="nav_logo">
            <i class='bx bx-layer'></i>
            <span>Admin Panel</span>
        </a>
        <a href="plans.php">
            <i class='bx bx-package'></i>
            <span>Plans</span>
        </a>
        <a href="operator.php">
            <i class='bx bx-building'></i>
            <span>ISPs</span>
        </a>
        <a href="subscriber.php">
            <i class='bx bx-group'></i>
            <span>Subscribers</span>
        </a>
        <a href="billing.php">
            <i class='bx bx-credit-card'></i>
            <span>Billing</span>
        </a>
        <a href="reports.php">
            <i class='bx bx-bar-chart-alt-2'></i>
            <span>Reports</span>
        </a>
        <a href="#">
            <i class='bx bx-globe'></i>
            <span>Website</span>
        </a>
        <a href="#">
            <i class='bx bx-cog'></i>
            <span>Settings</span>
        </a>
        <a href="logout.php" style="margin-top: auto;">
            <i class='bx bx-log-out'></i>
            <span>Sign Out</span>
        </a>
    </div>

    <div class="main-content">
        <div class="header d-flex justify-content-between align-items-center">
            <div>
                <i class='bx bx-menu fs-4'></i>
            </div>
            <div>
                Welcome, <?php echo htmlspecialchars($user['name'] ?? $user['email']); ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="container-fluid">
        <div class="row g-3 mb-4 align-items-stretch">
                <div class="col-md-3">
                    <div class="card stats-card bg-primary text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title">Total Users</h6>
                            <h2 class="mb-0"><?php echo number_format($userCount); ?></h2>
                            <small>Active subscribers</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-success text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title">Total ISPs</h6>
                            <h2 class="mb-0"><?php echo number_format($ispCount); ?></h2>
                            <small>Active operators</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-info text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title">Total Plans</h6>
                            <h2 class="mb-0"><?php echo number_format($planCount); ?></h2>
                            <small>Active plans</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-warning text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title">Invoices</h6>
                            <h2 class="mb-0">₹<?php echo number_format((float)($totalInvoiced ?? 0), 2); ?></h2>
                            <small>Total invoiced</small>
                            <div style="margin-top:8px;font-weight:700">Paid: ₹<?php echo number_format((float)($totalPaid ?? 0),2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">User Growth</h5>
                            <div class="chart-container">
                                <canvas id="userGrowthChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Plan Distribution</h5>
                            <div class="chart-container">
                                <canvas id="planDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Recent Activities</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $activities = $pdo->query("
                                                SELECT i.name AS isp_name, p.name as plan_name, p.created_at 
                                                FROM plans p 
                                                LEFT JOIN isps i ON p.isp_id = i.id 
                                                ORDER BY p.created_at DESC 
                                                LIMIT 5
                                            ")->fetchAll();

                                            foreach ($activities as $activity) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars(date('Y-m-d H:i', strtotime($activity['created_at']))) . "</td>";
                                                echo "<td>" . htmlspecialchars($activity['isp_name'] ?? 'Unknown') . "</td>";
                                                echo "<td>Added new plan</td>";
                                                echo "<td>" . htmlspecialchars($activity['plan_name']) . "</td>";
                                                echo "</tr>";
                                            }
                                        } catch(PDOException $e) {
                                            echo "<tr><td colspan='4'>No recent activities</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column(array_reverse($userGrowth), 'month')); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode(array_column(array_reverse($userGrowth), 'count')); ?>,
                    borderColor: '#4723D9',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Plan Distribution Chart
        const planDistributionCtx = document.getElementById('planDistributionChart').getContext('2d');
        new Chart(planDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($planData, 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($planData, 'count')); ?>,
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html>
