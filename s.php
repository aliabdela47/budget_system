<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #374151;
        }
        .ethiopic {
            font-family: 'Noto Sans Ethiopic', sans-serif;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        th {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        td {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background-color: #f9fafb;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .input-group {
            margin-bottom: 16px;
        }
        .input-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }
        .input-group input, .input-group select, .input-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .input-group input:focus, .input-group select:focus, .input-group textarea:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px 12px 0 0;
            overflow: hidden;
        }
        .tab {
            flex: 1;
            padding: 16px;
            text-align: center;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .tab.active {
            color: #0ea5e9;
            border-bottom: 3px solid #0ea5e9;
            background: rgba(14, 165, 233, 0.05);
        }
        .tab-content {
            display: none;
            padding: 24px;
            background: white;
            border-radius: 0 0 12px 12px;
        }
        .tab-content.active {
            display: block;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 1100;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        .notification.show {
            transform: translateX(0);
        }
        .notification.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .notification.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
    </style>
</head>
<body class="p-4 md:p-6">
    <div class="max-w-7xl mx-auto">
        <header class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">
                <i class="fas fa-database text-blue-600 mr-3"></i>Database Management System
            </h1>
            <p class="text-gray-600">Manage budget owners, vehicles, and budget codes in one place</p>
        </header>

        <div class="tabs mb-6">
            <div class="tab active" data-tab="budget_owners">
                <i class="fas fa-building mr-2"></i> Budget Owners
            </div>
            <div class="tab" data-tab="vehicles">
                <i class="fas fa-car mr-2"></i> Vehicles
            </div>
            <div class="tab" data-tab="budget_codes">
                <i class="fas fa-code mr-2"></i> Budget Codes
            </div>
        </div>

        <!-- Budget Owners Tab -->
        <div class="tab-content active" id="budget_owners">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1">
                    <div class="card p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-plus-circle text-blue-600 mr-3"></i>Add New Budget Owner
                        </h2>
                        <form id="budgetOwnerForm">
                            <input type="hidden" id="owner_id" value="">
                            <div class="input-group">
                                <label for="owner_code">Code</label>
                                <input type="text" id="owner_code" required>
                            </div>
                            <div class="input-group">
                                <label for="owner_name">Name</label>
                                <input type="text" id="owner_name" required>
                            </div>
                            <div class="input-group">
                                <label for="owner_p_koox">P Koox</label>
                                <input type="text" id="owner_p_koox">
                            </div>
                            <div class="flex space-x-3">
                                <button type="submit" class="btn btn-primary flex-1">
                                    <i class="fas fa-save mr-2"></i> Save
                                </button>
                                <button type="button" id="resetOwnerForm" class="btn btn-danger">
                                    <i class="fas fa-times mr-2"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="lg:col-span-2">
                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Budget Owners</h2>
                            <div class="relative">
                                <input type="text" placeholder="Search owners..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg" id="searchOwners">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>P Koox</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="ownersTable">
                                    <!-- Data will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vehicles Tab -->
        <div class="tab-content" id="vehicles">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1">
                    <div class="card p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-plus-circle text-blue-600 mr-3"></i>Add New Vehicle
                        </h2>
                        <form id="vehicleForm">
                            <input type="hidden" id="vehicle_id" value="">
                            <div class="input-group">
                                <label for="vehicle_model">Model</label>
                                <input type="text" id="vehicle_model" required>
                            </div>
                            <div class="input-group">
                                <label for="vehicle_plate_no">Plate No</label>
                                <input type="text" id="vehicle_plate_no" required>
                            </div>
                            <div class="input-group">
                                <label for="vehicle_chassis_no">Chassis No</label>
                                <input type="text" id="vehicle_chassis_no">
                            </div>
                            <div class="flex space-x-3">
                                <button type="submit" class="btn btn-primary flex-1">
                                    <i class="fas fa-save mr-2"></i> Save
                                </button>
                                <button type="button" id="resetVehicleForm" class="btn btn-danger">
                                    <i class="fas fa-times mr-2"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="lg:col-span-2">
                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Vehicles</h2>
                            <div class="relative">
                                <input type="text" placeholder="Search vehicles..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg" id="searchVehicles">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Model</th>
                                        <th>Plate No</th>
                                        <th>Chassis No</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="vehiclesTable">
                                    <!-- Data will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Codes Tab -->
        <div class="tab-content" id="budget_codes">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1">
                    <div class="card p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-plus-circle text-blue-600 mr-3"></i>Add New Budget Code
                        </h2>
                        <form id="budgetCodeForm">
                            <input type="hidden" id="code_id" value="">
                            <div class="input-group">
                                <label for="code_code">Code</label>
                                <input type="text" id="code_code" required>
                            </div>
                            <div class="input-group">
                                <label for="code_name">Name</label>
                                <input type="text" id="code_name" required>
                            </div>
                            <div class="flex space-x-3">
                                <button type="submit" class="btn btn-primary flex-1">
                                    <i class="fas fa-save mr-2"></i> Save
                                </button>
                                <button type="button" id="resetCodeForm" class="btn btn-danger">
                                    <i class="fas fa-times mr-2"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="lg:col-span-2">
                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">Budget Codes</h2>
                            <div class="relative">
                                <input type="text" placeholder="Search codes..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg" id="searchCodes">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="codesTable">
                                    <!-- Data will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="notification">
        <i class="fas fa-check-circle mr-2"></i>
        <span id="notification-text"></span>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    // Show active content
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === tabId) {
                            content.classList.add('active');
                        }
                    });
                });
            });

            // Sample data (in a real application, this would come from a server)
            const budgetOwners = [
                { id: 5, code: '01', name: 'ዋና ቢሮ ሐላፊ', p_koox: '341/01/01' },
                { id: 6, code: '02', name: 'የበሽታ መከላከልና መቆጣጠር ዘርፍ ም/ቢ/ሐላፊ', p_koox: '341/01/01' },
                { id: 7, code: '03', name: 'የኦፕሬሽን ዘርፍ ም/ቢ/ሐላፊ', p_koox: '341/01/01' }
            ];

            const vehicles = [
                { id: 1, model: 'Toyota Land Cruiser', plate_no: 'AA1234', chassis_no: 'JTEHH05J042085750' },
                { id: 2, model: 'Toyota Hiace', plate_no: 'AB5678', chassis_no: 'JTFSS21P202085856' }
            ];

            const budgetCodes = [
                { id: 5, code: '6217', name: 'Sansii kee Sukutih' },
                { id: 6, code: '6231', name: 'Ayroh Assentah' },
                { id: 7, code: '6232', name: 'Transporti Mekláh' }
            ];

            // Populate tables with data
            function populateTables() {
                // Budget Owners
                const ownersTable = document.getElementById('ownersTable');
                ownersTable.innerHTML = '';
                budgetOwners.forEach(owner => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${owner.code}</td>
                        <td>${owner.name}</td>
                        <td>${owner.p_koox}</td>
                        <td>
                            <button class="btn btn-primary mr-2 edit-owner" data-id="${owner.id}">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                            <button class="btn btn-danger delete-owner" data-id="${owner.id}">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </td>
                    `;
                    ownersTable.appendChild(row);
                });

                // Vehicles
                const vehiclesTable = document.getElementById('vehiclesTable');
                vehiclesTable.innerHTML = '';
                vehicles.forEach(vehicle => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${vehicle.model}</td>
                        <td>${vehicle.plate_no}</td>
                        <td>${vehicle.chassis_no || 'N/A'}</td>
                        <td>
                            <button class="btn btn-primary mr-2 edit-vehicle" data-id="${vehicle.id}">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                            <button class="btn btn-danger delete-vehicle" data-id="${vehicle.id}">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </td>
                    `;
                    vehiclesTable.appendChild(row);
                });

                // Budget Codes
                const codesTable = document.getElementById('codesTable');
                codesTable.innerHTML = '';
                budgetCodes.forEach(code => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${code.code}</td>
                        <td>${code.name}</td>
                        <td>
                            <button class="btn btn-primary mr-2 edit-code" data-id="${code.id}">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                            <button class="btn btn-danger delete-code" data-id="${code.id}">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </td>
                    `;
                    codesTable.appendChild(row);
                });

                // Add event listeners to edit and delete buttons
                addEventListeners();
            }

            // Add event listeners to buttons
            function addEventListeners() {
                // Budget Owner form submission
                document.getElementById('budgetOwnerForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const id = document.getElementById('owner_id').value;
                    const code = document.getElementById('owner_code').value;
                    const name = document.getElementById('owner_name').value;
                    const p_koox = document.getElementById('owner_p_koox').value;
                    
                    if (id) {
                        // Update existing owner
                        const index = budgetOwners.findIndex(owner => owner.id == id);
                        if (index !== -1) {
                            budgetOwners[index] = { id: parseInt(id), code, name, p_koox };
                            showNotification('Budget owner updated successfully!', 'success');
                        }
                    } else {
                        // Add new owner
                        const newId = Math.max(...budgetOwners.map(o => o.id), 0) + 1;
                        budgetOwners.push({ id: newId, code, name, p_koox });
                        showNotification('Budget owner added successfully!', 'success');
                    }
                    
                    populateTables();
                    document.getElementById('budgetOwnerForm').reset();
                    document.getElementById('owner_id').value = '';
                });

                // Vehicle form submission
                document.getElementById('vehicleForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const id = document.getElementById('vehicle_id').value;
                    const model = document.getElementById('vehicle_model').value;
                    const plate_no = document.getElementById('vehicle_plate_no').value;
                    const chassis_no = document.getElementById('vehicle_chassis_no').value;
                    
                    if (id) {
                        // Update existing vehicle
                        const index = vehicles.findIndex(vehicle => vehicle.id == id);
                        if (index !== -1) {
                            vehicles[index] = { id: parseInt(id), model, plate_no, chassis_no };
                            showNotification('Vehicle updated successfully!', 'success');
                        }
                    } else {
                        // Add new vehicle
                        const newId = Math.max(...vehicles.map(v => v.id), 0) + 1;
                        vehicles.push({ id: newId, model, plate_no, chassis_no });
                        showNotification('Vehicle added successfully!', 'success');
                    }
                    
                    populateTables();
                    document.getElementById('vehicleForm').reset();
                    document.getElementById('vehicle_id').value = '';
                });

                // Budget Code form submission
                document.getElementById('budgetCodeForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const id = document.getElementById('code_id').value;
                    const code = document.getElementById('code_code').value;
                    const name = document.getElementById('code_name').value;
                    
                    if (id) {
                        // Update existing code
                        const index = budgetCodes.findIndex(c => c.id == id);
                        if (index !== -1) {
                            budgetCodes[index] = { id: parseInt(id), code, name };
                            showNotification('Budget code updated successfully!', 'success');
                        }
                    } else {
                        // Add new code
                        const newId = Math.max(...budgetCodes.map(c => c.id), 0) + 1;
                        budgetCodes.push({ id: newId, code, name });
                        showNotification('Budget code added successfully!', 'success');
                    }
                    
                    populateTables();
                    document.getElementById('budgetCodeForm').reset();
                    document.getElementById('code_id').value = '';
                });

                // Reset forms
                document.getElementById('resetOwnerForm').addEventListener('click', function() {
                    document.getElementById('budgetOwnerForm').reset();
                    document.getElementById('owner_id').value = '';
                });

                document.getElementById('resetVehicleForm').addEventListener('click', function() {
                    document.getElementById('vehicleForm').reset();
                    document.getElementById('vehicle_id').value = '';
                });

                document.getElementById('resetCodeForm').addEventListener('click', function() {
                    document.getElementById('budgetCodeForm').reset();
                    document.getElementById('code_id').value = '';
                });
            }

            // Edit functionality
            function addEventListeners() {
                // ... (previous event listeners code)

                // Edit budget owner
                document.querySelectorAll('.edit-owner').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const owner = budgetOwners.find(o => o.id == id);
                        
                        if (owner) {
                            document.getElementById('owner_id').value = owner.id;
                            document.getElementById('owner_code').value = owner.code;
                            document.getElementById('owner_name').value = owner.name;
                            document.getElementById('owner_p_koox').value = owner.p_koox;
                        }
                    });
                });

                // Edit vehicle
                document.querySelectorAll('.edit-vehicle').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const vehicle = vehicles.find(v => v.id == id);
                        
                        if (vehicle) {
                            document.getElementById('vehicle_id').value = vehicle.id;
                            document.getElementById('vehicle_model').value = vehicle.model;
                            document.getElementById('vehicle_plate_no').value = vehicle.plate_no;
                            document.getElementById('vehicle_chassis_no').value = vehicle.chassis_no || '';
                        }
                    });
                });

                // Edit budget code
                document.querySelectorAll('.edit-code').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const code = budgetCodes.find(c => c.id == id);
                        
                        if (code) {
                            document.getElementById('code_id').value = code.id;
                            document.getElementById('code_code').value = code.code;
                            document.getElementById('code_name').value = code.name;
                        }
                    });
                });

                // Delete functionality
                document.querySelectorAll('.delete-owner').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        if (confirm('Are you sure you want to delete this budget owner?')) {
                            const index = budgetOwners.findIndex(o => o.id == id);
                            if (index !== -1) {
                                budgetOwners.splice(index, 1);
                                populateTables();
                                showNotification('Budget owner deleted successfully!', 'success');
                            }
                        }
                    });
                });

                document.querySelectorAll('.delete-vehicle').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        if (confirm('Are you sure you want to delete this vehicle?')) {
                            const index = vehicles.findIndex(v => v.id == id);
                            if (index !== -1) {
                                vehicles.splice(index, 1);
                                populateTables();
                                showNotification('Vehicle deleted successfully!', 'success');
                            }
                        }
                    });
                });

                document.querySelectorAll('.delete-code').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        if (confirm('Are you sure you want to delete this budget code?')) {
                            const index = budgetCodes.findIndex(c => c.id == id);
                            if (index !== -1) {
                                budgetCodes.splice(index, 1);
                                populateTables();
                                showNotification('Budget code deleted successfully!', 'success');
                            }
                        }
                    });
                });

                // Search functionality
                document.getElementById('searchOwners').addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#ownersTable tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });

                document.getElementById('searchVehicles').addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#vehiclesTable tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });

                document.getElementById('searchCodes').addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#codesTable tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });
            }

            // Show notification
            function showNotification(message, type) {
                const notification = document.getElementById('notification');
                const notificationText = document.getElementById('notification-text');
                
                notificationText.textContent = message;
                notification.className = `notification ${type} show`;
                
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 3000);
            }

            // Initialize the application
            populateTables();
        });
    </script>
</body>
</html>