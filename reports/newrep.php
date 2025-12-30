<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>New Report Form - Budget System</title>
 
  <script src="css/tailwind.css"> </script>
  <link rel="stylesheet" href="css/all.min.css">
 
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#4f46e5',
            secondary: '#7c3aed',
            light: '#f8fafc',
            lighter: '#f1f5f9',
            success: '#10B981',
            error: '#EF4444',
            warning: '#F59E0B',
          }
        }
      }
    }
  </script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
    
    .fade-out {
      opacity: 1;
      transition: opacity 0.5s ease-out;
    }
    
    .fade-out.hide {
      opacity: 0;
    }
    
    .sidebar {
      width: 280px;
      transition: all 0.3s ease;
    }
    
    .sidebar.collapsed {
      margin-left: -280px;
    }
    
    .main-content {
      width: calc(100% - 280px);
      transition: all 0.3s ease;
    }
    
    .main-content.expanded {
      width: 100%;
    }
    
    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        left: 0;
        z-index: 1000;
        height: 100vh;
        overflow-y: auto;
      }
      
      .main-content {
        width: 100%;
      }
    }
  </style>
</head>








            <!-- Existing Budgets -->
            <div class="bg-white rounded-xl p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Existing Budgets</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Directorates/Programes</th>
                                <th class="px-4 py-3">Budget Codes</th>
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3">Monthly Amount</th>
                                <th class="px-4 py-3">Yearly Amount</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $b): ?>
                                <tr class="border-b border-slate-200 hover:bg-slate-50">
                                    <td class="px-4 py-2 font-medium"><?php echo $b['id']; ?></td>
                                    <td class="px-4 py-2"><?php echo $b['owner_code'] . ' - ' . $b['owner_name']; ?></td>
                                    <td class="px-4 py-2"><?php echo $b['budget_code'] . ' - ' . $b['budget_name']; ?></td>
                                    <td class="px-4 py-2"><?php echo $b['month']; ?></td>
                                    <td class="px-4 py-2 font-medium"><?php echo number_format($b['monthly_amount'], 2); ?> ETB</td>
                                    <td class="px-4 py-2 font-medium"><?php echo number_format($b['yearly_amount'], 2); ?> ETB</td>
                                    <td class="px-4 py-2">
                                        <div class="flex space-x-2">
                                            <a href="?action=edit&id=<?php echo $b['id']; ?>" class="btn-secondary btn-sm">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </a>
                                            <a href="?action=delete&id=<?php echo $b['id']; ?>" class="btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                                <i class="fas fa-trash mr-1"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    </body>
</html>