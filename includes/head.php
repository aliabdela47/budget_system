<?php
// includes/head.php
// Shared <head> for all Budget System pages.

// Allow each page to override the <title> by setting $pageTitle
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#4f46e5">

<title><?= htmlspecialchars($pageTitle ?? 'AFAR-RHB Financial System') ?></title>

<!-- Fonts -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Icons & Styles -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- CSS -->
<link href="../assets/css/materialize.css" rel="stylesheet">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="css/styles.css">

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          primary:   '#4f46e5',
          secondary: '#7c3aed',
          accent:    '#fbbf24',
          light:     '#f8fafc',
          lighter:   '#f1f5f9',
          dark:      '#1f2937'
        },
        fontFamily: {
          'sans': ['Inter', 'system-ui', 'sans-serif'],
          'ethiopic': ['Noto Sans Ethiopic', 'sans-serif']
        },
        animation: {
          'fade-in': 'fadeIn 0.5s ease-in-out',
          'slide-in': 'slideIn 0.3s ease-out',
          'bounce-soft': 'bounceSoft 2s infinite'
        },
        keyframes: {
          fadeIn: {
            '0%': { opacity: '0', transform: 'translateY(10px)' },
            '100%': { opacity: '1', transform: 'translateY(0)' }
          },
          slideIn: {
            '0%': { opacity: '0', transform: 'translateX(-20px)' },
            '100%': { opacity: '1', transform: 'translateX(0)' }
          },
          bounceSoft: {
            '0%, 100%': { transform: 'translateY(0)' },
            '50%': { transform: 'translateY(-5px)' }
          }
        }
      }
    }
  }
</script>

<!-- Global Styles -->
<style>
    body { 
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        min-height: 100vh;
    }
    
    .ethio-font { 
        font-family: 'Noto Sans Ethiopic', sans-serif; 
    }
    
    .fade-out {
        opacity: 1;
        transition: opacity 0.5s ease-out;
    }
    
    .fade-out.hide {
        opacity: 0;
    }
    
    .main-content { 
        width: 100%;
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .main-content.expanded {
        margin-left: 0;
    }
    
    /* Enhanced Select2 Styling */
    .select2-container--default .select2-selection--single {
        height: 46px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        background: white;
        transition: all 0.3s ease;
    }
    
    .select2-container--default .select2-selection--single:hover {
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 44px;
        padding-left: 16px;
        color: #374151;
        font-weight: 500;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px;
        right: 12px;
    }
    
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #4f46e5;
        color: white;
    }
    
    /* Enhanced Card Styles */
    .info-card {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border-left: 4px solid #3b82f6;
        border-radius: 12px;
    }
    
    .program-card {
        background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
        border-left: 4px solid #6366f1;
        border-radius: 12px;
    }
    
    .employee-card {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border-left: 4px solid #22c55e;
        border-radius: 12px;
    }
    
    .vehicle-card {
        background: linear-gradient(135deg, #fef7ed 0%, #fed7aa 100%);
        border-left: 4px solid #ea580c;
        border-radius: 12px;
    }
    
    .row-click { 
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .row-click:hover {
        background-color: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    /* Enhanced Button Styles */
    .btn-modern {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        border: none;
        border-radius: 12px;
        padding: 0.875rem 1.5rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
    }
    
    .btn-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
    }
    
    /* Glassmorphism Effect */
    .glass-card {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
</style>

<!-- jQuery & Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>