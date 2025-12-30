<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial System Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
            min-height: 100vh;
            padding: 20px;
            color: white;
        }
        .dashboard-container {
            background: rgba(13, 17, 23, 0.85);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            margin-top: 30px;
            backdrop-filter: blur(10px);
        }
        .dashboard-title {
            font-weight: 600;
            font-size: 2.2rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        .welcome-text {
            color: white !important;
        }
        .username {
            color: #a0d2ff !important;
            font-weight: 500;
        }
        .btn-print {
            background: linear-gradient(to right, #3494e6, #ec6ead);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        .header-content {
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            padding-bottom: 20px;
        }
        .stats-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: scale(1.02);
        }
        .card-value {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .card-label {
            font-size: 1rem;
            opacity: 0.8;
        }
        @media (max-width: 768px) {
            .dashboard-title {
                font-size: 1.7rem;
            }
            .header-content {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container dashboard-container">
        <div class="header-content">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                <h1 class="dashboard-title">
                    <span class="welcome-text">Welcome to Afar Regional Health Bureau's Financial System,</span>
                    <span class="username">Admin User</span>!
                </h1>
                <button onclick="window.print()" class="btn btn-print mt-2 mt-md-0">
                    <i class="fas fa-print me-2"></i> Print Report
                </button>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-4 mb-4">
                <div class="stats-card text-center">
                    <div class="card-value">$542,890</div>
                    <div class="card-label">Annual Budget</div>
                    <i class="fas fa-coins fa-3x mt-3 text-warning"></i>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="stats-card text-center">
                    <div class="card-value">$283,560</div>
                    <div class="card-label">Expenses YTD</div>
                    <i class="fas fa-chart-line fa-3x mt-3 text-success"></i>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="stats-card text-center">
                    <div class="card-value">72%</div>
                    <div class="card-label">Budget Utilization</div>
                    <i class="fas fa-percent fa-3x mt-3 text-info"></i>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="stats-card">
                    <h3><i class="fas fa-list-alt me-2"></i> Recent Transactions</h3>
                    <div class="table-responsive mt-3">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>10/15/2023</td>
                                    <td>Medical Equipment Purchase</td>
                                    <td>Capital Expenditure</td>
                                    <td>$42,800</td>
                                </tr>
                                <tr>
                                    <td>10/12/2023</td>
                                    <td>Pharmaceutical Supplies</td>
                                    <td>Medical Supplies</td>
                                    <td>$28,500</td>
                                </tr>
                                <tr>
                                    <td>10/08/2023</td>
                                    <td>Staff Training Program</td>
                                    <td>Human Resources</td>
                                    <td>$12,300</td>
                                </tr>
                                <tr>
                                    <td>10/03/2023</td>
                                    <td>Facility Maintenance</td>
                                    <td>Operations</td>
                                    <td>$9,750</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>