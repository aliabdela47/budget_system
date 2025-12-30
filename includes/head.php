<?php
// includes/head.php
// Shared <head> for all Budget System pages.

// Allow each page to override the <title> by setting $pageTitle
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#4f46e5">

<title><?= htmlspecialchars($pageTitle ?? 'AFAR-RHB Financial System') ?></title>

<!-- SweetAlert2 Custom CSS -->
<link rel="stylesheet" href="css/sweetalert-custom.css">

<!-- Fonts -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Icons & Styles -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">

<!-- OPTIONAL: If Materialize is truly needed site-wide, fix the path: -->
<!-- <link href="assets/css/materialize.css" rel="stylesheet"> -->
<!-- Otherwise, omit it to avoid conflicts with Tailwind -->


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
          'bounce-soft': 'bounceSoft 2s infinite',
          'slide-in-left': 'slideInLeft 0.3s ease-out'
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
          slideInLeft: {
            '0%': { opacity: '0', transform: 'translateX(-100%)' },
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

<!-- Global Styles (keep single source of truth for layout + Select2) -->
<style>
  body { 
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    min-height: 100vh;
    overflow-x: hidden;
  }

  .ethio-font { 
    font-family: 'Noto Sans Ethiopic', sans-serif; 
  }

  /* Main content area with sidebar integration */
  .main-content { 
    width: 100%;
    min-height: 100vh;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin-left: 17.5rem; /* CORRECTED: 280px */
  }
  .main-content.sidebar-collapsed { 
    margin-left: 5rem; /* 80px */
  }
  @media (max-width: 1023px) {
    .main-content { 
      margin-left: 0 !important; 
    }
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
</style>

<!-- jQuery & Select2 (load once globally) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>