<?php
session_start();
include '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

$page_title = 'Fuel Management - Smart Driver Admin';
$base_url = '../';
include '../includes/header.php';

// Get all fuel records with user info
$fuel_records = mysqli_query($conn, "
    SELECT f.*, u.full_name, u.email, u.vehicle_plate 
    FROM fuel_records f 
    JOIN users u ON f.user_id = u.id 
    ORDER BY f.recorded_at DESC
");

// Get statistics
$total_fuel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(fuel_amount) as total FROM fuel_records"))['total'];
$total_cost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(cost) as total FROM fuel_records"))['total'];
$avg_efficiency = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(fuel_efficiency) as avg FROM fuel_records"))['avg'];
$total_records = mysqli_num_rows($fuel_records);

// Get fuel type distribution
$fuel_types = mysqli_query($conn, "
    SELECT fuel_type, COUNT(*) as count, SUM(fuel_amount) as total_liters 
    FROM fuel_records 
    GROUP BY fuel_type
");

// Get monthly fuel consumption data
$monthly_data = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(recorded_at, '%Y-%m') as month,
        SUM(fuel_amount) as total_fuel,
        SUM(cost) as total_cost,
        AVG(fuel_efficiency) as avg_efficiency
    FROM fuel_records 
    GROUP BY DATE_FORMAT(recorded_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
?>

<div class="admin-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2><i class="fas fa-car"></i> Smart Driver</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="violations.php"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="fuel.php" class="active"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="activity.php"><i class="fas fa-clock"></i> Activity</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1><i class="fas fa-gas-pump"></i> Fuel Management</h1>
            <span class="badge badge-info">Total Records: <?php echo $total_records; ?></span>
        </header>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="border-color: #1a237e;">
                <div class="stat-icon">⛽</div>
                <h3><?php echo number_format($total_fuel ?? 0, 1); ?> L</h3>
                <p>Total Fuel Used</p>
            </div>
            <div class="stat-card" style="border-color: #2e7d32;">
                <div class="stat-icon">💰</div>
                <h3><?php echo number_format($total_cost ?? 0, 2); ?> ETB</h3>
                <p>Total Fuel Cost</p>
            </div>
            <div class="stat-card" style="border-color: #f9a825;">
                <div class="stat-icon">📊</div>
                <h3><?php echo number_format($avg_efficiency ?? 0, 2); ?> km/L</h3>
                <p>Average Efficiency</p>
            </div>
            <div class="stat-card" style="border-color: #c62828;">
                <div class="stat-icon">🚗</div>
                <h3><?php echo $total_records; ?></h3>
                <p>Total Records</p>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <div class="chart-container">
                <h3><i class="fas fa-chart-line"></i> Monthly Fuel Consumption</h3>
                <canvas id="monthlyFuelChart"></canvas>
            </div>
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> Fuel Type Distribution</h3>
                <canvas id="fuelTypeChart"></canvas>
            </div>
        </div>

        <!-- Fuel Records Table -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <h3><i class="fas fa-list"></i> All Fuel Records</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" id="searchFuel" placeholder="Search by driver or location..." class="form-control" style="width: 250px;">
                    <select id="filterFuelType" class="form-control" style="width: 150px;">
                        <option value="">All Types</option>
                        <option value="petrol">Petrol</option>
                        <option value="diesel">Diesel</option>
                        <option value="electric">Electric</option>
                    </select>
                    <button onclick="exportFuelData()" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export
                    </button>
                </div>
            </div>

            <div class="table-container" style="box-shadow: none; padding: 0;">
                <table id="fuelTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Driver</th>
                            <th>Vehicle</th>
                            <th>Date</th>
                            <th>Fuel (L)</th>
                            <th>Distance (km)</th>
                            <th>Efficiency (km/L)</th>
                            <th>Cost (ETB)</th>
                            <th>Type</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($f = mysqli_fetch_assoc($fuel_records)): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <strong><?php echo $f['full_name']; ?></strong><br>
                                    <small style="color: #666;"><?php echo $f['email']; ?></small>
                                </td>
                                <td><?php echo $f['vehicle_plate'] ?? 'N/A'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($f['recorded_at'])); ?></td>
                                <td><?php echo number_format($f['fuel_amount'], 1); ?></td>
                                <td><?php echo number_format($f['distance_covered'], 1); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $f['fuel_efficiency'] >= 15 ? 'success' : 
                                            ($f['fuel_efficiency'] >= 10 ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo number_format($f['fuel_efficiency'], 2); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($f['cost'], 2); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $f['fuel_type'] == 'electric' ? 'success' : 
                                            ($f['fuel_type'] == 'diesel' ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($f['fuel_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $f['location'] ?? 'N/A'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($fuel_records) == 0): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-gas-pump" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></i>
                                    <p style="color: #666;">No fuel records found. Drivers haven't started tracking fuel yet.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Monthly Fuel Consumption Chart
const monthlyCtx = document.getElementById('monthlyFuelChart').getContext('2d');
const monthlyLabels = <?php 
    $labels = [];
    $fuelData = [];
    $costData = [];
    $efficiencyData = [];
    while ($row = mysqli_fetch_assoc($monthly_data)) {
        $labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $fuelData[] = (float)$row['total_fuel'];
        $costData[] = (float)$row['total_cost'];
        $efficiencyData[] = (float)$row['avg_efficiency'];
    }
    echo json_encode(array_reverse($labels));
?>;
const monthlyFuel = <?php echo json_encode(array_reverse($fuelData)); ?>;
const monthlyCost = <?php echo json_encode(array_reverse($costData)); ?>;
const monthlyEfficiency = <?php echo json_encode(array_reverse($efficiencyData)); ?>;

new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: [
            {
                label: 'Fuel Used (L)',
                data: monthlyFuel,
                backgroundColor: 'rgba(26, 35, 126, 0.6)',
                borderColor: 'rgba(26, 35, 126, 1)',
                borderWidth: 2,
                yAxisID: 'y'
            },
            {
                label: 'Cost (ETB)',
                data: monthlyCost,
                backgroundColor: 'rgba(46, 125, 50, 0.6)',
                borderColor: 'rgba(46, 125, 50, 1)',
                borderWidth: 2,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Fuel (L)'
                }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false
                },
                title: {
                    display: true,
                    text: 'Cost (ETB)'
                }
            }
        }
    }
});

// Fuel Type Distribution Chart
const typeCtx = document.getElementById('fuelTypeChart').getContext('2d');
const typeLabels = <?php 
    $tLabels = [];
    $tData = [];
    $query = mysqli_query($conn, "
        SELECT fuel_type, SUM(fuel_amount) as total 
        FROM fuel_records 
        GROUP BY fuel_type
    ");
    while ($row = mysqli_fetch_assoc($query)) {
        $tLabels[] = ucfirst($row['fuel_type']);
        $tData[] = (float)$row['total'];
    }
    echo json_encode($tLabels);
?>;
const typeData = <?php echo json_encode($tData); ?>;

new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: typeLabels,
        datasets: [{
            data: typeData,
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#4BC0C0'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Search functionality
document.getElementById('searchFuel').addEventListener('keyup', function() {
    const search = this.value.toLowerCase();
    const rows = document.querySelectorAll('#fuelTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Filter by fuel type
document.getElementById('filterFuelType').addEventListener('change', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#fuelTable tbody tr');
    rows.forEach(row => {
        const type = row.cells[8]?.textContent.toLowerCase().trim() || '';
        row.style.display = !filter || type === filter ? '' : 'none';
    });
});

// Export fuel data to CSV
function exportFuelData() {
    const table = document.getElementById('fuelTable');
    const rows = table.querySelectorAll('tbody tr');
    let csv = 'Driver,Vehicle,Date,Fuel (L),Distance (km),Efficiency (km/L),Cost (ETB),Type,Location\n';
    
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        const cols = row.querySelectorAll('td');
        if (cols.length > 0) {
            const data = [];
            for (let i = 1; i < cols.length; i++) {
                let text = cols[i].textContent.trim();
                // Clean up efficiency badge text
                if (i === 6) {
                    text = text.replace(/[^\d.]/g, '');
                }
                data.push(text);
            }
            csv += data.join(',') + '\n';
        }
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `fuel_records_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php include '../includes/footer.php'; ?>