// ============================================ //
// SMART DRIVER - MAIN JAVASCRIPT              //
// ============================================ //

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});

// Fuel efficiency calculator
function calculateFuelEfficiency(distance, fuel) {
    if (fuel <= 0) return 0;
    return (distance / fuel).toFixed(2);
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'ETB'
    }).format(amount);
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const inputs = form.querySelectorAll('input[required], select[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#c62828';
            isValid = false;
        } else {
            input.style.borderColor = '#2e7d32';
        }
    });
    
    return isValid;
}

// Show/hide password toggle
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
}

// Create chart helper
function createChart(canvasId, type, data, options) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    return new Chart(ctx, {
        type: type,
        data: data,
        options: options || {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

// AJAX helper
function fetchData(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(JSON.parse(xhr.responseText));
            } else {
                reject(xhr.statusText);
            }
        };
        
        xhr.onerror = function() {
            reject('Network error');
        };
        
        xhr.send(data ? JSON.stringify(data) : null);
    });
}

// Driver score color helper
function getScoreColor(score) {
    if (score >= 90) return '#2e7d32';
    if (score >= 70) return '#f9a825';
    if (score >= 50) return '#f57c00';
    return '#c62828';
}

// Driver score label helper
function getScoreLabel(score) {
    if (score >= 90) return '🌟 Excellent Driver';
    if (score >= 70) return '👍 Good Driver';
    if (score >= 50) return '⚠️ Fair Driver';
    if (score >= 30) return '⚠️ Risky Driver';
    return '🚫 Dangerous Driver';
}

// Initialize dashboard charts
function initDashboardCharts() {
    // Weekly violations chart
    const weeklyCtx = document.getElementById('weeklyViolationsChart');
    if (weeklyCtx) {
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Violations',
                    data: [12, 19, 15, 22, 18, 10, 8],
                    backgroundColor: 'rgba(26, 35, 126, 0.6)',
                    borderColor: 'rgba(26, 35, 126, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initDashboardCharts();
});

// Touch feedback for mobile
document.addEventListener('touchstart', function() {}, {passive: true});