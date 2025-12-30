<?php
// includes/head.php
// Shared <head> for all Budget System pages.

// Allow each page to override the <title> by setting $pageTitle
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($pageTitle ?? 'AFAR-RHB Financial System') ?></title>

<!-- Inter + Ethiopic fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<!-- Select2 for searchable dropdowns -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

<!-- Your global CSS -->
<link href="/css/sidebar.css" rel="stylesheet">
<link href="/css/styles.css" rel="stylesheet">

<!-- Tailwind + custom color palette -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          primary:   '#4f46e5',
          secondary: '#7c3aed',
          light:     '#f8fafc',
          lighter:   '#f1f5f9'
        }
      }
    }
  }
</script>

<!-- jQuery & Select2 script -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>