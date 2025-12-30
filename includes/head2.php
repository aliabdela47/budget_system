<meta charset="UTF-8">
<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>Budget System</title>

<!-- Favicon & Theme Color -->
<link rel="icon" type="image/png" href="images/bureau-logo.png" sizes="32x32">
<link rel="apple-touch-icon" href="images/bureau-logo.png">
<meta name="theme-color" content="#4f46e5">

<!-- Preconnect for Performance -->
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- Stylesheets -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Tailwind Config -->
<script>
  tailwind.config = {
    darkMode: 'class', // Enable dark mode
    theme: {
      extend: {
        colors: {
          primary: '#6366f1',
          secondary: '#8b5cf6',
          accent: '#ec4899',
          dark: '#0b1220'
        }
      }
    }
  }
</script>

<!-- Common Styles -->
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
  @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap');
  body { font-family: 'Inter', sans-serif; }
  .ethio-font { font-family: 'Noto Sans Ethiopic', sans-serif; }
  .main-content { width: 100%; }
  .select2-container--default .select2-selection--single{height:42px;border:1px solid #d1d5db;border-radius:.375rem}
  .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:40px;padding-left:12px}
  .select2-container--default .select2-selection--single .select2-selection__arrow{height:40px}
  .info-card{background:linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%);border-left:4px solid #3b82f6}
  .program-card{background:linear-gradient(135deg,#f0f4ff 0%,#e0e7ff 100%);border-left:4px solid #6366f1}
  .employee-card{background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border-left:4px solid #22c55e}
</style>